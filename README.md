## Installation
```bash
composer require kalna-lab/zabbix-agent
php artisan vendor:publish --tag=config --provider="KalnaLab\\ZabbixAgent\\ZabbixServiceProvider"
```

## Usage
```php
use KalnaLab\ZabbixAgent\ZabbixAgent;

// 1️⃣ Send a metric numeric value
ZabbixAgent::metric('app.active_users', 42);

// 2️⃣ Send an event
ZabbixAgent::event('app.login_event');

// 3️⃣ Send an error
ZabbixAgent::error('app.webservice.failure', 'Timeout contacting external API');
```