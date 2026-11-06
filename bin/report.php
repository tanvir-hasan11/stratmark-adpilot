<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Env;
use App\Notifications\TelegramNotifier;
use App\Services\JobService;

$root = dirname(__DIR__); $env = new Env($root . '/.env'); $jobId = null;
try {
    $pdo = (new Database($env))->pdo(); $jobs = new JobService($pdo); $jobId = $jobs->start('report');
    $from = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d'); $to = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(spend),0) spend, COALESCE(SUM(conversions),0) conversions, COALESCE(SUM(revenue),0) revenue FROM daily_insights WHERE insight_date BETWEEN ? AND ?');
    $stmt->execute([$from, $to]); $row = $stmt->fetch() ?: ['spend' => 0, 'conversions' => 0, 'revenue' => 0];
    $message = sprintf("Adpilot report (%s to %s)\nSpend: %s\nConversions: %s\nRevenue: %s", $from, $to, number_format((float)$row['spend'], 2), number_format((float)$row['conversions'], 0), number_format((float)$row['revenue'], 2));
    if ($env->get('TELEGRAM_BOT_TOKEN', '') !== '' && $env->get('TELEGRAM_CHAT_ID', '') !== '') (new TelegramNotifier($env->required('TELEGRAM_BOT_TOKEN'), $env->required('TELEGRAM_CHAT_ID')))->send($message);
    $jobs->finish((int)$jobId, 'SUCCESS', 1); fwrite(STDOUT, $message . PHP_EOL);
} catch (Throwable $e) {
    if ($jobId !== null && isset($jobs)) $jobs->finish((int)$jobId, 'FAILED', 0, $e->getMessage());
    fwrite(STDERR, 'Report failed: ' . $e->getMessage() . PHP_EOL); exit(1);
}
