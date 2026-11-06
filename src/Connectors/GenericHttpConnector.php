<?php
declare(strict_types=1);

namespace App\Connectors;

use App\Contracts\ApiConnectorInterface;

final class GenericHttpConnector implements ApiConnectorInterface
{
    public function __construct(private string $name, private string $baseUrl, private array $headers = []) {}
    public function provider(): string { return $this->name; }
    public function listAccounts(): array { return $this->request('GET', '/accounts'); }
    public function fetchInsights(string $accountId, string $from, string $to): array
    {
        return $this->request('GET', '/insights?' . http_build_query(compact('accountId', 'from', 'to')));
    }
    private function request(string $method, string $path): array
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $this->headers]);
        $body = curl_exec($ch);
        if ($body === false) throw new \RuntimeException(curl_error($ch));
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) throw new \RuntimeException('API request failed: HTTP ' . $status);
        return $data;
    }
}
