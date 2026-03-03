<?php

namespace TeleBot;

/**
 * Representa un Update de Telegram.
 *
 * Parsea el JSON del webhook y da acceso facil a todos los datos:
 * mensaje, usuario, chat, comandos, media, callbacks, etc.
 */
class Update
{
    private array $raw;

    /** Prefijos reconocidos como comando */
    private array $commandPrefixes = ['/', '.', '!', '@', '$', '#'];

    public function __construct(array $data, array $commandPrefixes = [])
    {
        $this->raw = $data;
        if (!empty($commandPrefixes)) {
            $this->commandPrefixes = $commandPrefixes;
        }
    }

    public static function fromWebhook(array $commandPrefixes = []): ?self
    {
        $input = file_get_contents('php://input');
        if (!$input) return null;
        $data = json_decode($input, true);
        if (!$data) return null;
        return new self($data, $commandPrefixes);
    }

    public static function fromArray(array $data, array $commandPrefixes = []): self
    {
        return new self($data, $commandPrefixes);
    }

    // ══════════════════════════════════════════════════════════
    //  TIPO DE UPDATE
    // ══════════════════════════════════════════════════════════

    public function getType(): string
    {
        if (isset($this->raw['message'])) return 'message';
        if (isset($this->raw['callback_query'])) return 'callback_query';
        if (isset($this->raw['edited_message'])) return 'edited_message';
        if (isset($this->raw['inline_query'])) return 'inline_query';
        if (isset($this->raw['chosen_inline_result'])) return 'chosen_inline_result';
        if (isset($this->raw['channel_post'])) return 'channel_post';
        if (isset($this->raw['edited_channel_post'])) return 'edited_channel_post';
        if (isset($this->raw['my_chat_member'])) return 'my_chat_member';
        if (isset($this->raw['chat_member'])) return 'chat_member';
        if (isset($this->raw['chat_join_request'])) return 'chat_join_request';
        return 'unknown';
    }

    public function isMessage(): bool
    {
        return $this->getType() === 'message';
    }

    public function isCallback(): bool
    {
        return $this->getType() === 'callback_query';
    }

    public function isEditedMessage(): bool
    {
        return $this->getType() === 'edited_message';
    }

    public function isInlineQuery(): bool
    {
        return $this->getType() === 'inline_query';
    }

    public function isChannelPost(): bool
    {
        return $this->getType() === 'channel_post';
    }

    // ══════════════════════════════════════════════════════════
    //  MENSAJE
    // ══════════════════════════════════════════════════════════

    public function getMessage(): ?array
    {
        return $this->raw['message']
            ?? $this->raw['callback_query']['message']
            ?? $this->raw['edited_message']
            ?? $this->raw['channel_post']
            ?? $this->raw['edited_channel_post']
            ?? null;
    }

    public function getText(): string
    {
        return $this->getMessage()['text']
            ?? $this->getMessage()['caption']
            ?? '';
    }

    public function getCaption(): string
    {
        return $this->getMessage()['caption'] ?? '';
    }

    public function getChatId(): int|string|null
    {
        return $this->getMessage()['chat']['id'] ?? null;
    }

    public function getMessageId(): ?int
    {
        return $this->getMessage()['message_id'] ?? null;
    }

    public function getDate(): ?int
    {
        return $this->getMessage()['date'] ?? null;
    }

    // ══════════════════════════════════════════════════════════
    //  USUARIO
    // ══════════════════════════════════════════════════════════

    public function getFrom(): ?array
    {
        if ($this->isCallback()) {
            return $this->raw['callback_query']['from'] ?? null;
        }
        if ($this->isInlineQuery()) {
            return $this->raw['inline_query']['from'] ?? null;
        }
        return $this->getMessage()['from'] ?? null;
    }

    public function getUserId(): ?int
    {
        return $this->getFrom()['id'] ?? null;
    }

    public function getUsername(): string
    {
        return $this->getFrom()['username'] ?? '';
    }

    public function getFirstName(): string
    {
        return $this->getFrom()['first_name'] ?? '';
    }

    public function getLastName(): string
    {
        return $this->getFrom()['last_name'] ?? '';
    }

    public function getFullName(): string
    {
        $first = $this->getFirstName();
        $last = $this->getLastName();
        return $last ? "{$first} {$last}" : $first;
    }

    public function getLanguageCode(): string
    {
        return $this->getFrom()['language_code'] ?? '';
    }

    public function isBot(): bool
    {
        return $this->getFrom()['is_bot'] ?? false;
    }

    // ══════════════════════════════════════════════════════════
    //  CHAT
    // ══════════════════════════════════════════════════════════

    public function getChat(): ?array
    {
        return $this->getMessage()['chat'] ?? null;
    }

    public function getChatType(): string
    {
        return $this->getChat()['type'] ?? 'private';
    }

    public function getChatTitle(): string
    {
        return $this->getChat()['title'] ?? '';
    }

    public function isGroup(): bool
    {
        return in_array($this->getChatType(), ['group', 'supergroup']);
    }

    public function isPrivate(): bool
    {
        return $this->getChatType() === 'private';
    }

    public function isChannel(): bool
    {
        return $this->getChatType() === 'channel';
    }

    // ══════════════════════════════════════════════════════════
    //  COMANDOS (multi-prefijo: / . ! @ $ #)
    // ══════════════════════════════════════════════════════════

    /**
     * Verifica si el texto es un comando.
     * Reconoce: /start, .help, !ban, @info, $precio, #tag
     */
    public function isCommand(): bool
    {
        $text = $this->getText();
        if ($text === '') return false;
        return in_array($text[0], $this->commandPrefixes);
    }

    /**
     * Retorna el comando con su prefijo, en minusculas.
     * Ejemplo: "/start", ".help", "!ban"
     * Si el usuario envio "/start@MiBot", retorna "/start".
     */
    public function getCommand(): string
    {
        if (!$this->isCommand()) return '';
        $parts = explode(' ', $this->getText(), 2);
        $cmd = explode('@', $parts[0])[0]; // quita @botname
        return strtolower($cmd);
    }

    /**
     * Retorna el prefijo del comando.
     * Ejemplo: para "/start" retorna "/"
     */
    public function getCommandPrefix(): string
    {
        $cmd = $this->getCommand();
        return $cmd !== '' ? $cmd[0] : '';
    }

    /**
     * Retorna el nombre del comando sin prefijo.
     * Ejemplo: para "/start" retorna "start"
     */
    public function getCommandName(): string
    {
        $cmd = $this->getCommand();
        return $cmd !== '' ? substr($cmd, 1) : '';
    }

    /**
     * Retorna todo lo que va despues del comando como string.
     * Ejemplo: "/imagen un gato volador" -> "un gato volador"
     */
    public function getCommandArgs(): string
    {
        if (!$this->isCommand()) return '';
        $parts = explode(' ', $this->getText(), 2);
        return $parts[1] ?? '';
    }

    /**
     * Retorna los argumentos como array separados por espacio.
     * Ejemplo: "/cmd arg1 arg2" -> ['arg1', 'arg2']
     */
    public function getArgs(): array
    {
        $args = $this->getCommandArgs();
        return $args !== '' ? preg_split('/\s+/', $args) : [];
    }

    // ══════════════════════════════════════════════════════════
    //  CALLBACK QUERY
    // ══════════════════════════════════════════════════════════

    public function getCallbackData(): string
    {
        return $this->raw['callback_query']['data'] ?? '';
    }

    public function getCallbackId(): string
    {
        return $this->raw['callback_query']['id'] ?? '';
    }

    // ══════════════════════════════════════════════════════════
    //  INLINE QUERY
    // ══════════════════════════════════════════════════════════

    public function getInlineQueryId(): string
    {
        return $this->raw['inline_query']['id'] ?? '';
    }

    public function getInlineQueryText(): string
    {
        return $this->raw['inline_query']['query'] ?? '';
    }

    // ══════════════════════════════════════════════════════════
    //  MEDIA
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna el tipo de media del mensaje.
     * Posibles: 'photo', 'document', 'video', 'audio', 'voice',
     *           'video_note', 'sticker', 'animation', 'location',
     *           'contact', 'venue', 'poll', 'dice', null
     */
    public function getMediaType(): ?string
    {
        $msg = $this->getMessage();
        if (!$msg) return null;

        $types = [
            'photo', 'document', 'video', 'audio', 'voice',
            'video_note', 'sticker', 'animation', 'location',
            'contact', 'venue', 'poll', 'dice',
        ];

        foreach ($types as $type) {
            if (isset($msg[$type])) return $type;
        }

        return null;
    }

    public function hasMedia(): bool
    {
        return $this->getMediaType() !== null;
    }

    public function hasPhoto(): bool
    {
        return isset($this->getMessage()['photo']);
    }

    public function getPhotoFileId(): ?string
    {
        $photos = $this->getMessage()['photo'] ?? [];
        if (empty($photos)) return null;
        return end($photos)['file_id'];
    }

    /**
     * Retorna todas las resoluciones de la foto.
     */
    public function getPhotoSizes(): array
    {
        return $this->getMessage()['photo'] ?? [];
    }

    public function hasDocument(): bool
    {
        return isset($this->getMessage()['document']);
    }

    public function getDocumentFileId(): ?string
    {
        return $this->getMessage()['document']['file_id'] ?? null;
    }

    public function getDocumentName(): string
    {
        return $this->getMessage()['document']['file_name'] ?? '';
    }

    public function getDocumentMimeType(): string
    {
        return $this->getMessage()['document']['mime_type'] ?? '';
    }

    public function hasVideo(): bool
    {
        return isset($this->getMessage()['video']);
    }

    public function getVideoFileId(): ?string
    {
        return $this->getMessage()['video']['file_id'] ?? null;
    }

    public function hasAudio(): bool
    {
        return isset($this->getMessage()['audio']);
    }

    public function getAudioFileId(): ?string
    {
        return $this->getMessage()['audio']['file_id'] ?? null;
    }

    public function hasVoice(): bool
    {
        return isset($this->getMessage()['voice']);
    }

    public function getVoiceFileId(): ?string
    {
        return $this->getMessage()['voice']['file_id'] ?? null;
    }

    public function hasSticker(): bool
    {
        return isset($this->getMessage()['sticker']);
    }

    public function getStickerFileId(): ?string
    {
        return $this->getMessage()['sticker']['file_id'] ?? null;
    }

    public function hasAnimation(): bool
    {
        return isset($this->getMessage()['animation']);
    }

    public function hasLocation(): bool
    {
        return isset($this->getMessage()['location']);
    }

    public function getLocation(): ?array
    {
        return $this->getMessage()['location'] ?? null;
    }

    public function hasContact(): bool
    {
        return isset($this->getMessage()['contact']);
    }

    public function getContact(): ?array
    {
        return $this->getMessage()['contact'] ?? null;
    }

    // ══════════════════════════════════════════════════════════
    //  ENTITIES
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna las entidades del mensaje (mentions, links, bold, etc).
     */
    public function getEntities(): array
    {
        return $this->getMessage()['entities']
            ?? $this->getMessage()['caption_entities']
            ?? [];
    }

    /**
     * Extrae mentions (@usuario) del mensaje.
     */
    public function getMentions(): array
    {
        $mentions = [];
        foreach ($this->getEntities() as $entity) {
            if ($entity['type'] === 'mention') {
                $mentions[] = substr($this->getText(), $entity['offset'], $entity['length']);
            }
        }
        return $mentions;
    }

    /**
     * Extrae hashtags del mensaje.
     */
    public function getHashtags(): array
    {
        $tags = [];
        foreach ($this->getEntities() as $entity) {
            if ($entity['type'] === 'hashtag') {
                $tags[] = substr($this->getText(), $entity['offset'], $entity['length']);
            }
        }
        return $tags;
    }

    /**
     * Extrae URLs del mensaje.
     */
    public function getUrls(): array
    {
        $urls = [];
        foreach ($this->getEntities() as $entity) {
            if ($entity['type'] === 'url') {
                $urls[] = substr($this->getText(), $entity['offset'], $entity['length']);
            } elseif ($entity['type'] === 'text_link') {
                $urls[] = $entity['url'];
            }
        }
        return $urls;
    }

    // ══════════════════════════════════════════════════════════
    //  REPLY
    // ══════════════════════════════════════════════════════════

    public function hasReplyTo(): bool
    {
        return isset($this->getMessage()['reply_to_message']);
    }

    public function getReplyToMessage(): ?array
    {
        return $this->getMessage()['reply_to_message'] ?? null;
    }

    public function getReplyToMessageId(): ?int
    {
        return $this->getReplyToMessage()['message_id'] ?? null;
    }

    // ══════════════════════════════════════════════════════════
    //  FORWARD
    // ══════════════════════════════════════════════════════════

    public function isForwarded(): bool
    {
        $msg = $this->getMessage();
        return isset($msg['forward_from']) || isset($msg['forward_from_chat'])
            || isset($msg['forward_origin']);
    }

    // ══════════════════════════════════════════════════════════
    //  RAW ACCESS
    // ══════════════════════════════════════════════════════════

    public function getUpdateId(): int
    {
        return $this->raw['update_id'] ?? 0;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Acceso con dot notation.
     *
     *   $update->get('message.from.id')
     *   $update->get('callback_query.data')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $arr = $this->raw;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($arr) || !array_key_exists($segment, $arr)) {
                return $default;
            }
            $arr = $arr[$segment];
        }
        return $arr;
    }
}
