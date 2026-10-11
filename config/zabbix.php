<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Zabbix server connection
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ZABBIX_ENABLED', true),
    'server' => env('ZABBIX_SERVER'),
    'port' => env('ZABBIX_PORT', 10051),

    /*
    |--------------------------------------------------------------------------
    | Default host identifier in Zabbix
    |--------------------------------------------------------------------------
    */
    'token' => env('ZABBIX_TOKEN'),
    'host' => env('ZABBIX_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Protocol
    |--------------------------------------------------------------------------
    | Supported: 'http', 'tcp', 'udp'
    */
    'protocol' => env('ZABBIX_PROTOCOL', 'tcp'), //

    /*
    |--------------------------------------------------------------------------
    | TLS / PSK encryption (optional)
    |--------------------------------------------------------------------------
    | PHP's native socket sender cannot speak TLS-PSK, so when 'connect' is set
    | to 'psk' delivery is delegated to the external `zabbix_sender` binary.
    | That binary is therefore only required when PSK is enabled; if it is
    | missing the send fails gracefully (logged, returns false) and never
    | throws. Leave 'connect' as 'unencrypted' to keep using the built-in
    | plaintext socket — fully backwards compatible.
    |
    | The PSK file must be an absolute path readable by the app/web user.
    */
    'tls' => [
        'connect' => env('ZABBIX_TLS_CONNECT', 'unencrypted'), // 'unencrypted' | 'psk'
        'psk_identity' => env('ZABBIX_TLS_PSK_IDENTITY'),
        'psk_file' => env('ZABBIX_TLS_PSK_FILE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Path to the zabbix_sender binary (used only for PSK delivery)
    |--------------------------------------------------------------------------
    */
    'sender_bin' => env('ZABBIX_SENDER_BIN', 'zabbix_sender'),
];