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
    - **Formula:** `sum(/host/app.login_event,1h)`
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

| Protocol       | Description                                     | Port   | Notes                                                                 |
|----------------|-------------------------------------------------|--------|-----------------------------------------------------------------------|
| `tcp` or `udp` | Native Zabbix sender protocol                   | 10051  | Plaintext unless PSK is enabled (requires the `zabbix_sender` binary) |
| `http`         | Zabbix API method `history.push` (Zabbix 7.0+)  | 443    | Encrypted by HTTPS, no binary, works on containers/PaaS and through firewalls |

### Setting up `http` (history.push)

1. **API token.** Create a dedicated API user, e.g. `api-myapp`, and give it the least access it needs:
    - A user group with **Read-write** permission on the host group containing *only* this application's host.
    - A user role that allows the API method `history.push` (you can deny all other methods).
    - Create the token under **Users → API tokens**, ideally with an expiry date.
2. **Allowed hosts on every trapper item.** `history.push` is executed by the Zabbix *frontend*, which
   forwards the value to the server. The frontend's IP (often `127.0.0.1` when frontend and server share a
   machine) must therefore be listed in **Allowed hosts** on each trapper item — not the IP of your application.
   Leave the field empty to allow any source.
3. **Configure the app:**
   ```dotenv
   ZABBIX_PROTOCOL=http
   ZABBIX_SERVER=https://zabbix.example.com   # frontend URL, /api_jsonrpc.php is appended
   ZABBIX_TOKEN=<API token>
   ZABBIX_HOST=myapp                          # technical host name in Zabbix
   ZABBIX_TIMEOUT=5
   ```

Zabbix replies `"response": "success"` even when an individual value is rejected (unknown item, disabled item,
missing permissions). The package checks the per-item result and returns `false` with a logged warning in that case.

---

## 7. Laravel Environment Configuration

Example `.env`:
```dotenv
ZABBIX_SERVER=https://zabbix.example.com
ZABBIX_PORT=10051
ZABBIX_TOKEN=
ZABBIX_HOST=laravel-app
ZABBIX_PROTOCOL=tcp # Supported: http (Zabbix 7.0+), tcp, udp
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
| “Authentication failed” / “Not authorized.” | Wrong or expired API token | Generate a new one in Users → API tokens | 
| “No permissions to referred object or it does not exist.” | Wrong host/key, or API user lacks Read-write on the host | Check host name, item key and user group permissions | 
| http: value rejected although token is valid | Frontend IP not in the item's *Allowed hosts* | Add the frontend IP (often 127.0.0.1) | 
| Text metrics truncated  | Item type not set to Text       | Change Type of information to Text               | 
| Graph shows flat line   | Event-based data not summed     | Use a Calculated item with sum()                 | 
