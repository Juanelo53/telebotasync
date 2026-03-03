# TeleBot Async

Framework minimalista y **verdaderamente asíncrono** para bots de Telegram en PHP.

Cada tarea pesada corre en un proceso CLI independiente. El webhook se libera de inmediato. El usuario puede enviar 100 comandos y todos se procesan en paralelo sin bloquearse.

## Requisitos

- PHP 8.1+
- cURL extension
- proc_open habilitado
- pcntl extension (para typing loop en workers)

## Instalación

```bash
git clone https://github.com/Juanelo53/telebotasync.git
cd telebotasync
cp config.example.php config.php
```

Editar `config.php` con tu token de [@BotFather](https://t.me/BotFather).

### Configurar webhook

```bash
php setup.php set
php setup.php test
php setup.php info
php setup.php commands
```

## Estructura

```
telebot/
├── webhook.php          # Punto de entrada (webhook de Telegram)
├── config.php           # Configuración (no se commitea)
├── config.example.php   # Plantilla de configuración
├── autoload.php         # PSR-4 autoloader
├── setup.php            # CLI para configurar webhook
├── src/
│   ├── Bot.php          # Motor principal + async engine
│   ├── Context.php      # API fluida para handlers
│   ├── Api.php          # Wrapper del API de Telegram
│   ├── Update.php       # Parser de updates
│   ├── Keyboard.php     # Builder de teclados
│   └── Helpers.php      # Utilidades (escape, HTTP, formato)
├── examples/
│   └── full_bot.php     # Ejemplo completo
├── logs/                # Logs del bot
└── storage/             # Tareas async en progreso
```

## Inicio Rápido

```php
<?php
require_once __DIR__ . '/autoload.php';

$config = require __DIR__ . '/config.php';

$bot = new TeleBot\Bot(
    token:       $config['token'],
    secretToken: $config['secret_token'] ?? null,
);

use TeleBot\Context as Ctx;
use TeleBot\Keyboard as Kb;
use TeleBot\Helpers;

$bot->command('/start', function (Ctx $ctx) {
    $name = Helpers::escape($ctx->firstName());
    $ctx->reply("Hola <b>{$name}</b>!");
});

$bot->command('/ping', function (Ctx $ctx) {
    $ctx->reply('Pong!');
});

$bot->run();
```

---

## Cómo Funciona el Async

```
Usuario envía /tarea_pesada
        │
        ▼
┌──────────────────────────┐
│  Webhook (PHP-FPM)       │
│  1. Recibe update        │
│  2. Responde 200 a TG    │◄── Telegram ya no espera
│  3. Envía "Procesando…"  │
│  4. Guarda update en JSON│
│  5. proc_open() lanza    │
│     worker CLI ─────────────┐
│  6. FPM worker LIBRE     │  │
└──────────────────────────┘  │
                               │
        Usuario envía /ping    │
        │                      ▼
        ▼             ┌──────────────────┐
┌──────────────┐      │  Worker CLI      │
│ Otro FPM     │      │  (background)    │
│ worker       │      │  pcntl_fork()    │
│ → "Pong!"   │      │  ├─ typing loop  │
│ → LIBRE      │      │  └─ tarea pesada │
└──────────────┘      │     reply()      │
                       └──────────────────┘
```

- Comandos normales (`reply`, `send`) son síncronos y responden inmediato.
- Comandos que usan `async()` o `asyncWithTyping()` lanzan un proceso CLI en background.
- El usuario puede seguir enviando comandos sin esperar.

---

## Comandos

```php
// Comando simple
$bot->command('/start', function (Ctx $ctx) {
    $ctx->reply("Hola!");
});

// Múltiples prefijos: /, ., !, @, $, #
$bot->command('/help', fn(Ctx $ctx) => $ctx->reply("Ayuda"));
$bot->command('.help', fn(Ctx $ctx) => $ctx->reply("Ayuda")); // también funciona
$bot->command('!ban',  fn(Ctx $ctx) => $ctx->reply("Baneado"));

// Múltiples aliases al mismo handler
$bot->commands(['/help', '/ayuda', '.help'], function (Ctx $ctx) {
    $ctx->reply("Ayuda!");
});

// Comando con argumentos
$bot->command('/echo', function (Ctx $ctx) {
    $text = $ctx->args();           // "hola mundo"
    $parts = $ctx->argsArray();     // ["hola", "mundo"]
    $ctx->reply(Helpers::escape($text));
});
```

## Callbacks (Botones Inline)

```php
// Callback exacto
$bot->callback('confirm', function (Ctx $ctx) {
    $ctx->answerCallback("Confirmado!");
});

// Callback por prefijo
$bot->callbackPrefix('item_', function (Ctx $ctx) {
    $id = str_replace('item_', '', $ctx->callbackData());
    $ctx->answerCallback("Item: {$id}");
});
```

## Hears (Patrones de Texto)

```php
// Texto exacto (case-insensitive)
$bot->hears('hola', function (Ctx $ctx) {
    $ctx->reply("Que onda!");
});

// Regex
$bot->hears('/precio|costo|vale/i', function (Ctx $ctx) {
    $ctx->reply("$100 USD");
});
```

## Eventos de Media

```php
$bot->on('photo',    fn(Ctx $ctx) => $ctx->reply("Linda foto!"));
$bot->on('document', fn(Ctx $ctx) => $ctx->reply("Archivo recibido"));
$bot->on('voice',    fn(Ctx $ctx) => $ctx->reply("Nota de voz!"));
$bot->on('sticker',  fn(Ctx $ctx) => $ctx->reply("Buen sticker!"));
$bot->on('location', fn(Ctx $ctx) => $ctx->reply("Ubicación recibida"));
$bot->on('video',    fn(Ctx $ctx) => $ctx->reply("Video recibido"));
$bot->on('text',     fn(Ctx $ctx) => $ctx->reply("Texto libre"));
```

## Fallback

```php
$bot->fallback(function (Ctx $ctx) {
    $ctx->reply("No entendí. Usa /help");
});
```

## Middleware

```php
// Log de cada mensaje
$bot->use(function (Ctx $ctx, callable $next) {
    error_log("[BOT] {$ctx->username()}: {$ctx->text()}");
    $next($ctx);
});

// Solo admins
$bot->use(function (Ctx $ctx, callable $next) {
    $admins = [123456789];
    if (in_array($ctx->userId(), $admins)) {
        $next($ctx);
    } else {
        $ctx->reply("No autorizado.");
    }
});
```

---

## Context (`$ctx`) - Referencia Completa

### Enviar Mensajes

```php
$ctx->reply("Hola!");                  // Con reply_to
$ctx->send("Sin reply_to");           // Sin reply_to

// Con botones inline
$ctx->reply("Elige:", inline: [
    Kb::row(Kb::inline('Sí', 'yes'), Kb::inline('No', 'no')),
    Kb::row(Kb::url('Web', 'https://example.com')),
]);

// Con reply keyboard
$ctx->reply("Opción:", keyboard: Kb::reply(
    ['Opción A', 'Opción B'],
    ['Opción C'],
), oneTime: true, placeholder: 'Selecciona...');
```

### Media

```php
$ctx->replyPhoto('/path/to/img.jpg', caption: 'Mi foto');
$ctx->replyDocument('/path/to/file.pdf', caption: 'Manual');
$ctx->replyVideo('/path/to/video.mp4');
$ctx->replyAudio('/path/to/audio.mp3');
$ctx->replyVoice('/path/to/voice.ogg');
$ctx->replyAnimation('/path/to/gif.gif');
$ctx->replySticker($stickerFileId);
$ctx->replyLocation(19.4326, -99.1332);
$ctx->replyContact('+5215512345678', 'Juan');
$ctx->replyDice();
$ctx->replyPoll("¿Mejor lenguaje?", ['PHP', 'Python', 'Go']);
```

Todos los `reply*()` responden al mensaje del usuario. Para enviar sin reply: `sendPhoto()`, `sendDocument()`, `sendVideo()`, etc.

### Editar y Borrar

```php
$result = $ctx->reply("Cargando...");
$msgId = $result['result']['message_id'];

$ctx->edit($msgId, "Listo!");
$ctx->edit($msgId, "Con botones", inline: [...]);
$ctx->editCaption($msgId, "Nuevo caption");
$ctx->editButtons($msgId, $newInlineRows);
$ctx->delete($msgId);
$ctx->deleteMany([$id1, $id2, $id3]);
$ctx->deleteUserMessage();             // Borra el mensaje del usuario
```

### Callbacks

```php
$ctx->answerCallback();                      // Cerrar popup
$ctx->answerCallback("Listo!");              // Toast
$ctx->answerCallback("Error!", alert: true); // Alert modal
```

### Chat Actions

```php
$ctx->typing();
$ctx->uploadingPhoto();
$ctx->uploadingVideo();
$ctx->uploadingDocument();
$ctx->recordingVoice();
$ctx->recordingVideo();
```

### Forward y Copy

```php
$ctx->forward($otherChatId);
$ctx->copyTo($otherChatId);
```

### Datos del Update

```php
$ctx->chatId();        // ID del chat
$ctx->userId();        // ID del usuario
$ctx->messageId();     // ID del mensaje
$ctx->text();          // Texto del mensaje
$ctx->caption();       // Caption de media
$ctx->args();          // Argumentos del comando (string)
$ctx->argsArray();     // Argumentos del comando (array)
$ctx->firstName();     // Nombre del usuario
$ctx->lastName();      // Apellido
$ctx->fullName();      // Nombre completo
$ctx->username();      // @username
$ctx->callbackData();  // Datos del callback
$ctx->isGroup();       // ¿Es grupo?
$ctx->isPrivate();     // ¿Es chat privado?
$ctx->mediaType();     // 'photo', 'document', etc.
```

### Async

```php
// Tarea en background (sin typing)
$ctx->async(function (Ctx $ctx) {
    sleep(30);
    $ctx->reply("Listo!");
}, "Procesando...");

// Tarea en background CON typing activo
$ctx->asyncWithTyping(function (Ctx $ctx) {
    sleep(30);
    $ctx->reply("Listo!");
}, "Procesando...");
```

---

## Keyboard Builder (`Kb`)

```php
use TeleBot\Keyboard as Kb;
```

### Botones Inline

```php
Kb::inline('Texto', 'callback_data')   // Botón con callback
Kb::url('Web', 'https://...')          // Botón con URL
Kb::webApp('App', 'https://...')       // Botón WebApp
Kb::pay('Pagar $10')                   // Botón de pago
Kb::switchInline('Buscar', 'query')    // Switch inline query
Kb::switchHere('Buscar aquí', 'q')     // Switch inline en mismo chat
Kb::login('Login', 'https://...')      // Botón de login
```

### Layout

```php
// Fila manual
Kb::row(Kb::inline('A', 'a'), Kb::inline('B', 'b'))

// Grid automático
Kb::grid([btn1, btn2, btn3, btn4], cols: 2)
// Resultado: [[btn1, btn2], [btn3, btn4]]

// Botón solo en su fila
Kb::single(Kb::inline('Confirmar', 'ok'))
```

### Reply Keyboard

```php
// Teclado personalizado
Kb::reply(['Opción 1', 'Opción 2'], ['Opción 3'])

// Botones especiales
Kb::requestContact('Enviar contacto')
Kb::requestLocation('Enviar ubicación')
Kb::requestPoll('Crear encuesta')

// Quitar teclado
Kb::remove()

// Forzar respuesta
Kb::forceReply('Escribe aquí...')
```

---

## Helpers

```php
use TeleBot\Helpers;

// Escape HTML (SIEMPRE usar con input del usuario)
Helpers::escape($userInput)

// Formato HTML
Helpers::bold("texto")         // <b>texto</b>
Helpers::italic("texto")      // <i>texto</i>
Helpers::code("texto")        // <code>texto</code>
Helpers::pre("código", "php") // <pre class="language-php">código</pre>
Helpers::link("Google", "https://google.com")
Helpers::mention("Juan", $userId)

// HTTP
Helpers::httpGet($url, $headers)
Helpers::httpPostJson($url, $data, $headers)
Helpers::downloadUrl($url, $destPath)

// Utilidades
Helpers::shortId(8)            // "a3f9b2c1"
Helpers::formatBytes(1048576)  // "1 MB"
Helpers::formatDuration(3661)  // "1h 1m"
```

---

## Setup CLI

```bash
php setup.php set              # Configurar webhook
php setup.php set <URL>        # Webhook con URL personalizada
php setup.php info             # Ver info del webhook y bot
php setup.php delete           # Eliminar webhook
php setup.php test             # Probar conexión
php setup.php commands         # Registrar comandos en BotFather
```

## Polling (desarrollo local)

```php
// En un script separado:
$bot->poll();   // Bloquea y procesa updates en loop
```

> **No usar polling en producción.** Solo para desarrollo local.

## Configuración

```php
// config.php
return [
    'token'            => getenv('TELEBOT_TOKEN') ?: 'TU_TOKEN_AQUI',
    'secret_token'     => getenv('TELEBOT_SECRET') ?: 'un_secret_aleatorio',
    'webhook_url'      => 'https://tudominio.com/telebot/webhook.php',
    'command_prefixes' => ['/', '.', '!', '@', '$', '#'],
    'max_async_tasks'  => 10,
    'debug'            => false,
    'admin_ids'        => [],
];
```

Variables de entorno soportadas:

| Variable | Descripción |
|---|---|
| `TELEBOT_TOKEN` | Token del bot |
| `TELEBOT_SECRET` | Secret token para webhook |

## Seguridad

- El webhook valida `X-Telegram-Bot-Api-Secret-Token` en cada request.
- `.htaccess` bloquea acceso directo a `config.php`, `autoload.php`, `setup.php`, `src/`, `logs/`, `storage/`.
- Siempre usar `Helpers::escape()` al insertar input del usuario en mensajes HTML.

## Licencia

MIT
