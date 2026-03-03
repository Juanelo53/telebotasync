<?php

namespace TeleBot;

/**
 * Builder de teclados para Telegram.
 *
 * Uso rapido:
 *
 *   use TeleBot\Keyboard as Kb;
 *
 *   // Botones inline
 *   $ctx->reply("Elige:", inline: [
 *       Kb::row(Kb::inline('Si', 'yes'), Kb::inline('No', 'no')),
 *       Kb::row(Kb::url('Web', 'https://example.com')),
 *   ]);
 *
 *   // Grid automatico (2 columnas)
 *   $ctx->reply("Menu:", inline: Kb::grid([
 *       Kb::inline('A', 'a'), Kb::inline('B', 'b'),
 *       Kb::inline('C', 'c'), Kb::inline('D', 'd'),
 *   ], cols: 2));
 *
 *   // Reply keyboard
 *   $ctx->reply("Elige:", keyboard: Kb::reply(
 *       ['Opcion 1', 'Opcion 2'],
 *       ['Opcion 3'],
 *   ));
 */
class Keyboard
{
    // ══════════════════════════════════════════════════════════
    //  BOTONES INLINE
    // ══════════════════════════════════════════════════════════

    /**
     * Boton inline con callback_data.
     *
     *   Kb::inline('Aceptar', 'accept')
     */
    public static function inline(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    /**
     * Boton inline con URL.
     *
     *   Kb::url('Visitar', 'https://example.com')
     */
    public static function url(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**
     * Boton inline para compartir al chat.
     *
     *   Kb::switchInline('Buscar', 'query')
     */
    public static function switchInline(string $text, string $query = ''): array
    {
        return ['text' => $text, 'switch_inline_query' => $query];
    }

    /**
     * Boton inline para inline query en el mismo chat.
     *
     *   Kb::switchHere('Buscar aqui', 'query')
     */
    public static function switchHere(string $text, string $query = ''): array
    {
        return ['text' => $text, 'switch_inline_query_current_chat' => $query];
    }

    /**
     * Boton inline de login.
     *
     *   Kb::login('Iniciar sesion', 'https://example.com/auth')
     */
    public static function login(string $text, string $url): array
    {
        return ['text' => $text, 'login_url' => ['url' => $url]];
    }

    /**
     * Boton para WebApp.
     *
     *   Kb::webApp('Abrir App', 'https://app.example.com')
     */
    public static function webApp(string $text, string $url): array
    {
        return ['text' => $text, 'web_app' => ['url' => $url]];
    }

    /**
     * Boton de pago (Pay).
     *
     *   Kb::pay('Pagar $10')
     */
    public static function pay(string $text): array
    {
        return ['text' => $text, 'pay' => true];
    }

    // ══════════════════════════════════════════════════════════
    //  LAYOUT
    // ══════════════════════════════════════════════════════════

    /**
     * Crea una fila de botones.
     *
     *   Kb::row(Kb::inline('A', 'a'), Kb::inline('B', 'b'))
     */
    public static function row(array ...$buttons): array
    {
        return $buttons;
    }

    /**
     * Organiza botones en grid de N columnas.
     *
     *   Kb::grid([btn1, btn2, btn3, btn4], cols: 2)
     *   // Resultado: [[btn1, btn2], [btn3, btn4]]
     */
    public static function grid(array $buttons, int $cols = 2): array
    {
        return array_chunk($buttons, $cols);
    }

    /**
     * Un solo boton como fila completa.
     *
     *   Kb::single(Kb::inline('Confirmar', 'ok'))
     */
    public static function single(array $button): array
    {
        return [$button];
    }

    // ══════════════════════════════════════════════════════════
    //  INLINE KEYBOARD MARKUP
    // ══════════════════════════════════════════════════════════

    /**
     * Construye el reply_markup para inline keyboard.
     * Acepta filas de botones (arrays de arrays).
     *
     *   Kb::inlineMarkup([
     *       Kb::row(Kb::inline('A', 'a'), Kb::inline('B', 'b')),
     *       Kb::row(Kb::inline('C', 'c')),
     *   ])
     */
    public static function inlineMarkup(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    // ══════════════════════════════════════════════════════════
    //  REPLY KEYBOARD
    // ══════════════════════════════════════════════════════════

    /**
     * Construye un reply keyboard (teclado personalizado).
     * Cada argumento es una fila de botones (strings o arrays).
     *
     *   Kb::reply(['Opcion 1', 'Opcion 2'], ['Opcion 3'])
     */
    public static function reply(array ...$rows): array
    {
        $keyboard = [];
        foreach ($rows as $row) {
            $keyboard[] = array_map(function ($btn) {
                return is_string($btn) ? ['text' => $btn] : $btn;
            }, $row);
        }
        return $keyboard;
    }

    /**
     * Boton de reply keyboard que solicita contacto.
     *
     *   Kb::requestContact('Enviar contacto')
     */
    public static function requestContact(string $text): array
    {
        return ['text' => $text, 'request_contact' => true];
    }

    /**
     * Boton de reply keyboard que solicita ubicacion.
     *
     *   Kb::requestLocation('Enviar ubicacion')
     */
    public static function requestLocation(string $text): array
    {
        return ['text' => $text, 'request_location' => true];
    }

    /**
     * Boton de reply keyboard que solicita encuesta.
     *
     *   Kb::requestPoll('Crear encuesta', 'quiz')
     */
    public static function requestPoll(string $text, ?string $type = null): array
    {
        $btn = ['text' => $text, 'request_poll' => []];
        if ($type) {
            $btn['request_poll']['type'] = $type;
        }
        return $btn;
    }

    /**
     * Construye el reply_markup para reply keyboard.
     *
     *   Kb::replyMarkup(
     *       Kb::reply(['A', 'B'], ['C']),
     *       resize: true,
     *       oneTime: true
     *   )
     */
    public static function replyMarkup(
        array $keyboard,
        bool $resize = true,
        bool $oneTime = false,
        string $placeholder = '',
        bool $selective = false,
    ): array {
        $markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
            'selective' => $selective,
        ];
        if ($placeholder !== '') {
            $markup['input_field_placeholder'] = $placeholder;
        }
        return $markup;
    }

    // ══════════════════════════════════════════════════════════
    //  CONTROLES ESPECIALES
    // ══════════════════════════════════════════════════════════

    /**
     * Quitar reply keyboard.
     *
     *   $ctx->reply("Listo", extra: ['reply_markup' => Kb::remove()])
     */
    public static function remove(bool $selective = false): array
    {
        return [
            'remove_keyboard' => true,
            'selective' => $selective,
        ];
    }

    /**
     * Forzar al usuario a responder.
     *
     *   $ctx->reply("Responde:", extra: ['reply_markup' => Kb::forceReply()])
     */
    public static function forceReply(string $placeholder = '', bool $selective = false): array
    {
        $markup = [
            'force_reply' => true,
            'selective' => $selective,
        ];
        if ($placeholder !== '') {
            $markup['input_field_placeholder'] = $placeholder;
        }
        return $markup;
    }
}
