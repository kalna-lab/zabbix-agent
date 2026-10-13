<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use KalnaLab\ZabbixAgent\ZabbixAgent;

const API = 'https://zabbix.example.com/api_jsonrpc.php';

beforeEach(function () {
    config([
        'zabbix.enabled' => true,
        'zabbix.protocol' => 'http',
        'zabbix.server' => 'https://zabbix.example.com/',
        'zabbix.token' => 'secret-api-token',
        'zabbix.host' => 'gigt2meta',
    ]);
});

function accepted(): array
{
    return ['jsonrpc' => '2.0', 'result' => ['response' => 'success', 'data' => [['itemid' => '10600']]], 'id' => 1];
}

function rejected(string $error): array
{
    return ['jsonrpc' => '2.0', 'result' => ['response' => 'success', 'data' => [['error' => $error]]], 'id' => 1];
}

it('pushes a value through history.push with a bearer token', function () {
    Http::fake([API => Http::response(accepted())]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 12))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === API
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer secret-api-token')
        && $request['jsonrpc'] === '2.0'
        && $request['method'] === 'history.push'
        && ! isset($request['auth'])
        && $request['params'][0]['host'] === 'gigt2meta'
        && $request['params'][0]['key'] === 'gigt2meta.sync.sent'
        && $request['params'][0]['value'] === '12'
        && is_int($request['params'][0]['clock']));
});

it('sends text values for errors', function () {
    Http::fake([API => Http::response(accepted())]);

    ZabbixAgent::error('gigt2meta.sync.error', 'Meta CAPI request failed with HTTP 400');

    Http::assertSent(fn (Request $request) => $request['params'][0]['value'] === 'Meta CAPI request failed with HTTP 400');
});

it('treats a per-item error as a failure even though the response says success', function () {
    Log::spy();
    Http::fake([API => Http::response(rejected('No permissions to referred object or it does not exist.'))]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'No permissions to referred object'));
});

it('treats a JSON-RPC error as a failure', function () {
    Log::spy();
    Http::fake([API => Http::response(['jsonrpc' => '2.0', 'error' => [
        'code' => -32602, 'message' => 'Invalid params.', 'data' => 'Not authorized.',
    ], 'id' => 1])]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Not authorized.'));
});

it('treats an HTTP error as a failure', function () {
    Http::fake([API => Http::response('Bad gateway', 502)]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeFalse();
});

it('never throws when the server is unreachable', function () {
    Log::spy();
    Http::fake([API => fn () => throw new ConnectionException('Connection refused')]);

    expect(ZabbixAgent::event('gigt2meta.sync.heartbeat'))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Connection refused'));
});

it('never logs the API token', function () {
    Log::spy();
    Http::fake([API => Http::response(rejected('Item is disabled.'))]);

    ZabbixAgent::metric('gigt2meta.sync.sent', 1);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => ! str_contains($message, 'secret-api-token'));
});

it('skips sending when token or server is missing', function (string $setting) {
    Log::spy();
    config(["zabbix.{$setting}" => null]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeFalse();

    Http::assertNothingSent();
})->with(['token', 'server']);

it('ignores PSK settings for the http protocol', function () {
    config(['zabbix.tls.connect' => 'psk', 'zabbix.sender_bin' => '/nonexistent/zabbix_sender']);
    Http::fake([API => Http::response(accepted())]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeTrue();
});

it('sends nothing when disabled', function () {
    config(['zabbix.enabled' => false]);

    expect(ZabbixAgent::metric('gigt2meta.sync.sent', 1))->toBeFalse();

    Http::assertNothingSent();
});
