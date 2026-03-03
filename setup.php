<?php

/**
 * Script para configurar el webhook del bot.
 *
 * Uso desde terminal:
 *   php setup.php set                - Configurar webhook
 *   php setup.php set <URL>          - Configurar con URL personalizada
 *   php setup.php info               - Ver info del webhook
 *   php setup.php delete             - Eliminar webhook
 *   php setup.php test               - Probar conexion
 *   php setup.php commands           - Registrar comandos en BotFather
 */

require_once __DIR__ . '/autoload.php';

$config = require __DIR__ . '/config.php';

$bot = new TeleBot\Bot(
    token: $config['token'],
    secretToken: $config['secret_token'] ?? null,
);
$api = $bot->getApi();

$action = $argv[1] ?? 'info';

switch ($action) {
    case 'set':
        $url = $argv[2] ?? $config['webhook_url'] ?? null;
        if (!$url) {
            echo "Uso: php setup.php set <URL>\n";
            exit(1);
        }

        $opt = [];
        if (!empty($config['secret_token'])) {
            $opt['secret_token'] = $config['secret_token'];
        }

        $result = $api->setWebhook($url, $opt);

        if ($result['ok'] ?? false) {
            echo "Webhook configurado: {$url}\n";
            if (!empty($config['secret_token'])) {
                echo "Secret token: configurado\n";
            }
        } else {
            echo "ERROR: " . ($result['description'] ?? json_encode($result)) . "\n";
        }
        break;

    case 'delete':
        $result = $api->deleteWebhook();
        if ($result['ok'] ?? false) {
            echo "Webhook eliminado.\n";
        } else {
            echo "ERROR: " . ($result['description'] ?? json_encode($result)) . "\n";
        }
        break;

    case 'info':
        echo "=== Webhook Info ===\n";
        $wh = $api->getWebhookInfo();
        if ($wh['ok'] ?? false) {
            $r = $wh['result'];
            echo "URL:              " . ($r['url'] ?: '(no configurado)') . "\n";
            echo "Pendientes:       " . ($r['pending_update_count'] ?? 0) . "\n";
            echo "Ultimo error:     " . ($r['last_error_message'] ?? 'ninguno') . "\n";
            echo "Secret token:     " . (($r['has_custom_certificate'] ?? false) ? 'si' : 'no') . "\n";
            echo "Max connections:  " . ($r['max_connections'] ?? 40) . "\n";
        } else {
            echo json_encode($wh, JSON_PRETTY_PRINT) . "\n";
        }

        echo "\n=== Bot Info ===\n";
        $me = $api->getMe();
        if ($me['ok'] ?? false) {
            $r = $me['result'];
            echo "Username:  @{$r['username']}\n";
            echo "Nombre:    {$r['first_name']}\n";
            echo "ID:        {$r['id']}\n";
        } else {
            echo "ERROR al obtener info del bot.\n";
        }
        break;

    case 'test':
        $me = $api->getMe();
        if ($me['ok'] ?? false) {
            $r = $me['result'];
            echo "Bot conectado: @{$r['username']} ({$r['first_name']})\n";
            echo "ID: {$r['id']}\n";
        } else {
            echo "ERROR: No se pudo conectar. Verifica tu token.\n";
            echo json_encode($me, JSON_PRETTY_PRINT) . "\n";
            exit(1);
        }
        break;

    case 'commands':
        $commands = [
            ['command' => 'start',  'description' => 'Iniciar el bot'],
            ['command' => 'ping',   'description' => 'Test de latencia'],
            ['command' => 'async',  'description' => 'Prueba de tarea asincrona'],
            ['command' => 'imagen', 'description' => 'Generar una imagen'],
            ['command' => 'estado', 'description' => 'Ver estado del bot'],
        ];
        $result = $api->setMyCommands($commands);
        if ($result['ok'] ?? false) {
            echo "Comandos registrados en BotFather:\n";
            foreach ($commands as $cmd) {
                echo "  /{$cmd['command']} - {$cmd['description']}\n";
            }
        } else {
            echo "ERROR: " . ($result['description'] ?? json_encode($result)) . "\n";
        }
        break;

    default:
        echo "TeleBot Setup\n\n";
        echo "Comandos:\n";
        echo "  php setup.php set [URL]    - Configurar webhook\n";
        echo "  php setup.php info         - Ver info del webhook y bot\n";
        echo "  php setup.php delete       - Eliminar webhook\n";
        echo "  php setup.php test         - Probar conexion del bot\n";
        echo "  php setup.php commands     - Registrar comandos en BotFather\n";
}

echo "\n";
