<?php

return [
    // Token del bot (@BotFather)
    // Puedes usar variable de entorno: export TELEBOT_TOKEN="tu_token"
    'token' => getenv('TELEBOT_TOKEN') ?: 'TU_TOKEN_AQUI',

    // Secret token para validar que los webhooks vienen de Telegram
    'secret_token' => getenv('TELEBOT_SECRET') ?: 'cambia_esto_' . md5('TU_TOKEN_AQUI'),

    // URL del webhook
    'webhook_url' => 'https://tudominio.com/telebot/webhook.php',

    // Prefijos de comando permitidos (ademas de /)
    'command_prefixes' => ['/', '.', '!', '@', '$', '#'],

    // Maximo de tareas async simultaneas
    'max_async_tasks' => 10,

    // Debug mode (mas logs)
    'debug' => false,

    // IDs de admins (opcional)
    'admin_ids' => [],
];
