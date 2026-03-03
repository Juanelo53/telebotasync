<?php

namespace TeleBot;

/**
 * TeleBot - Motor principal del bot de Telegram.
 *
 * Verdaderamente asincrono: cada tarea pesada corre en un proceso CLI
 * independiente via proc_open(). El webhook se libera de inmediato.
 * El usuario puede enviar 100 comandos y todos se procesan en paralelo.
 *
 * Como funciona:
 *   1. Telegram envia webhook → PHP-FPM lo recibe
 *   2. Si el comando es sync → responde y listo
 *   3. Si el comando usa async() → guarda el update en un archivo,
 *      lanza un proceso CLI en background, y libera el FPM worker
 *   4. El proceso CLI carga el mismo webhook.php, re-ejecuta el handler,
 *      y esta vez async() ejecuta la tarea directamente (ya estamos en background)
 *   5. En CLI, pcntl_fork() funciona al 100% para el loop de typing
 */
class Bot
{
    private Api $api;
    private string $storagePath;
    private string $logPath;
    private int $maxAsyncTasks;
    private bool $debug;
    private ?string $secretToken;
    private ?string $webhookScript = null;
    private array $workerMeta = [];

    /** Prefijos de comando reconocidos */
    private array $commandPrefixes = ['/', '.', '!', '@', '$', '#'];

    /** @var array<string, callable> */
    private array $commands = [];

    /** @var array<string, callable> */
    private array $callbackHandlers = [];

    /** @var array<string, callable> */
    private array $callbackPrefixHandlers = [];

    /** @var array<string, callable> */
    private array $textHandlers = [];

    /** @var array<string, callable> */
    private array $eventHandlers = [];

    /** @var callable|null */
    private $fallbackHandler = null;

    /** @var array<callable> */
    private array $middleware = [];

    /** @var array<string, callable> */
    private array $listeners = [];

    public function __construct(
        string $token,
        string $storagePath = '',
        int $maxAsyncTasks = 10,
        bool $debug = false,
        ?string $secretToken = null,
        array $commandPrefixes = [],
    ) {
        $this->api = new Api($token);
        $this->storagePath = $storagePath ?: __DIR__ . '/../storage/tasks';
        $this->logPath = ($storagePath ? dirname($storagePath) : __DIR__ . '/..') . '/logs';
        $this->maxAsyncTasks = $maxAsyncTasks;
        $this->debug = $debug;
        $this->secretToken = $secretToken;

        if (!empty($commandPrefixes)) {
            $this->commandPrefixes = $commandPrefixes;
        }

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0770, true);
        }
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0770, true);
        }
    }

    public function getApi(): Api
    {
        return $this->api;
    }

    /**
     * Retorna true si estamos corriendo como worker en background.
     */
    public function isWorker(): bool
    {
        return (bool) getenv('TELEBOT_WORKER');
    }

    // ══════════════════════════════════════════════════════════
    //  REGISTRO DE HANDLERS
    // ══════════════════════════════════════════════════════════

    /**
     * Registra un handler para un comando.
     *
     *   $bot->command('/start', fn(Context $ctx) => $ctx->reply("Hola!"));
     *   $bot->command('.help',  fn(Context $ctx) => $ctx->reply("Ayuda"));
     *   $bot->command('!ban',   fn(Context $ctx) => $ctx->reply("Baneado"));
     *   $bot->command('$price', fn(Context $ctx) => $ctx->reply("$100"));
     */
    public function command(string $cmd, callable $handler): self
    {
        $cmd = strtolower($cmd);
        if ($cmd !== '' && !in_array($cmd[0], $this->commandPrefixes)) {
            $cmd = '/' . $cmd;
        }
        $this->commands[$cmd] = $handler;
        return $this;
    }

    /**
     * Registra multiples comandos al mismo handler.
     *
     *   $bot->commands(['/help', '/ayuda', '.help'], fn($ctx) => ...);
     */
    public function commands(array $cmds, callable $handler): self
    {
        foreach ($cmds as $cmd) {
            $this->command($cmd, $handler);
        }
        return $this;
    }

    /**
     * Handler para callback query exacto.
     *
     *   $bot->callback('confirmar', fn($ctx) => $ctx->answerCallback("OK"));
     */
    public function callback(string $data, callable $handler): self
    {
        $this->callbackHandlers[$data] = $handler;
        return $this;
    }

    /**
     * Handler para callbacks con prefijo.
     *
     *   $bot->callbackPrefix('item_', fn($ctx) => ...); // item_1, item_abc
     */
    public function callbackPrefix(string $prefix, callable $handler): self
    {
        $this->callbackPrefixHandlers[$prefix] = $handler;
        return $this;
    }

    /**
     * Handler para texto que contenga un patron.
     *
     *   $bot->hears('hola', fn($ctx) => $ctx->reply("Que onda!"));
     *   $bot->hears('/precio|costo/i', fn($ctx) => $ctx->reply("$100"));
     */
    public function hears(string $pattern, callable $handler): self
    {
        $this->textHandlers[$pattern] = $handler;
        return $this;
    }

    /**
     * Handler para tipos de media y eventos.
     *
     *   $bot->on('photo', fn($ctx) => $ctx->reply("Linda foto!"));
     *   $bot->on('document', fn($ctx) => $ctx->reply("Archivo recibido"));
     *   $bot->on('voice', fn($ctx) => ...);
     *   $bot->on('sticker', fn($ctx) => ...);
     *   $bot->on('location', fn($ctx) => ...);
     *   $bot->on('text', fn($ctx) => ...);
     */
    public function on(string $event, callable $handler): self
    {
        $this->eventHandlers[strtolower($event)] = $handler;
        return $this;
    }

    /**
     * Handler por defecto cuando nada matchea.
     */
    public function fallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;
        return $this;
    }

    /**
     * Middleware que se ejecuta antes de cada handler.
     *
     *   $bot->use(function(Context $ctx, callable $next) {
     *       if (isBanned($ctx->userId())) return;
     *       $next($ctx);
     *   });
     */
    public function use(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Listener para eventos internos.
     * Eventos: 'async_start', 'async_done', 'async_error', 'update'
     */
    public function listen(string $event, callable $handler): self
    {
        $this->listeners[$event] = $handler;
        return $this;
    }

    // ══════════════════════════════════════════════════════════
    //  EJECUCION
    // ══════════════════════════════════════════════════════════

    /**
     * Procesa el webhook entrante.
     * Si estamos en modo worker, procesa la tarea async.
     */
    public function run(): void
    {
        // ── Modo WORKER (proceso CLI en background) ──
        if ($this->isWorker()) {
            $this->runAsWorker();
            return;
        }

        // ── Modo WEBHOOK (request HTTP de Telegram) ──

        // Validar secret_token
        if ($this->secretToken !== null) {
            $headerToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
            if ($headerToken !== $this->secretToken) {
                http_response_code(403);
                return;
            }
        }

        // Guardar ruta del script para los workers
        $this->webhookScript = $_SERVER['SCRIPT_FILENAME']
            ?? realpath($_SERVER['PHP_SELF'] ?? '')
            ?: null;

        // LEER php://input ANTES de flush (puede vaciarse despues)
        $rawInput = file_get_contents('php://input');
        $this->log('DEBUG', "Raw input length: " . strlen($rawInput ?: ''));

        // Responder 200 de inmediato
        http_response_code(200);
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        }
        $this->flushResponse();

        // Limpiar zombies
        $this->reapZombies();

        // Parsear update desde el input ya leido
        $updateData = $rawInput ? json_decode($rawInput, true) : null;
        $update = $updateData ? Update::fromArray($updateData, $this->commandPrefixes) : null;
        if (!$update || !$update->getChatId()) {
            return;
        }

        $this->emit('update', $update);
        $this->log('DEBUG', "Update {$update->getUpdateId()}: {$update->getType()} from {$update->getUserId()}");

        $ctx = new Context($update, $this->api, $this);

        try {
            $this->runMiddleware($ctx, function (Context $ctx) {
                $this->dispatch($ctx);
            });
        } catch (\Throwable $e) {
            $this->log('ERROR', "Dispatch failed: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Modo worker: carga el update desde archivo y lo procesa.
     * Este metodo se ejecuta en un proceso CLI independiente.
     */
    private function runAsWorker(): void
    {
        $file = getenv('TELEBOT_WORKER_FILE');
        if (!$file || !file_exists($file)) {
            return;
        }

        $payload = json_decode(file_get_contents($file), true);
        @unlink($file);
        if (!$payload) return;

        // Extraer metadata del worker
        $this->workerMeta = $payload['_telebot_meta'] ?? [];
        unset($payload['_telebot_meta']);

        $update = Update::fromArray($payload, $this->commandPrefixes);
        if (!$update->getChatId()) return;

        $this->log('DEBUG', "Worker processing task: " . ($this->workerMeta['task_id'] ?? 'unknown'));

        $ctx = new Context($update, $this->api, $this);

        $this->runMiddleware($ctx, function (Context $ctx) {
            $this->dispatch($ctx);
        });
    }

    /**
     * Procesa un update manualmente (para testing/polling).
     */
    public function processUpdate(array $data): void
    {
        $update = Update::fromArray($data, $this->commandPrefixes);
        if (!$update->getChatId()) return;

        $ctx = new Context($update, $this->api, $this);

        $this->runMiddleware($ctx, function (Context $ctx) {
            $this->dispatch($ctx);
        });
    }

    // ══════════════════════════════════════════════════════════
    //  DISPATCH INTERNO
    // ══════════════════════════════════════════════════════════

    private function dispatch(Context $ctx): void
    {
        $update = $ctx->update;

        $this->log('DEBUG', "Dispatch: type={$update->getType()} text={$update->getText()} isCmd=" . ($update->isCommand() ? 'yes' : 'no') . " cmd={$update->getCommand()} registered=" . implode(',', array_keys($this->commands)));

        // 1. Callback queries
        if ($update->isCallback()) {
            $data = $update->getCallbackData();

            if (isset($this->callbackHandlers[$data])) {
                ($this->callbackHandlers[$data])($ctx);
                return;
            }

            foreach ($this->callbackPrefixHandlers as $prefix => $handler) {
                if (str_starts_with($data, $prefix)) {
                    $handler($ctx);
                    return;
                }
            }
            return;
        }

        // 2. Comandos (multi-prefijo)
        if ($update->isCommand()) {
            $cmd = $update->getCommand();

            // Match exacto
            if (isset($this->commands[$cmd])) {
                ($this->commands[$cmd])($ctx);
                return;
            }

            // Match por nombre sin prefijo
            $name = $update->getCommandName();
            foreach ($this->commands as $registeredCmd => $handler) {
                if (substr($registeredCmd, 1) === $name) {
                    $handler($ctx);
                    return;
                }
            }
        }

        // 3. Event handlers (media)
        $mediaType = $update->getMediaType();
        if ($mediaType !== null && isset($this->eventHandlers[$mediaType])) {
            ($this->eventHandlers[$mediaType])($ctx);
            return;
        }

        // 4. Texto libre + hears
        if (!$update->isCommand() && $update->getText() !== '') {
            if ($this->matchHears($ctx)) return;

            if (isset($this->eventHandlers['text'])) {
                ($this->eventHandlers['text'])($ctx);
                return;
            }
        }

        // 5. Hears para comandos no registrados
        if ($this->matchHears($ctx)) return;

        // 6. Fallback
        if ($this->fallbackHandler) {
            ($this->fallbackHandler)($ctx);
        }
    }

    private function matchHears(Context $ctx): bool
    {
        $text = $ctx->update->getText();
        if ($text === '') return false;

        $textLower = strtolower($text);

        foreach ($this->textHandlers as $pattern => $handler) {
            if (str_starts_with($pattern, '/') && @preg_match($pattern, '') !== false) {
                if (preg_match($pattern, $text)) {
                    $handler($ctx);
                    return true;
                }
            } else {
                if (str_contains($textLower, strtolower($pattern))) {
                    $handler($ctx);
                    return true;
                }
            }
        }

        return false;
    }

    private function runMiddleware(Context $ctx, callable $final): void
    {
        if (empty($this->middleware)) {
            $final($ctx);
            return;
        }

        $chain = array_reverse($this->middleware);
        $next = $final;

        foreach ($chain as $mw) {
            $current = $next;
            $next = function (Context $ctx) use ($mw, $current) {
                $mw($ctx, $current);
            };
        }

        $next($ctx);
    }

    // ══════════════════════════════════════════════════════════
    //  MOTOR ASYNC (el corazon del bot)
    // ══════════════════════════════════════════════════════════

    /**
     * Lanza una tarea en un proceso CLI en background.
     *
     * Flujo:
     *   1. Webhook recibe update → handler llama async()
     *   2. async() guarda el update en archivo + spawns worker CLI
     *   3. Worker carga webhook.php → handler llama async() de nuevo
     *   4. async() detecta que ya es worker → ejecuta la tarea directamente
     *   5. pcntl_fork() funciona en CLI para el loop de typing
     */
    public function dispatchAsync(Context $ctx, callable $task, ?string $loadingMessage = null): void
    {
        // ── Si somos un WORKER: ejecutar la tarea directamente ──
        if ($this->isWorker()) {
            $this->executeWorkerTask($ctx, $task);
            return;
        }

        // ── Si somos WEBHOOK: lanzar worker en background ──

        // Verificar limite de tareas
        $running = $this->countRunningTasks();
        if ($running >= $this->maxAsyncTasks) {
            $ctx->reply("Hay muchas tareas en proceso ({$running}). Intenta en un momento.");
            return;
        }

        // Enviar mensaje de carga
        $loadingMsgId = null;
        if ($loadingMessage !== null) {
            $result = $ctx->reply($loadingMessage);
            $loadingMsgId = $result['result']['message_id'] ?? null;
        }

        $taskId = $this->createTask($ctx);

        // Guardar update + metadata para el worker
        $updateFile = "{$this->storagePath}/{$taskId}_update.json";
        $payload = $ctx->update->getRaw();
        $payload['_telebot_meta'] = [
            'task_id' => $taskId,
            'loading_msg_id' => $loadingMsgId,
        ];
        file_put_contents($updateFile, json_encode($payload), LOCK_EX);

        // Intentar lanzar worker en background
        if ($this->spawnWorker($updateFile)) {
            $this->log('DEBUG', "Task {$taskId}: worker spawned in background");
            $this->emit('async_start', $taskId, $ctx);
            return; // FPM worker libre!
        }

        // Fallback: ejecutar sincrono (HTTP ya se cerro)
        $this->log('DEBUG', "Task {$taskId}: worker failed, running synchronously");
        @unlink($updateFile);

        $this->updateTask($taskId, ['status' => 'running', 'started_at' => time()]);
        try {
            $task($ctx);
            if ($loadingMsgId) $ctx->delete($loadingMsgId);
            $this->updateTask($taskId, ['status' => 'done', 'finished_at' => time()]);
        } catch (\Throwable $e) {
            $this->log('ERROR', "Task {$taskId} fallback failed: {$e->getMessage()}");
            $ctx->reply("Error: " . Helpers::escape($e->getMessage()));
            if ($loadingMsgId) $ctx->delete($loadingMsgId);
            $this->updateTask($taskId, ['status' => 'error', 'finished_at' => time()]);
        }
        $this->removeTask($taskId);
    }

    /**
     * Ejecuta la tarea dentro del worker (ya estamos en background).
     */
    private function executeWorkerTask(Context $ctx, callable $task): void
    {
        $meta = $this->workerMeta;
        $taskId = $meta['task_id'] ?? null;
        $loadingMsgId = $meta['loading_msg_id'] ?? null;

        if ($taskId) {
            $this->updateTask($taskId, ['status' => 'running', 'started_at' => time()]);
        }

        try {
            $task($ctx);

            // Borrar mensaje de carga
            if ($loadingMsgId) {
                $ctx->delete($loadingMsgId);
            }

            if ($taskId) {
                $this->updateTask($taskId, ['status' => 'done', 'finished_at' => time()]);
                $this->emit('async_done', $taskId, $ctx);
            }

        } catch (\Throwable $e) {
            $this->log('ERROR', "Worker task failed: {$e->getMessage()}");
            $ctx->reply("Error: " . Helpers::escape($e->getMessage()));

            if ($loadingMsgId) {
                $ctx->delete($loadingMsgId);
            }

            if ($taskId) {
                $this->updateTask($taskId, [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'finished_at' => time(),
                ]);
                $this->emit('async_error', $taskId, $ctx, $e);
            }
        }

        if ($taskId) {
            $this->removeTask($taskId);
        }
    }

    /**
     * Lanza un proceso CLI en background que ejecuta el mismo webhook.php.
     *
     * El worker recibe el update via archivo JSON y variables de entorno:
     *   TELEBOT_WORKER=1           → indica modo worker
     *   TELEBOT_WORKER_FILE=/path  → ruta al archivo con el update
     */
    private function spawnWorker(string $updateFile): bool
    {
        if (!function_exists('proc_open')) {
            $this->log('ERROR', 'proc_open not available - cannot spawn worker');
            return false;
        }

        $scriptPath = $this->webhookScript;
        if (!$scriptPath || !file_exists($scriptPath)) {
            $this->log('ERROR', "Cannot find webhook script: {$scriptPath}");
            return false;
        }

        $phpBin = $this->findPhpCli();
        $logFile = $this->logPath . '/worker.log';

        // Comando: lanzar PHP en background con nohup
        // El & al final hace que sh lo lance en background y retorne
        $cmd = sprintf(
            'TELEBOT_WORKER=1 TELEBOT_WORKER_FILE=%s nohup %s %s >> %s 2>&1 &',
            escapeshellarg($updateFile),
            escapeshellarg($phpBin),
            escapeshellarg($scriptPath),
            escapeshellarg($logFile)
        );

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            $this->log('ERROR', 'proc_open failed to create process');
            return false;
        }

        // proc_close retorna inmediato porque sh ya lanzo el proceso con &
        proc_close($process);

        return true;
    }

    /**
     * PHP_BINARY in FPM context returns php-fpm, which can't run scripts.
     * This finds the actual PHP CLI binary.
     */
    private function findPhpCli(): string
    {
        $binary = PHP_BINARY;

        // If PHP_BINARY is the CLI binary, use it directly
        if ($binary && !str_contains($binary, 'fpm') && !str_contains($binary, 'cgi')) {
            return $binary;
        }

        // In cPanel: replace sbin/php-fpm with bin/php
        if ($binary && str_contains($binary, 'php-fpm')) {
            $cli = str_replace(['sbin/php-fpm', 'php-fpm'], ['bin/php', 'php'], $binary);
            if (file_exists($cli)) return $cli;
        }

        // Common paths
        foreach (['/usr/local/bin/php', '/usr/bin/php', '/opt/cpanel/ea-php82/root/usr/bin/php'] as $path) {
            if (file_exists($path)) return $path;
        }

        return 'php';
    }

    // ══════════════════════════════════════════════════════════
    //  TASK TRACKING
    // ══════════════════════════════════════════════════════════

    private function createTask(Context $ctx): string
    {
        $id = uniqid('task_', true);
        $data = [
            'id' => $id,
            'chat_id' => $ctx->chatId(),
            'user_id' => $ctx->userId(),
            'command' => $ctx->update->getCommand(),
            'status' => 'pending',
            'created_at' => time(),
        ];
        file_put_contents("{$this->storagePath}/{$id}.json", json_encode($data), LOCK_EX);
        return $id;
    }

    private function updateTask(string $id, array $merge): void
    {
        $file = "{$this->storagePath}/{$id}.json";
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true) ?: [];
        file_put_contents($file, json_encode(array_merge($data, $merge)), LOCK_EX);
    }

    private function removeTask(string $id): void
    {
        @unlink("{$this->storagePath}/{$id}.json");
        @unlink("{$this->storagePath}/{$id}_update.json");
    }

    private function countRunningTasks(): int
    {
        $files = glob("{$this->storagePath}/task_*.json");
        $count = 0;

        foreach ($files ?: [] as $file) {
            // Ignorar archivos de update
            if (str_contains($file, '_update.json')) continue;

            $data = json_decode(file_get_contents($file), true);
            if (!$data) {
                @unlink($file);
                continue;
            }

            // Limpiar tareas de mas de 10 minutos
            if (isset($data['created_at']) && time() - $data['created_at'] > 600) {
                @unlink($file);
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Retorna info de tareas activas.
     */
    public function getRunningTasks(): array
    {
        $files = glob("{$this->storagePath}/task_*.json");
        $tasks = [];

        foreach ($files ?: [] as $file) {
            if (str_contains($file, '_update.json')) continue;
            $data = json_decode(file_get_contents($file), true);
            if ($data) $tasks[] = $data;
        }

        return $tasks;
    }

    // ══════════════════════════════════════════════════════════
    //  UTILIDADES
    // ══════════════════════════════════════════════════════════

    private function flushResponse(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) ob_end_flush();
            flush();
        }
    }

    private function reapZombies(): void
    {
        if (!function_exists('pcntl_waitpid')) return;
        while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
            // limpiar procesos zombie
        }
    }

    public function log(string $level, string $message): void
    {
        if (!$this->debug && $level === 'DEBUG') return;

        $date = date('Y-m-d H:i:s');
        $line = "[{$date}] [{$level}] {$message}\n";
        @file_put_contents(
            $this->logPath . '/bot.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    private function emit(string $event, mixed ...$args): void
    {
        if (isset($this->listeners[$event])) {
            ($this->listeners[$event])(...$args);
        }
    }

    // ══════════════════════════════════════════════════════════
    //  WEBHOOK MANAGEMENT
    // ══════════════════════════════════════════════════════════

    public function setWebhook(string $url, array $opt = []): array
    {
        if ($this->secretToken !== null && !isset($opt['secret_token'])) {
            $opt['secret_token'] = $this->secretToken;
        }
        return $this->api->setWebhook($url, $opt);
    }

    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->api->deleteWebhook($dropPending);
    }

    public function getWebhookInfo(): array
    {
        return $this->api->getWebhookInfo();
    }

    // ══════════════════════════════════════════════════════════
    //  POLLING (desarrollo/testing)
    // ══════════════════════════════════════════════════════════

    /**
     * Modo polling para desarrollo local. NO usar en produccion.
     */
    public function poll(int $timeout = 30): never
    {
        $this->api->deleteWebhook();
        $offset = 0;

        echo "[TeleBot] Polling started... (Ctrl+C para salir)\n";

        $me = $this->api->getMe();
        if ($me['ok'] ?? false) {
            echo "[TeleBot] Bot: @{$me['result']['username']}\n";
        }

        while (true) {
            $result = $this->api->call('getUpdates', [
                'offset' => $offset,
                'timeout' => $timeout,
            ]);

            if (!($result['ok'] ?? false)) {
                echo "[TeleBot] Error: " . ($result['error'] ?? 'unknown') . "\n";
                sleep(1);
                continue;
            }

            foreach ($result['result'] as $updateData) {
                $offset = $updateData['update_id'] + 1;
                echo "[TeleBot] <- " . ($updateData['message']['text'] ?? 'update') . "\n";
                $this->processUpdate($updateData);
            }

            $this->reapZombies();
        }
    }
}
