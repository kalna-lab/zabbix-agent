<?php

namespace KalnaLab\ZabbixAgent\Helpers;

use Illuminate\Support\Facades\Log;
class ZabbixSender
{
    public static function send(string $host, string $key, string|int|float $value): bool
    {
        $protocol = config('zabbix.protocol', 'tcp');

        return match ($protocol) {
//            'http' => self::sendHttp($host, $key, $value),
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
        $port = (int)config('zabbix.port');
        $protocol = config('zabbix.protocol', 'tcp');
        // Brug Unix timestamp til clock
        $clock = time();

        $data = json_encode([
            'request' => 'sender data',
            'data' => [
                [
                    'host'  => $host,
                    'key'   => $key,
                    'value' => (string)$value,
                    'clock' => $clock,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $payloadLength = pack('P', strlen($data)); // 64-bit little-endian
        $header = "ZBXD" . "\x01" . $payloadLength;
        $packet = $header . $data;

        $errno = $errstr = null;
        $fp = @fsockopen("$protocol://$server", $port, $errno, $errstr, 5); // 5s timeout
        if (!$fp) {
            Log::error(__METHOD__ . " failed to connect to $server:$port: $errno $errstr");
            return false;
        }

        stream_set_timeout($fp, 5);

        // Send pakken
        fwrite($fp, $packet, strlen($packet));
        fflush($fp);

        // Læs Zabbix-serverens svar (JSON)
        $response = fread($fp, 1024);
        fclose($fp);

        // Parse response
        if (!empty($response) && str_contains($response, 'failed: 0')) {
            return true; // Zabbix accepterede pakken
        }

        Log::warning(__METHOD__ . " Zabbix did not accept data: $response");
        return false;
    }
}