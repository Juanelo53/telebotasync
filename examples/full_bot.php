<?php

/**
 * Ejemplo completo de TeleBot.
 *
 * Demuestra:
 *   - Comandos con multiples prefijos (/ . ! @ $ #)
 *   - Botones inline y reply keyboard
 *   - Handlers de media (fotos, documentos, etc)
 *   - Middleware (logging, admin check)
 *   - Tareas async con typing
 *   - Edicion de mensajes
 *   - Hears (patrones de texto)
 *   - Callbacks con prefijo
 */

require_once __DIR__ . '/../autoload.php';

$config = require __DIR__ . '/../config.php';

$bot = new TeleBot\Bot(
    token:           $config['token'],
    debug:           true,
    secretToken:     $config['secret_token'] ?? null,
    commandPrefixes: $config['command_prefixes'] ?? [],
);

// Alias cortos
use TeleBot\Context as Ctx;
use TeleBot\Keyboard as Kb;
use TeleBot\Helpers;

// ══════════════════════════════════════════════════════════════
//  MIDDLEWARE
// ══════════════════════════════════════════════════════════════

// Log de cada mensaje
$bot->use(function (Ctx $ctx, callable $next) {
    $user = $ctx->username() ?: $ctx->firstName();
    $text = $ctx->text() ?: $ctx->mediaType() ?: 'callback';
    error_log("[BOT] {$user}: {$text}");
    $next($ctx);
});

// ══════════════════════════════════════════════════════════════
//  COMANDOS BASICOS
// ══════════════════════════════════════════════════════════════

$bot->command('/start', function (Ctx $ctx) {
    $name = Helpers::escape($ctx->firstName());

    $ctx->reply(
        "Hola <b>{$name}</b>!\n\n" .
        "Soy un bot asincrono. Puedo procesar multiples\n" .
        "comandos al mismo tiempo sin bloquearse.\n\n" .
        "Prueba enviar /lento varias veces seguidas!",
        inline: [
            Kb::row(
                Kb::inline('Comandos', 'show_help'),
                Kb::inline('Estado', 'show_status'),
            ),
            Kb::row(
                Kb::inline('Menu', 'show_menu'),
            ),
        ],
    );
});

$bot->command('/ping', function (Ctx $ctx) {
    $start = microtime(true);
    $result = $ctx->reply('Pong!');
    $ms = round((microtime(true) - $start) * 1000, 1);

    $msgId = $result['result']['message_id'] ?? null;
    if ($msgId) {
        $ctx->edit($msgId, "Pong! <i>({$ms}ms)</i>");
    }
});

// ── Comando con reply keyboard ───────────────────────────────

$bot->command('/menu', function (Ctx $ctx) {
    $ctx->reply(
        "Elige una opcion del teclado:",
        keyboard: Kb::reply(
            ['Opcion A', 'Opcion B'],
            ['Opcion C', 'Opcion D'],
            ['Cerrar Menu'],
        ),
        oneTime: false,
        placeholder: 'Selecciona...',
    );
});

// ── Comandos con diferentes prefijos ─────────────────────────

// Todos estos funcionan: /help, .help, !help
$bot->commands(['/help', '/ayuda'], function (Ctx $ctx) {
    $ctx->reply(
        "<b>Comandos disponibles:</b>\n\n" .
        "/start - Inicio\n" .
        "/ping - Latencia\n" .
        "/menu - Teclado personalizado\n" .
        "/help - Esta ayuda\n" .
        "/lento [seg] - Tarea async\n" .
        "/imagen [prompt] - Generar imagen\n" .
        "/descargar [url] - Descargar archivo\n" .
        "/boton - Demo de botones\n" .
        "/dado - Lanzar dado\n" .
        "/encuesta - Crear encuesta\n\n" .
        "<i>Tambien puedes usar . ! @ $ # como prefijo</i>"
    );
});

// ── Demo de botones inline ───────────────────────────────────

$bot->command('/boton', function (Ctx $ctx) {
    // Grid automatico de 2 columnas
    $buttons = [
        Kb::inline('Btn 1', 'btn_1'),
        Kb::inline('Btn 2', 'btn_2'),
        Kb::inline('Btn 3', 'btn_3'),
        Kb::inline('Btn 4', 'btn_4'),
    ];

    $ctx->reply(
        "Grid de botones (2 columnas):",
        inline: Kb::grid($buttons, cols: 2),
    );
});

// ── Dado y encuesta ──────────────────────────────────────────

$bot->command('/dado', function (Ctx $ctx) {
    $ctx->replyDice();
});

$bot->command('/encuesta', function (Ctx $ctx) {
    $ctx->replyPoll(
        "Que lenguaje prefieres?",
        ['PHP', 'Python', 'JavaScript', 'Go'],
    );
});

// ══════════════════════════════════════════════════════════════
//  COMANDOS ASYNC (no bloquean)
// ══════════════════════════════════════════════════════════════

$bot->command('/lento', function (Ctx $ctx) {
    $seconds = (int)($ctx->argsArray()[0] ?? 10);
    $seconds = max(1, min($seconds, 60));

    $ctx->asyncWithTyping(function (Ctx $ctx) use ($seconds) {
        sleep($seconds);
        $ctx->reply("Tarea completada! (tarde {$seconds} segundos)");
    }, "Procesando tarea de {$seconds} segundos...");
});

$bot->command('/imagen', function (Ctx $ctx) {
    $prompt = $ctx->args();
    if (empty($prompt)) {
        $ctx->reply(
            "Uso: /imagen <descripcion>\n\n" .
            "Ejemplo: /imagen un gato espacial"
        );
        return;
    }

    $safe = Helpers::escape($prompt);

    $ctx->asyncWithTyping(function (Ctx $ctx) use ($safe) {
        // ── Aqui conectas tu API de imagenes ──
        // Ejemplo:
        //   $response = Helpers::httpPostJson('https://api.example.com/generate', [
        //       'prompt' => $prompt,
        //   ], ['Authorization: Bearer TU_KEY']);
        //   $data = json_decode($response, true);
        //   $ctx->replyPhoto($data['url'], caption: $prompt);

        sleep(5); // Simulacion
        $ctx->reply("Imagen generada para: <b>{$safe}</b>\n\n<i>(Conecta tu API aqui)</i>");
    }, "Generando: <i>{$safe}</i>...");
});

$bot->command('/descargar', function (Ctx $ctx) {
    $url = $ctx->args();
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        $ctx->reply("Uso: /descargar <URL>");
        return;
    }

    $ctx->async(function (Ctx $ctx) use ($url) {
        $ctx->typing();

        $tmpFile = tempnam(sys_get_temp_dir(), 'telebot_');
        $success = Helpers::downloadUrl($url, $tmpFile);

        if ($success && filesize($tmpFile) > 0) {
            $size = Helpers::formatBytes(filesize($tmpFile));
            $ctx->replyDocument($tmpFile, caption: "Descargado: {$size}");
        } else {
            $ctx->reply("No se pudo descargar el archivo.");
        }

        @unlink($tmpFile);
    }, "Descargando archivo...");
});

// ══════════════════════════════════════════════════════════════
//  CALLBACKS
// ══════════════════════════════════════════════════════════════

$bot->callback('show_help', function (Ctx $ctx) {
    $ctx->answerCallback();
    $ctx->reply(
        "<b>Comandos:</b>\n\n" .
        "/start - Inicio\n" .
        "/ping - Latencia\n" .
        "/help - Ayuda\n" .
        "/menu - Teclado\n" .
        "/lento [seg] - Async\n" .
        "/imagen [prompt] - Imagen\n" .
        "/boton - Demo botones\n" .
        "/dado - Dado\n" .
        "/encuesta - Encuesta"
    );
});

$bot->callback('show_status', function (Ctx $ctx) {
    $ctx->answerCallback("Todo funcionando!");
});

$bot->callback('show_menu', function (Ctx $ctx) {
    $ctx->answerCallback();
    $ctx->reply(
        "Menu del bot:",
        inline: [
            Kb::row(Kb::inline('Info', 'menu_info'), Kb::inline('Config', 'menu_config')),
            Kb::row(Kb::inline('Volver', 'show_help')),
        ],
    );
});

// Callbacks con prefijo: menu_info, menu_config, etc.
$bot->callbackPrefix('menu_', function (Ctx $ctx) {
    $option = str_replace('menu_', '', $ctx->callbackData());
    $ctx->answerCallback("Seleccionaste: {$option}");
    $ctx->reply("Opcion del menu: <b>{$option}</b>");
});

// Callbacks con prefijo: btn_1, btn_2, etc.
$bot->callbackPrefix('btn_', function (Ctx $ctx) {
    $num = str_replace('btn_', '', $ctx->callbackData());
    $ctx->answerCallback("Boton {$num}!");
});

// ══════════════════════════════════════════════════════════════
//  HANDLERS DE MEDIA
// ══════════════════════════════════════════════════════════════

$bot->on('photo', function (Ctx $ctx) {
    $ctx->reply("Linda foto! La recibi correctamente.");
});

$bot->on('document', function (Ctx $ctx) {
    $name = Helpers::escape($ctx->update->getDocumentName());
    $ctx->reply("Documento recibido: <b>{$name}</b>");
});

$bot->on('sticker', function (Ctx $ctx) {
    $ctx->reply("Buen sticker!");
});

$bot->on('voice', function (Ctx $ctx) {
    $ctx->reply("Nota de voz recibida!");
});

$bot->on('location', function (Ctx $ctx) {
    $loc = $ctx->update->getLocation();
    $lat = $loc['latitude'] ?? 0;
    $lon = $loc['longitude'] ?? 0;
    $ctx->reply("Ubicacion recibida: <code>{$lat}, {$lon}</code>");
});

// ══════════════════════════════════════════════════════════════
//  HEARS (patrones de texto)
// ══════════════════════════════════════════════════════════════

$bot->hears('hola', function (Ctx $ctx) {
    $ctx->reply("Que onda <b>{$ctx->firstName()}</b>!");
});

$bot->hears('/gracias|thanks|thx/i', function (Ctx $ctx) {
    $ctx->reply("De nada! Para eso estoy.");
});

$bot->hears('Cerrar Menu', function (Ctx $ctx) {
    $ctx->reply("Menu cerrado.", extra: [
        'reply_markup' => json_encode(Kb::remove()),
    ]);
});

// Responder a opciones del reply keyboard
$bot->hears('Opcion A', function (Ctx $ctx) {
    $ctx->reply("Elegiste la <b>Opcion A</b>!");
});

$bot->hears('Opcion B', function (Ctx $ctx) {
    $ctx->reply("Elegiste la <b>Opcion B</b>!");
});

// ══════════════════════════════════════════════════════════════
//  FALLBACK
// ══════════════════════════════════════════════════════════════

$bot->fallback(function (Ctx $ctx) {
    if ($ctx->update->isMessage() && $ctx->text()) {
        $ctx->reply("No entendi. Usa /help para ver los comandos.");
    }
});

// ══════════════════════════════════════════════════════════════
//  RUN
// ══════════════════════════════════════════════════════════════

$bot->run();
