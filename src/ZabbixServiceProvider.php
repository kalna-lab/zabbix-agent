<?php

namespace KalnaLab\ZabbixAgent;

use Illuminate\Support\ServiceProvider;

class ZabbixServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/zabbix.php' => config_path('zabbix.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/zabbix.php', 'zabbix');
    }
}