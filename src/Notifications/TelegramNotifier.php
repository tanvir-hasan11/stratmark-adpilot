<?php
declare(strict_types=1);

namespace App\Notifications;

final class TelegramNotifier
{
    public function __construct(private string $token, private string $chatId) {}
    public function send(string $message): void
    {
        $url = 'https://api.telegram.org/bot' . rawurlencode($this->token) . '/sendMessage';
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $this->chatId, 'text' => $message, 'parse_mode' => 'HTML'])]);
        $body = curl_exec($ch); if ($body === false) throw new \RuntimeException(curl_error($ch));
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $decoded = json_decode($body, true);
        if ($status >= 400 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) throw new \RuntimeException('Telegram request failed: HTTP ' . $status);
    }
}
