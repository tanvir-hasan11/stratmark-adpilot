<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(private Env $env) {}
    public function readOnly(): bool { return $this->env->get('READ_ONLY_MODE', '1') !== '0'; }
    public function killSwitch(): bool { return $this->env->get('GLOBAL_KILL_SWITCH', '1') !== '0'; }
    public function env(): Env { return $this->env; }
}
