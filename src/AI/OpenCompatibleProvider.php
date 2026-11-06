<?php
declare(strict_types=1);

namespace App\AI;

final class OpenCompatibleProvider implements AiProviderInterface
{
    public function __construct(private string $baseUrl, private string $apiKey, private string $model) {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $payload = json_encode(['model' => $options['model'] ?? $this->model, 'messages' => [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $userPrompt]], 'temperature' => $options['temperature'] ?? 0.2], JSON_THROW_ON_ERROR);
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/chat/completions');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey], CURLOPT_POSTFIELDS => $payload]);
        $body = curl_exec($ch); if ($body === false) throw new \RuntimeException(curl_error($ch));
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); $decoded = json_decode($body, true);
        if ($status >= 400 || !is_array($decoded)) throw new \RuntimeException('AI provider request failed: HTTP ' . $status);
        return $decoded;
    }
}
