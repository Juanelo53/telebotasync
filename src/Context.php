<?php

namespace TeleBot;

/**
 * Contexto del handler - tu herramienta principal para interactuar con el usuario.
 *
 * Todos los metodos reply*() responden al mensaje del usuario (reply_to_message_id).
 * Para enviar sin responder, usa send*().
 *
 * Uso:
 *
 *   $bot->command('/start', function(Context $ctx) {
 *       $ctx->reply("Hola <b>{$ctx->firstName()}</b>!");
 *   });
 *
 *   $bot->command('/menu', function(Context $ctx) {
 *       $ctx->reply("Elige:", inline: [
 *           [Kb::inline('Opcion A', 'a'), Kb::inline('Opcion B', 'b')],
 *           [Kb::url('Web', 'https://example.com')],
 *       ]);
 *   });
 */
class Context
{
    public readonly Update $update;
    public readonly Api $api;
    private Bot $bot;

    public function __construct(Update $update, Api $api, Bot $bot)
    {
        $this->update = $update;
        $this->api = $api;
        $this->bot = $bot;
    }

    /**
     * Builds reply_parameters for replying to the current message.
     * Uses allow_sending_without_reply to avoid errors if the message is deleted.
     */
    private function replyOpt(): array
    {
        $msgId = $this->messageId();
        if (!$msgId) return [];
        return ['reply_parameters' => [
            'message_id' => $msgId,
            'allow_sending_without_reply' => true,
        ]];
    }

    // ══════════════════════════════════════════════════════════
    //  ENVIAR MENSAJES
    // ══════════════════════════════════════════════════════════

    /**
     * Responde al mensaje del usuario (con reply_to).
     *
     *   $ctx->reply("Hola!");
     *   $ctx->reply("Elige:", inline: [[Kb::inline('A', 'a')]]);
     *   $ctx->reply("Opcion:", keyboard: Kb::reply(['A', 'B'], ['C']));
     *   $ctx->reply("Elige:", keyboard: Kb::reply(['Si','No']), oneTime: true);
     */
    public function reply(
        string $text,
        ?array $inline = null,
        ?array $keyboard = null,
        bool $oneTime = false,
        bool $resize = true,
        string $placeholder = '',
        array $extra = [],
    ): array {
        $opt = $this->buildReplyMarkup($inline, $keyboard, $resize, $oneTime, $placeholder, $extra);
        return $this->api->sendMessage($this->chatId(), $text, $opt + $this->replyOpt());
    }

    /**
     * Envia mensaje SIN responder al usuario (sin reply_to).
     *
     *   $ctx->send("Mensaje independiente");
     */
    public function send(
        string $text,
        ?array $inline = null,
        ?array $keyboard = null,
        bool $oneTime = false,
        bool $resize = true,
        string $placeholder = '',
        array $extra = [],
    ): array {
        $opt = $this->buildReplyMarkup($inline, $keyboard, $resize, $oneTime, $placeholder, $extra);
        return $this->api->sendMessage($this->chatId(), $text, $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  MEDIA (con reply_to automatico)
    // ══════════════════════════════════════════════════════════

    /**
     * Responde con foto.
     *
     *   $ctx->replyPhoto('/path/to/img.jpg');
     *   $ctx->replyPhoto('https://example.com/img.jpg', caption: 'Mi foto');
     *   $ctx->replyPhoto($fileId, inline: [[Kb::inline('Like', 'like')]]);
     */
    public function replyPhoto(
        string $photo,
        string $caption = '',
        ?array $inline = null,
        array $extra = [],
    ): array {
        $opt = $this->buildReplyMarkup($inline, extra: $extra);
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendPhoto($this->chatId(), $photo, $opt + $this->replyOpt());
    }

    /**
     * Envia foto sin reply_to.
     */
    public function sendPhoto(
        string $photo,
        string $caption = '',
        ?array $inline = null,
        array $extra = [],
    ): array {
        $opt = $this->buildReplyMarkup($inline, extra: $extra);
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendPhoto($this->chatId(), $photo, $opt);
    }

    /**
     * Responde con documento.
     *
     *   $ctx->replyDocument('/path/to/file.pdf', caption: 'Manual');
     */
    public function replyDocument(string $doc, string $caption = '', array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendDocument($this->chatId(), $doc, $opt);
    }

    public function sendDocument(string $doc, string $caption = '', array $extra = []): array
    {
        $opt = $extra;
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendDocument($this->chatId(), $doc, $opt);
    }

    /**
     * Responde con video.
     */
    public function replyVideo(string $video, string $caption = '', array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendVideo($this->chatId(), $video, $opt);
    }

    public function sendVideo(string $video, string $caption = '', array $extra = []): array
    {
        $opt = $extra;
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendVideo($this->chatId(), $video, $opt);
    }

    /**
     * Responde con audio.
     */
    public function replyAudio(string $audio, string $caption = '', array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendAudio($this->chatId(), $audio, $opt);
    }

    public function sendAudio(string $audio, string $caption = '', array $extra = []): array
    {
        $opt = $extra;
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendAudio($this->chatId(), $audio, $opt);
    }

    /**
     * Responde con nota de voz.
     */
    public function replyVoice(string $voice, string $caption = '', array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendVoice($this->chatId(), $voice, $opt);
    }

    /**
     * Responde con animacion (GIF).
     */
    public function replyAnimation(string $gif, string $caption = '', array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        if ($caption !== '') $opt['caption'] = $caption;
        return $this->api->sendAnimation($this->chatId(), $gif, $opt);
    }

    /**
     * Responde con sticker.
     */
    public function replySticker(string $sticker, array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        return $this->api->sendSticker($this->chatId(), $sticker, $opt);
    }

    /**
     * Responde con ubicacion.
     */
    public function replyLocation(float $lat, float $lon, array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        return $this->api->sendLocation($this->chatId(), $lat, $lon, $opt);
    }

    /**
     * Responde con contacto.
     */
    public function replyContact(string $phone, string $name, array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        return $this->api->sendContact($this->chatId(), $phone, $name, $opt);
    }

    /**
     * Responde con dado/emoji animado.
     */
    public function replyDice(string $emoji = "\xF0\x9F\x8E\xB2", array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        return $this->api->sendDice($this->chatId(), $emoji, $opt);
    }

    /**
     * Responde con encuesta.
     */
    public function replyPoll(string $question, array $options, array $extra = []): array
    {
        $opt = $extra + $this->replyOpt();
        return $this->api->sendPoll($this->chatId(), $question, $options, $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  EDITAR / BORRAR MENSAJES
    // ══════════════════════════════════════════════════════════

    /**
     * Edita el texto de un mensaje.
     *
     *   $ctx->edit($msgId, "Texto actualizado");
     *   $ctx->edit($msgId, "Nuevo texto", inline: [[Kb::inline('OK', 'ok')]]);
     */
    public function edit(int $messageId, string $text, ?array $inline = null, array $extra = []): array
    {
        if ($inline !== null) {
            $extra['reply_markup'] = json_encode(['inline_keyboard' => $inline]);
        }
        return $this->api->editMessageText($this->chatId(), $messageId, $text, $extra);
    }

    /**
     * Edita el caption de un mensaje con media.
     */
    public function editCaption(int $messageId, string $caption, ?array $inline = null, array $extra = []): array
    {
        if ($inline !== null) {
            $extra['reply_markup'] = json_encode(['inline_keyboard' => $inline]);
        }
        return $this->api->editMessageCaption($this->chatId(), $messageId, $caption, $extra);
    }

    /**
     * Edita solo los botones inline de un mensaje.
     */
    public function editButtons(int $messageId, array $inline): array
    {
        return $this->api->editMessageReplyMarkup(
            $this->chatId(),
            $messageId,
            ['inline_keyboard' => $inline],
        );
    }

    /**
     * Borra un mensaje.
     */
    public function delete(int $messageId): array
    {
        return $this->api->deleteMessage($this->chatId(), $messageId);
    }

    /**
     * Borra multiples mensajes.
     */
    public function deleteMany(array $messageIds): array
    {
        return $this->api->deleteMessages($this->chatId(), $messageIds);
    }

    /**
     * Borra el mensaje del usuario (el que disparo este handler).
     */
    public function deleteUserMessage(): array
    {
        $id = $this->messageId();
        if ($id === null) return ['ok' => false, 'error' => 'No message to delete'];
        return $this->delete($id);
    }

    // ══════════════════════════════════════════════════════════
    //  CALLBACKS
    // ══════════════════════════════════════════════════════════

    /**
     * Responde a un callback query.
     *
     *   $ctx->answerCallback();                    // Solo cerrar popup
     *   $ctx->answerCallback("Listo!");            // Toast
     *   $ctx->answerCallback("Error!", alert: true); // Alert modal
     */
    public function answerCallback(string $text = '', bool $alert = false): array
    {
        return $this->api->answerCallbackQuery($this->update->getCallbackId(), $text, $alert);
    }

    // ══════════════════════════════════════════════════════════
    //  CHAT ACTIONS
    // ══════════════════════════════════════════════════════════

    public function typing(): void
    {
        $this->api->sendChatAction($this->chatId(), 'typing');
    }

    public function uploadingPhoto(): void
    {
        $this->api->sendChatAction($this->chatId(), 'upload_photo');
    }

    public function uploadingVideo(): void
    {
        $this->api->sendChatAction($this->chatId(), 'upload_video');
    }

    public function uploadingDocument(): void
    {
        $this->api->sendChatAction($this->chatId(), 'upload_document');
    }

    public function recordingVoice(): void
    {
        $this->api->sendChatAction($this->chatId(), 'record_voice');
    }

    public function recordingVideo(): void
    {
        $this->api->sendChatAction($this->chatId(), 'record_video');
    }

    // ══════════════════════════════════════════════════════════
    //  FORWARD / COPY
    // ══════════════════════════════════════════════════════════

    /**
     * Reenvia el mensaje del usuario a otro chat.
     */
    public function forward(int|string $toChatId): array
    {
        return $this->api->forwardMessage($toChatId, $this->chatId(), $this->messageId());
    }

    /**
     * Copia el mensaje del usuario a otro chat.
     */
    public function copyTo(int|string $toChatId, array $opt = []): array
    {
        return $this->api->copyMessage($toChatId, $this->chatId(), $this->messageId(), $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  ASYNC (tareas pesadas en background)
    // ══════════════════════════════════════════════════════════

    /**
     * Ejecuta una tarea en background (no bloquea).
     *
     *   $ctx->async(function(Context $ctx) {
     *       sleep(30); // trabajo pesado
     *       $ctx->reply("Listo!");
     *   }, "Procesando...");
     */
    public function async(callable $task, ?string $loadingMessage = null): void
    {
        $this->bot->dispatchAsync($this, $task, $loadingMessage);
    }

    /**
     * Ejecuta una tarea async y mantiene "typing..." activo.
     *
     *   $ctx->asyncWithTyping(function(Context $ctx) {
     *       sleep(10);
     *       $ctx->reply("Listo!");
     *   }, "Generando...");
     */
    public function asyncWithTyping(callable $task, ?string $loadingMessage = null): void
    {
        $wrapped = function (Context $ctx) use ($task) {
            $typingActive = true;

            // Mandar typing periodicamente en un loop
            $lastTyping = 0;
            $typingCallback = function () use ($ctx, &$lastTyping, &$typingActive) {
                $now = time();
                if ($typingActive && $now - $lastTyping >= 4) {
                    $ctx->typing();
                    $lastTyping = $now;
                }
            };

            // Enviar typing inicial
            $ctx->typing();
            $lastTyping = time();

            // Ejecutar tarea
            // Si pcntl esta disponible, hacer typing en paralelo
            if (function_exists('pcntl_fork')) {
                $typingPid = pcntl_fork();
                if ($typingPid === 0) {
                    while (true) {
                        $ctx->typing();
                        sleep(4);
                    }
                    exit(0);
                }

                try {
                    $task($ctx);
                } finally {
                    $typingActive = false;
                    if ($typingPid > 0) {
                        posix_kill($typingPid, SIGTERM);
                        pcntl_waitpid($typingPid, $s);
                    }
                }
            } else {
                // Sin pcntl, simplemente ejecutar la tarea
                // El typing se envia solo al inicio
                $task($ctx);
            }
        };

        $this->async($wrapped, $loadingMessage);
    }

    // ══════════════════════════════════════════════════════════
    //  DATOS DEL UPDATE (atajos rapidos)
    // ══════════════════════════════════════════════════════════

    public function chatId(): int|string
    {
        return $this->update->getChatId();
    }

    public function userId(): ?int
    {
        return $this->update->getUserId();
    }

    public function messageId(): ?int
    {
        return $this->update->getMessageId();
    }

    public function text(): string
    {
        return $this->update->getText();
    }

    public function caption(): string
    {
        return $this->update->getCaption();
    }

    /** Argumentos del comando como string */
    public function args(): string
    {
        return $this->update->getCommandArgs();
    }

    /** Argumentos del comando como array */
    public function argsArray(): array
    {
        return $this->update->getArgs();
    }

    public function firstName(): string
    {
        return $this->update->getFirstName();
    }

    public function lastName(): string
    {
        return $this->update->getLastName();
    }

    public function fullName(): string
    {
        return $this->update->getFullName();
    }

    public function username(): string
    {
        return $this->update->getUsername();
    }

    public function callbackData(): string
    {
        return $this->update->getCallbackData();
    }

    public function isGroup(): bool
    {
        return $this->update->isGroup();
    }

    public function isPrivate(): bool
    {
        return $this->update->isPrivate();
    }

    /** Tipo de media: 'photo', 'document', 'video', etc */
    public function mediaType(): ?string
    {
        return $this->update->getMediaType();
    }

    // ══════════════════════════════════════════════════════════
    //  HELPERS INTERNOS
    // ══════════════════════════════════════════════════════════

    /**
     * Construye las opciones de reply_markup a partir de inline/keyboard.
     */
    private function buildReplyMarkup(
        ?array $inline = null,
        ?array $keyboard = null,
        bool $resize = true,
        bool $oneTime = false,
        string $placeholder = '',
        array $extra = [],
    ): array {
        if ($inline !== null) {
            $extra['reply_markup'] = json_encode(['inline_keyboard' => $inline]);
        } elseif ($keyboard !== null) {
            $markup = [
                'keyboard' => $keyboard,
                'resize_keyboard' => $resize,
                'one_time_keyboard' => $oneTime,
            ];
            if ($placeholder !== '') {
                $markup['input_field_placeholder'] = $placeholder;
            }
            $extra['reply_markup'] = json_encode($markup);
        }
        return $extra;
    }
}
