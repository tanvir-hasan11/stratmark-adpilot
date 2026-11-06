<?php
declare(strict_types=1);

namespace App\Connectors;

use App\Contracts\ApiConnectorInterface;

final class GoogleAdsConnector implements ApiConnectorInterface
{
    public function __construct(private string $developerToken, private string $accessToken, private string $customerId, private string $loginCustomerId = '', private string $apiVersion = 'v18') {}
    public function provider(): string { return 'google_ads'; }
    public function listAccounts(): array { return $this->query('SELECT customer_client.id, customer_client.descriptive_name FROM customer_client'); }
    public function fetchInsights(string $accountId, string $from, string $to): array
    {
        $from = self::date($from); $to = self::date($to);
        $query = "SELECT campaign.id, campaign.name, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.ctr, metrics.conversions, metrics.conversions_value, segments.date FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}'";
        return $this->query($query, $accountId);
    }
    private function query(string $query, ?string $customerId = null): array
    {
        $id = preg_replace('/\D/', '', (string)($customerId ?? $this->customerId));
        if ($id === '') throw new \InvalidArgumentException('Invalid Google Ads customer ID.');
        $url = 'https://googleads.googleapis.com/' . $this->apiVersion . '/customers/' . $id . ':searchStream';
        $headers = ['Content-Type: application/json', 'developer-token: ' . $this->developerToken, 'Authorization: Bearer ' . $this->accessToken];
        if ($this->loginCustomerId !== '') $headers[] = 'login-customer-id: ' . preg_replace('/\D/', '', $this->loginCustomerId);
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode(['query' => $query], JSON_THROW_ON_ERROR)]);
        $body = curl_exec($ch); if ($body === false) throw new \RuntimeException(curl_error($ch));
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); $data = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) throw new \RuntimeException('Google Ads API request failed: HTTP ' . $status);
        $rows = [];
        foreach ($data as $batch) foreach (($batch['results'] ?? []) as $row) $rows[] = $row;
        return ['data' => $rows];
    }
    private static function date(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException('Invalid date.');
        return $value;
    }
}
