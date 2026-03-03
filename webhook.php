<?php

/**
 * TeleBot - Punto de entrada del webhook.
 *
 * Configurar:
 *   php setup.php set
 *   php setup.php test
 */

require_once __DIR__ . '/autoload.php';

$config = require __DIR__ . '/config.php';

// ── Crear bot ─────────────────────────────────────────────────
$bot = new TeleBot\Bot(
    token:           $config['token'],
    maxAsyncTasks:   $config['max_async_tasks'] ?? 10,
    debug:           $config['debug'] ?? false,
    secretToken:     $config['secret_token'] ?? null,
    commandPrefixes: $config['command_prefixes'] ?? [],
);

// Alias para el Keyboard builder
use TeleBot\Context as Ctx;
use TeleBot\Keyboard as Kb;
use TeleBot\Helpers;

// ══════════════════════════════════════════════════════════════
//  COMANDOS
// ══════════════════════════════════════════════════════════════

$bot->command('start', function (Ctx $ctx) {
    $name = Helpers::escape($ctx->firstName());

    $ctx->reply(
        "Hola <b>{$name}</b>! Soy un bot asincrono.\n\n" .
        "Comandos:\n" .
        "/start - Este mensaje\n" .
        "/ping - Test rapido\n" .
        "/async - Prueba asincrona\n" .
        "/estado - Tareas en proceso",
        inline: [
            Kb::row(
                Kb::inline('Comandos', 'show_help'),
                Kb::inline('Estado', 'show_status'),
            ),
        ],
    );
});

$bot->command('ping', function (Ctx $ctx) {
    $ctx->reply('Pong!');
});

$bot->command('async', function (Ctx $ctx) {
    $ctx->asyncWithTyping(function (Ctx $ctx) {
        sleep(10);
        $ctx->reply("Tarea completada despues de 10 segundos!");
    }, "Procesando... esto tardara unos segundos");
});

$bot->command('imagen', function (Ctx $ctx) {
    $prompt = $ctx->args();
    if (empty($prompt)) {
        $ctx->reply("Uso: /imagen <descripcion de la imagen>");
        return;
    }

    $safe = Helpers::escape($prompt);

    $ctx->asyncWithTyping(function (Ctx $ctx) use ($safe) {
        sleep(5);
        $ctx->reply("Imagen generada para: <b>{$safe}</b>\n(Conecta tu API de imagenes aqui)");
    }, "Generando imagen: <i>{$safe}</i>...");
});

$bot->command('estado', function (Ctx $ctx) {
    $tasks = $ctx->api->call('getWebhookInfo');
    $pending = $tasks['result']['pending_update_count'] ?? 0;
    $ctx->reply("Updates pendientes: <b>{$pending}</b>");
});

// ── Callbacks ─────────────────────────────────────────────────

$bot->callback('show_help', function (Ctx $ctx) {
    $ctx->answerCallback();
    $ctx->reply(
        "<b>Comandos:</b>\n\n" .
        "/start - Inicio\n" .
        "/ping - Latencia\n" .
        "/async - Tarea asincrona\n" .
        "/imagen [prompt] - Generar imagen\n" .
        "/estado - Ver estado"
    );
});

$bot->callback('show_status', function (Ctx $ctx) {
    $ctx->answerCallback("Todo funcionando!");
});

// ══════════════════════════════════════════════════════════════
//  EJECUTAR
// ══════════════════════════════════════════════════════════════

$bot->run();
