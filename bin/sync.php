<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Connectors\GoogleAdsConnector;
use App\Connectors\MetaConnector;
use App\Core\Database;
use App\Core\Env;
use App\Repositories\InsightRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\JobService;
use App\Services\SyncService;

$root = dirname(__DIR__);
$env = new Env($root . '/.env');
$jobId = null;
try {
    $database = new Database($env);
    $pdo = $database->pdo();
    $jobs = new JobService($pdo);
    $jobId = $jobs->start('sync');
    $state = (new App\Core\LocalState())->all();
    $currency = (string)($state['settings']['currency'] ?? 'USD');
    $timezone = (string)($state['settings']['timezone'] ?? 'UTC');
    $clientId = (new WorkspaceRepository($pdo))->ensureDefaultClient((string)($state['brand']['name'] ?? 'Default workspace'), $timezone, $currency);
    $from = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->modify('-6 days')->format('Y-m-d');
    $to = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
    $service = new SyncService($pdo, new InsightRepository($pdo));
    $workspace = new WorkspaceRepository($pdo);
    $total = 0;
    if (($state['providers']['meta']['enabled'] ?? false) && trim((string)$env->get('META_ACCESS_TOKEN', '')) !== '' && trim((string)($state['providers']['meta']['account_id'] ?? '')) !== '') {
        $account = trim((string)$state['providers']['meta']['account_id']);
        $providerId = $workspace->upsertProviderAccount($clientId, 'meta', $account);
        $total += $service->syncAccount($providerId, $clientId, 'meta', new MetaConnector($env->required('META_ACCESS_TOKEN'), $env->get('META_API_VERSION', 'v21.0')), $from, $to);
    }
    if (($state['providers']['google']['enabled'] ?? false) && trim((string)$env->get('GOOGLE_ACCESS_TOKEN', '')) !== '' && trim((string)($state['providers']['google']['customer_id'] ?? '')) !== '') {
        $account = trim((string)$state['providers']['google']['customer_id']);
        $providerId = $workspace->upsertProviderAccount($clientId, 'google_ads', $account);
        $connector = new GoogleAdsConnector($env->required('GOOGLE_DEVELOPER_TOKEN'), $env->required('GOOGLE_ACCESS_TOKEN'), $account, $env->get('GOOGLE_LOGIN_CUSTOMER_ID', ''), $env->get('GOOGLE_API_VERSION', 'v18'));
        $total += $service->syncAccount($providerId, $clientId, 'google_ads', $connector, $from, $to);
    }
    $jobs->finish((int)$jobId, 'SUCCESS', $total);
    fwrite(STDOUT, "Sync completed: {$total} records.\n");
} catch (Throwable $e) {
    if ($jobId !== null && isset($jobs)) $jobs->finish((int)$jobId, 'FAILED', 0, $e->getMessage());
    fwrite(STDERR, 'Sync failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
