<?php

namespace KalnaLab\ZabbixAgent\Helpers;

use Illuminate\Support\Facades\Log;
class ZabbixSender
{
    public static function send(string $host, string $key, string|int|float $value): bool
    {
        if (!config('zabbix.enabled')) {
            return false;
        }

        // When the Zabbix host requires PSK, delegate to the zabbix_sender
        // binary — PHP sockets cannot negotiate TLS-PSK. Opt-in only.
        if (config('zabbix.tls.connect') === 'psk') {
            return self::sendViaBinary($host, $key, $value);
        }

        $protocol = config('zabbix.protocol', 'tcp');

        return match ($protocol) {
//            'http' => self::sendHttp($host, $key, $value),
            'tcp', 'udp' => self::sendSocket($host, $key, $value),
            default => throw new \InvalidArgumentException("Unsupported Zabbix protocol: {$protocol}"),
        };
    }

    /**
     * Send PSK-encrypted data by shelling out to the external zabbix_sender
     * binary. This is the only reliable way to speak TLS-PSK from PHP. The
     * binary is therefore an optional, deployment-time dependency: when it is
     * absent (or PSK is misconfigured) the send fails gracefully and is logged
     * rather than throwing, so telemetry never breaks the calling flow.
     */
    protected static function sendViaBinary(string $host, string $key, string|int|float $value): bool
    {
        $server = config('zabbix.server');
        if (!$server) {
            return false;
        }

        $identity = config('zabbix.tls.psk_identity');
        $pskFile = config('zabbix.tls.psk_file');
        if (!$identity || !$pskFile) {
            Log::warning(__METHOD__ . ': PSK selected but tls.psk_identity / tls.psk_file is not configured.');
            return false;
        }

        $bin = config('zabbix.sender_bin', 'zabbix_sender');

        // Array form runs the program directly without a shell, so arguments
        // need no escaping and cannot be injected.
        $command = [
            $bin,
            '-z', (string)$server,
            '-p', (string)config('zabbix.port', 10051),
            '-s', $host,
            '--tls-connect', 'psk',
            '--tls-psk-identity', $identity,
            '--tls-psk-file', $pskFile,
            '-k', $key,
            '-o', (string)$value,
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            Log::warning(__METHOD__ . ": unable to run '{$bin}'. PSK delivery requires the zabbix_sender binary to be installed.");
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $exitCode = proc_close($process);

        if ($exitCode === 0 && str_contains($stdout, 'failed: 0')) {
            return true;
        }

        Log::warning(__METHOD__ . " Zabbix did not accept the data ($key: $value): exit={$exitCode} stdout=" . trim($stdout) . ' stderr=' . trim($stderr));
        return false;
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

        if (!$server) {
            return false;
        }

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

        Log::warning(__METHOD__ . " Zabbix did not accept the data ($key: $value): $response");
        return false;
    }
}