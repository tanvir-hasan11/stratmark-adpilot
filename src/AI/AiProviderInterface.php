<?php
declare(strict_types=1);

namespace App\AI;

interface AiProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array;
}
