<?php

namespace TeleBot;

/**
 * Funciones utilitarias para el bot.
 */
class Helpers
{
    /**
     * Escapa HTML para Telegram (parse_mode=HTML).
     * SIEMPRE usar cuando insertes texto del usuario en mensajes HTML.
     *
     *   $safe = Helpers::escape($ctx->args());
     *   $ctx->reply("Buscando: <b>{$safe}</b>");
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Formatea texto en negrita (HTML).
     */
    public static function bold(string $text): string
    {
        return '<b>' . self::escape($text) . '</b>';
    }

    /**
     * Formatea texto en italica (HTML).
     */
    public static function italic(string $text): string
    {
        return '<i>' . self::escape($text) . '</i>';
    }

    /**
     * Formatea texto como codigo inline.
     */
    public static function code(string $text): string
    {
        return '<code>' . self::escape($text) . '</code>';
    }

    /**
     * Formatea texto como bloque de codigo.
     */
    public static function pre(string $text, string $language = ''): string
    {
        $lang = $language ? " class=\"language-{$language}\"" : '';
        return "<pre{$lang}>" . self::escape($text) . '</pre>';
    }

    /**
     * Crea un link HTML.
     */
    public static function link(string $text, string $url): string
    {
        return '<a href="' . self::escape($url) . '">' . self::escape($text) . '</a>';
    }

    /**
     * Mention a un usuario por ID.
     */
    public static function mention(string $text, int $userId): string
    {
        return '<a href="tg://user?id=' . $userId . '">' . self::escape($text) . '</a>';
    }

    /**
     * Hace una peticion HTTP GET.
     */
    public static function httpGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $error ? null : $response;
    }

    /**
     * Hace una peticion HTTP POST con JSON.
     */
    public static function httpPostJson(string $url, array $data, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
            ], $headers),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $error ? null : $response;
    }

    /**
     * Descarga un archivo desde URL.
     */
    public static function downloadUrl(string $url, string $destPath): bool
    {
        $ch = curl_init($url);
        $fp = fopen($destPath, 'wb');
        if (!$fp) return false;

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            @unlink($destPath);
            return false;
        }

        return true;
    }

    /**
     * Genera un ID unico corto.
     */
    public static function shortId(int $length = 8): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }

    /**
     * Formatea bytes a formato legible.
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Formatea segundos a duracion legible.
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }
}
