# Zabbix Setup Guide for `kalna-lab/zabbix-agent`

This guide explains how to configure Zabbix to receive metrics, events, and error messages from your Laravel application
using the `kalna-lab/zabbix-agent` package.

# Installation

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

---

## 1. Create or identify your application host

1. Go to **Configuration → Hosts**.
2. Click **Create host**.
3. Fill in:
    - **Host name**: `laravel-app` (or whatever you set as `ZABBIX_HOST` in your config)
    - **Groups**: e.g. `Web Applications`
    - **Interfaces**: none required (Trapper items do not need an agent interface)
4. Click **Add**.

---

## 2. Add Trapper items

Your Laravel app sends data using the **Zabbix trapper** mechanism (push mode).

For each metric or event:

1. Go to **Data collection → Hosts → _laravel-app_ → Items → Create item**.
2. Fill in:
    - **Name:** e.g. `Active users`
    - **Type:** `Zabbix trapper`
    - **Key:** `app.active_users`
    - **Type of information:** `Numeric (float)` or `Text` depending on the metric
    - **History storage period:** e.g. `7d`
    - **Trends storage period:** e.g. `90d`
3. Click **Add**.

Repeat for any other metrics:

- `app.login_event` (numeric)
- `app.webservice.failure` (text)
- etc.

---

## 3. Add Calculated items (optional)

If you send **event counters** via `ZabbixAgent::event('app.login_event')`, Zabbix can calculate counts per hour or per
day.

1. Go to **Data collection → Hosts → _laravel-app_ → Items → Create item**.
2. Choose:
    - **Type:** `Calculated`
    - **Key:** `app.logins_per_hour`
    - **Formula:** `sum(app.login_event,1h)`
    - **Type of information:** `Numeric (unsigned)`
3. Save.

This creates an automatic time-based count of login events.

---

## 4. Add Triggers (optional)

To get alerts for application errors:

1. Go to **Data collection → Hosts → _laravel-app_ → Triggers → Create trigger**.
2. Fill in:
    - **Name:** `External API failure`
    - **Expression:**
      ```
      last(/laravel-app/app.webservice.failure,#1)<>0
      ```
    - **Severity:** `Warning` or `High`
3. Save and link it to an Action (e.g., email or Slack notifications).

---

## 5. Create a Graph (optional)

1. Go to **Data collection → Hosts → _laravel-app_ → Graphs → Create graph**.
2. Add items such as:
    - `app.active_users`
    - `app.logins_per_hour`
3. Save and view under **Monitoring → Latest data** or **Graphs**.

---

## 6. Protocol Choice

In your Laravel config (`config/zabbix.php`):

| Protocol       | Description                   | Port          | Notes                                              |
|----------------|-------------------------------|---------------|----------------------------------------------------|
| `tcp` or `udp` | Native Zabbix Sender protocol | `10051`       | Binary payload, fast, requires port open           |
| `http`         | Zabbix API / HTTP trapper     | `80` or `443` | Works through firewalls, uses `curl` and API token |

If using `http`:

- Set `ZABBIX_SERVER=https://your-zabbix.example.com`
- Set `ZABBIX_TOKEN=<API token>` (create under **Administration → API tokens**)

---

## 7. Laravel Environment Configuration

Example `.env`:
```dotenv
ZABBIX_SERVER=https://zabbix.example.com
ZABBIX_PORT=10051
ZABBIX_TOKEN=
ZABBIX_HOST=laravel-app
ZABBIX_PROTOCOL=tcp #Supported: http, tcp, udp
```

---

## 8. Test Your Setup

Run this in Laravel Tinker or a test route:

```php
use KalnaLab\ZabbixAgent\ZabbixAgent;

ZabbixAgent::metric('app.active_users', 42);
ZabbixAgent::event('app.login_event');
ZabbixAgent::error('app.webservice.failure', 'Timeout contacting API');
```

Then in Zabbix:

- Go to Monitoring → Latest data
- Filter by laravel-app
- Values should appear within seconds.

---

## 9. Troubleshooting Tips

| Problem                 | Likely Cause                    | Solution                                         |
|-------------------------|---------------------------------|--------------------------------------------------|
| No data received        | Port blocked or wrong host name | Ensure host name matches ZABBIX_HOST             | 
| “Authentication failed” | Wrong API token                 | Generate a new one in User settings → API tokens | 
| Text metrics truncated  | Item type not set to Text       | Change Type of information to Text               | 
| Graph shows flat line   | Event-based data not summed     | Use a Calculated item with sum()                 | 
