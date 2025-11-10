<?php

namespace KalnaLab\ZabbixAgent\Helpers;

use Illuminate\Support\Facades\Log;
class ZabbixSender
{
    public static function send(string $host, string $key, string|int|float $value): bool
    {
        $protocol = config('zabbix.protocol', 'tcp');

        return match ($protocol) {
            'http' => self::sendHttp($host, $key, $value),
            'tcp', 'udp' => self::sendSocket($host, $key, $value),
            default => throw new \InvalidArgumentException("Unsupported Zabbix protocol: {$protocol}"),
        };
    }

    /**
     * Send via HTTP to Zabbix API (using curl)
     */
    protected static function sendHttp(string $host, string $key, string|int|float $value): bool
    {
        $url = rtrim(config('zabbix.server'), '/');
        $token = config('zabbix.token');

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'item.create', // or custom API endpoint if using a trapper receiver
            'params' => [
                [
                    'host' => $host,
                    'key_' => $key,
                    'value' => (string)$value,
                ],
            ],
            'auth' => $token,
            'id' => 1,
        ];

        $ch = curl_init("{$url}/api_jsonrpc.php");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json-rpc'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $response && !$error;
    }

    /**
     * Send using native Zabbix sender protocol (binary)
     */
    protected static function sendSocket(string $host, string $key, string|int|float $value): bool
    {
        $server = config('zabbix.server');
        $port = config('zabbix.port');
        $protocol = config('zabbix.protocol', 'tcp');

        $data = json_encode([
            'request' => 'sender data',
            'data' => [
                [
                    'host' => $host,
                    'key' => $key,
                    'value' => (string)$value,
                    'clock' => time(),
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $header = pack('a4V', 'ZBXD', strlen($data)) . "\x01";
        $packet = $header . $data;

        $errno = $errstr = null;
        $fp = @fsockopen("$protocol://$server", $port, $errno, $errstr, 2);

        if (!$fp) {
            Log::error(__METHOD__ . " failed to connect: $errno $errstr");
            return false;
        }

        stream_set_timeout($fp, 2);
        fwrite($fp, $packet);
        fclose($fp);
        Log::info(__METHOD__ . " sent: $data to $server:$port via $protocol");

        return true;
    }
}