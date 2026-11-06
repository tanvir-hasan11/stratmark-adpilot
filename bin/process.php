<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Services\JobService;

try {
    $env = new Env(dirname(__DIR__) . '/.env');
    $config = new Config($env);
    $pdo = (new Database($env))->pdo();
    $jobs = new JobService($pdo); $jobId = $jobs->start('process');
    if ($config->readOnly() || $config->killSwitch()) {
        $jobs->finish($jobId, 'SUCCESS', 0);
        fwrite(STDOUT, "Approval processing skipped: read-only mode or kill switch is active.\n"); exit(0);
    }
    $jobs->finish($jobId, 'SUCCESS', 0);
    fwrite(STDOUT, "Approval processing completed: no executable actions are registered.\n");
} catch (Throwable $e) {
    if (isset($jobId, $jobs)) $jobs->finish($jobId, 'FAILED', 0, $e->getMessage());
    fwrite(STDERR, 'Process failed: ' . $e->getMessage() . PHP_EOL); exit(1);
}
