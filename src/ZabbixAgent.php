<?php

namespace KalnaLab\ZabbixAgent;

use KalnaLab\ZabbixAgent\Helpers\ZabbixSender;

class ZabbixAgent
{
    /**
     * Send a numeric metric to Zabbix.
     */
    public static function metric(string $key, float|int|string $value, ?string $host = null): bool
    {
        return ZabbixSender::send($host, $key, $value);
    }

    /**
     * Send an event (counter increment) to Zabbix.
     */
    public static function event(string $key, ?string $host = null): bool
    {
        return ZabbixSender::send($host, $key, 1);
    }

    /**
     * Send a textual error message to Zabbix.
     */
    public static function error(string $key, string $message, ?string $host = null): bool
    {
        return ZabbixSender::send($host, $key, $message);
    }
}