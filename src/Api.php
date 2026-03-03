<?php

namespace TeleBot;

/**
 * Wrapper completo del API de Telegram Bot.
 *
 * Todos los metodos retornan el array de respuesta de Telegram.
 * Para metodos no cubiertos, usa call():
 *
 *   $api->call('sendGame', ['chat_id' => $id, 'game_short_name' => 'test']);
 */
class Api
{
    private string $token;
    private string $base;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->base = "https://api.telegram.org/bot{$token}";
    }

    // ══════════════════════════════════════════════════════════
    //  MENSAJES
    // ══════════════════════════════════════════════════════════

    /**
     * Envia un mensaje de texto.
     *
     *   $api->sendMessage($chatId, "Hola <b>mundo</b>");
     *   $api->sendMessage($chatId, "Hola", ['reply_to_message_id' => $msgId]);
     */
    public function sendMessage(int|string $chat, string $text, array $opt = []): array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chat,
            'text' => $text,
            'parse_mode' => 'HTML',
        ] + $opt);
    }

    /**
     * Envia mensaje con inline keyboard.
     *
     *   $api->sendMessageWithInline($chatId, "Elige:", [
     *       [Kb::inline('A', 'a'), Kb::inline('B', 'b')],
     *   ]);
     */
    public function sendMessageWithInline(int|string $chat, string $text, array $inlineRows, array $opt = []): array
    {
        $opt['reply_markup'] = json_encode(['inline_keyboard' => $inlineRows]);
        return $this->sendMessage($chat, $text, $opt);
    }

    /**
     * Envia mensaje con reply keyboard.
     *
     *   $api->sendMessageWithKeyboard($chatId, "Elige:", [
     *       [['text' => 'A'], ['text' => 'B']],
     *   ]);
     */
    public function sendMessageWithKeyboard(
        int|string $chat,
        string $text,
        array $keyboard,
        bool $resize = true,
        bool $oneTime = false,
        string $placeholder = '',
        array $opt = [],
    ): array {
        $markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
        ];
        if ($placeholder !== '') {
            $markup['input_field_placeholder'] = $placeholder;
        }
        $opt['reply_markup'] = json_encode($markup);
        return $this->sendMessage($chat, $text, $opt);
    }

    /**
     * Reenviar un mensaje.
     */
    public function forwardMessage(int|string $chat, int|string $from, int $msgId): array
    {
        return $this->call('forwardMessage', [
            'chat_id' => $chat,
            'from_chat_id' => $from,
            'message_id' => $msgId,
        ]);
    }

    /**
     * Copiar un mensaje.
     */
    public function copyMessage(int|string $chat, int|string $from, int $msgId, array $opt = []): array
    {
        return $this->call('copyMessage', [
            'chat_id' => $chat,
            'from_chat_id' => $from,
            'message_id' => $msgId,
        ] + $opt);
    }

    /**
     * Editar texto de un mensaje.
     */
    public function editMessageText(int|string $chat, int $msgId, string $text, array $opt = []): array
    {
        return $this->call('editMessageText', [
            'chat_id' => $chat,
            'message_id' => $msgId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ] + $opt);
    }

    /**
     * Editar texto y actualizar inline keyboard.
     */
    public function editMessageTextWithInline(int|string $chat, int $msgId, string $text, array $inlineRows, array $opt = []): array
    {
        $opt['reply_markup'] = json_encode(['inline_keyboard' => $inlineRows]);
        return $this->editMessageText($chat, $msgId, $text, $opt);
    }

    /**
     * Editar caption de un mensaje con media.
     */
    public function editMessageCaption(int|string $chat, int $msgId, string $caption, array $opt = []): array
    {
        return $this->call('editMessageCaption', [
            'chat_id' => $chat,
            'message_id' => $msgId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ] + $opt);
    }

    /**
     * Editar media de un mensaje.
     */
    public function editMessageMedia(int|string $chat, int $msgId, array $media, array $opt = []): array
    {
        return $this->call('editMessageMedia', [
            'chat_id' => $chat,
            'message_id' => $msgId,
            'media' => json_encode($media),
        ] + $opt);
    }

    /**
     * Editar solo el reply_markup de un mensaje.
     */
    public function editMessageReplyMarkup(int|string $chat, int $msgId, array $markup): array
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chat,
            'message_id' => $msgId,
            'reply_markup' => json_encode($markup),
        ]);
    }

    /**
     * Borrar un mensaje.
     */
    public function deleteMessage(int|string $chat, int $msgId): array
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chat,
            'message_id' => $msgId,
        ]);
    }

    /**
     * Borrar multiples mensajes.
     */
    public function deleteMessages(int|string $chat, array $msgIds): array
    {
        return $this->call('deleteMessages', [
            'chat_id' => $chat,
            'message_ids' => json_encode($msgIds),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  MEDIA
    // ══════════════════════════════════════════════════════════

    /**
     * Enviar foto.
     *
     *   $api->sendPhoto($chat, '/path/to/img.jpg', ['caption' => 'Mi foto']);
     *   $api->sendPhoto($chat, 'https://example.com/img.jpg');
     *   $api->sendPhoto($chat, $fileId);
     */
    public function sendPhoto(int|string $chat, string $photo, array $opt = []): array
    {
        return $this->media('sendPhoto', $chat, 'photo', $photo, $opt);
    }

    public function sendDocument(int|string $chat, string $doc, array $opt = []): array
    {
        return $this->media('sendDocument', $chat, 'document', $doc, $opt);
    }

    public function sendVideo(int|string $chat, string $video, array $opt = []): array
    {
        return $this->media('sendVideo', $chat, 'video', $video, $opt);
    }

    public function sendAnimation(int|string $chat, string $gif, array $opt = []): array
    {
        return $this->media('sendAnimation', $chat, 'animation', $gif, $opt);
    }

    public function sendAudio(int|string $chat, string $audio, array $opt = []): array
    {
        return $this->media('sendAudio', $chat, 'audio', $audio, $opt);
    }

    public function sendVoice(int|string $chat, string $voice, array $opt = []): array
    {
        return $this->media('sendVoice', $chat, 'voice', $voice, $opt);
    }

    public function sendVideoNote(int|string $chat, string $note, array $opt = []): array
    {
        return $this->media('sendVideoNote', $chat, 'video_note', $note, $opt);
    }

    public function sendSticker(int|string $chat, string $sticker, array $opt = []): array
    {
        return $this->media('sendSticker', $chat, 'sticker', $sticker, $opt);
    }

    public function sendMediaGroup(int|string $chat, array $media, array $opt = []): array
    {
        return $this->call('sendMediaGroup', [
            'chat_id' => $chat,
            'media' => json_encode($media),
        ] + $opt);
    }

    public function sendLocation(int|string $chat, float $lat, float $lon, array $opt = []): array
    {
        return $this->call('sendLocation', [
            'chat_id' => $chat,
            'latitude' => $lat,
            'longitude' => $lon,
        ] + $opt);
    }

    public function sendVenue(int|string $chat, float $lat, float $lon, string $title, string $address, array $opt = []): array
    {
        return $this->call('sendVenue', [
            'chat_id' => $chat,
            'latitude' => $lat,
            'longitude' => $lon,
            'title' => $title,
            'address' => $address,
        ] + $opt);
    }

    public function sendContact(int|string $chat, string $phone, string $firstName, array $opt = []): array
    {
        return $this->call('sendContact', [
            'chat_id' => $chat,
            'phone_number' => $phone,
            'first_name' => $firstName,
        ] + $opt);
    }

    public function sendDice(int|string $chat, string $emoji = "\xF0\x9F\x8E\xB2", array $opt = []): array
    {
        return $this->call('sendDice', [
            'chat_id' => $chat,
            'emoji' => $emoji,
        ] + $opt);
    }

    public function sendPoll(int|string $chat, string $question, array $options, array $opt = []): array
    {
        $opts = array_map(fn($o) => is_string($o) ? ['text' => $o] : $o, $options);
        return $this->call('sendPoll', [
            'chat_id' => $chat,
            'question' => $question,
            'options' => json_encode($opts),
        ] + $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  CHAT ACTIONS
    // ══════════════════════════════════════════════════════════

    public function sendChatAction(int|string $chat, string $action = 'typing'): array
    {
        return $this->call('sendChatAction', [
            'chat_id' => $chat,
            'action' => $action,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  CALLBACKS
    // ══════════════════════════════════════════════════════════

    public function answerCallbackQuery(string $id, string $text = '', bool $alert = false, array $opt = []): array
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $id,
            'text' => $text,
            'show_alert' => $alert,
        ] + $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  ADMIN / CHAT MANAGEMENT
    // ══════════════════════════════════════════════════════════

    public function banChatMember(int|string $chat, int $userId, array $opt = []): array
    {
        return $this->call('banChatMember', ['chat_id' => $chat, 'user_id' => $userId] + $opt);
    }

    public function unbanChatMember(int|string $chat, int $userId, bool $onlyIfBanned = true): array
    {
        return $this->call('unbanChatMember', [
            'chat_id' => $chat,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned,
        ]);
    }

    public function restrictChatMember(int|string $chat, int $userId, array $perms, array $opt = []): array
    {
        return $this->call('restrictChatMember', [
            'chat_id' => $chat,
            'user_id' => $userId,
            'permissions' => json_encode($perms),
        ] + $opt);
    }

    public function promoteChatMember(int|string $chat, int $userId, array $opt = []): array
    {
        return $this->call('promoteChatMember', ['chat_id' => $chat, 'user_id' => $userId] + $opt);
    }

    public function setChatTitle(int|string $chat, string $title): array
    {
        return $this->call('setChatTitle', ['chat_id' => $chat, 'title' => $title]);
    }

    public function setChatDescription(int|string $chat, string $desc): array
    {
        return $this->call('setChatDescription', ['chat_id' => $chat, 'description' => $desc]);
    }

    public function pinChatMessage(int|string $chat, int $msgId, bool $silent = false): array
    {
        return $this->call('pinChatMessage', [
            'chat_id' => $chat,
            'message_id' => $msgId,
            'disable_notification' => $silent,
        ]);
    }

    public function unpinChatMessage(int|string $chat, ?int $msgId = null): array
    {
        $p = ['chat_id' => $chat];
        if ($msgId !== null) $p['message_id'] = $msgId;
        return $this->call('unpinChatMessage', $p);
    }

    public function unpinAllChatMessages(int|string $chat): array
    {
        return $this->call('unpinAllChatMessages', ['chat_id' => $chat]);
    }

    public function getChat(int|string $chat): array
    {
        return $this->call('getChat', ['chat_id' => $chat]);
    }

    public function getChatMember(int|string $chat, int $userId): array
    {
        return $this->call('getChatMember', ['chat_id' => $chat, 'user_id' => $userId]);
    }

    public function getChatMemberCount(int|string $chat): array
    {
        return $this->call('getChatMemberCount', ['chat_id' => $chat]);
    }

    public function getChatAdministrators(int|string $chat): array
    {
        return $this->call('getChatAdministrators', ['chat_id' => $chat]);
    }

    public function leaveChat(int|string $chat): array
    {
        return $this->call('leaveChat', ['chat_id' => $chat]);
    }

    // ══════════════════════════════════════════════════════════
    //  ARCHIVOS
    // ══════════════════════════════════════════════════════════

    public function getFile(string $fileId): array
    {
        return $this->call('getFile', ['file_id' => $fileId]);
    }

    public function getFileUrl(string $filePath): string
    {
        return "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
    }

    public function downloadFile(string $fileId, string $dest): bool
    {
        $info = $this->getFile($fileId);
        if (!($info['ok'] ?? false)) return false;
        $url = $this->getFileUrl($info['result']['file_path']);
        $data = @file_get_contents($url);
        return $data !== false && file_put_contents($dest, $data) !== false;
    }

    // ══════════════════════════════════════════════════════════
    //  WEBHOOK & BOT INFO
    // ══════════════════════════════════════════════════════════

    public function setWebhook(string $url, array $opt = []): array
    {
        return $this->call('setWebhook', ['url' => $url] + $opt);
    }

    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => $dropPending]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    public function setMyCommands(array $commands, array $opt = []): array
    {
        return $this->call('setMyCommands', ['commands' => json_encode($commands)] + $opt);
    }

    public function deleteMyCommands(array $opt = []): array
    {
        return $this->call('deleteMyCommands', $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  INLINE MODE
    // ══════════════════════════════════════════════════════════

    public function answerInlineQuery(string $id, array $results, array $opt = []): array
    {
        return $this->call('answerInlineQuery', [
            'inline_query_id' => $id,
            'results' => json_encode($results),
        ] + $opt);
    }

    // ══════════════════════════════════════════════════════════
    //  LLAMADA GENERICA (para cualquier metodo del API)
    // ══════════════════════════════════════════════════════════

    /**
     * Llama cualquier metodo del API de Telegram.
     *
     *   $api->call('sendGame', ['chat_id' => $id, 'game_short_name' => 'test']);
     */
    public function call(string $method, array $params = []): array
    {
        foreach (['reply_markup', 'reply_parameters'] as $key) {
            if (isset($params[$key]) && is_array($params[$key])) {
                $params[$key] = json_encode($params[$key]);
            }
        }

        $url = "{$this->base}/{$method}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            error_log("[TeleBot API] cURL error on {$method}: {$err}");
            return ['ok' => false, 'error' => $err];
        }

        $decoded = json_decode($res, true);
        if (!$decoded) {
            error_log("[TeleBot API] Invalid JSON on {$method} (HTTP {$httpCode}): " . substr($res ?: '', 0, 200));
            return ['ok' => false, 'error' => 'Invalid JSON', 'http_code' => $httpCode];
        }

        if (!($decoded['ok'] ?? false)) {
            error_log("[TeleBot API] Error on {$method}: " . ($decoded['description'] ?? json_encode($decoded)));
        }

        return $decoded;
    }

    /**
     * Envia un archivo local via multipart/form-data.
     */
    private function media(string $method, int|string $chat, string $field, string $file, array $opt): array
    {
        $params = ['chat_id' => $chat, 'parse_mode' => 'HTML'] + $opt;

        // Si es archivo local, subir via multipart
        if (file_exists($file)) {
            $params[$field] = new \CURLFile(realpath($file));

            // Codificar reply_markup si viene como array
            if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
                $params['reply_markup'] = json_encode($params['reply_markup']);
            }

            return $this->upload($method, $params);
        }

        // Si es URL o file_id, enviar como JSON
        $params[$field] = $file;
        return $this->call($method, $params);
    }

    /**
     * Upload multipart/form-data (para archivos locales).
     */
    private function upload(string $method, array $params): array
    {
        $ch = curl_init("{$this->base}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err];
        }

        return json_decode($res, true) ?: ['ok' => false, 'error' => 'Invalid JSON'];
    }
}
