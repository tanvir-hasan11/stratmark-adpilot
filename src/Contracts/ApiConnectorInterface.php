<?php
declare(strict_types=1);

namespace App\Contracts;

interface ApiConnectorInterface
{
    public function provider(): string;
    public function listAccounts(): array;
    public function fetchInsights(string $accountId, string $from, string $to): array;
}
