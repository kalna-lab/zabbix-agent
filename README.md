## Installation
```bash
composer require kalna-lab/zabbix-agent
php artisan vendor:publish --tag=config --provider="KalnaLab\\ZabbixAgent\\ZabbixServiceProvider"
```

## Usage
ENV variables
```dotenv
ZABBIX_SERVER=https://zabbix.example.com
ZABBIX_PORT=10051
ZABBIX_TOKEN=
ZABBIX_HOST=laravel-app
ZABBIX_PROTOCOL=tcp #Supported: http, tcp, udp
```

```php
use KalnaLab\ZabbixAgent\ZabbixAgent;

// 1️⃣ Send a metric numeric value
ZabbixAgent::metric('app.active_users', 42);

// 2️⃣ Send an event
ZabbixAgent::event('app.login_event');

// 3️⃣ Send an error
ZabbixAgent::error('app.webservice.failure', 'Timeout contacting external API');
```