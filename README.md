# Synexel

Cloud spreadsheet platform built on Laravel. Create workbooks, manage cells and formulas, import/export `.xlsx`, and receive signed webhook events — all via REST API or the Synexel web editor.

## Requirements

- Docker Desktop (recommended via Laravel Sail)
- PHP 8.2+ and Composer (for local CLI without Sail)

## Quick Start (Sail / Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate

# Windows
vendor\bin\sail up -d

# macOS/Linux
./vendor/bin/sail up -d

vendor\bin\sail artisan migrate
vendor\bin\sail artisan db:seed
php artisan serve
php artisan queue:work
```

API base URL: `http://localhost:8000/api/v1`  
OpenAPI docs: `http://localhost:8000/docs/api`  
Horizon dashboard: `http://localhost:8000/horizon`

> **Note:** `php artisan serve` uses port **8000**, not 80. Use `http://localhost:8000/...` — `http://localhost/...` will show `ERR_CONNECTION_REFUSED` unless you use Sail/Docker on port 80.

## Quick Start (SQLite, no Docker)

```bash
composer install
cp .env.example .env
```

Edit `.env`:

```
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Then:

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
php artisan queue:work
```

## Authentication

```bash
curl -X POST http://localhost:8000/api/v1/auth/tokens \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@excel-api.local","password":"password","token_name":"cli"}'
```

Use the returned token:

```bash
curl http://localhost:8000/api/v1/workbooks -H "Authorization: Bearer YOUR_TOKEN"
```

## API Examples

### Create workbook

```bash
curl -X POST http://localhost:8000/api/v1/workbooks \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Q1 Report"}'
```

### Batch update cells

```bash
curl -X PATCH http://localhost:8000/api/v1/workbooks/{id}/sheets/{sheetId}/cells \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"updates":[{"row":1,"col":1,"value":"100"},{"row":2,"col":1,"formula":"=A1*2"}]}'
```

### Read range

```bash
curl "http://localhost:8000/api/v1/workbooks/{id}/sheets/{sheetId}/cells?range=A1:B10" \
  -H "Authorization: Bearer TOKEN"
```

### Import / export XLSX

```bash
curl -X POST http://localhost:8000/api/v1/workbooks/import \
  -H "Authorization: Bearer TOKEN" -F "file=@report.xlsx"

curl -O -J http://localhost:8000/api/v1/workbooks/{id}/export -H "Authorization: Bearer TOKEN"
```

## Webhooks

### Subscribe

```bash
curl -X POST http://localhost:8000/api/v1/webhooks \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://your-server.com/hook","events":["cells.updated"]}'
```

### Verify signature (PHP)

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $yourWebhookSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

### Event types

`workbook.created`, `workbook.deleted`, `sheet.created`, `sheet.renamed`, `cells.updated`, `range.cleared`, `named_range.changed`, `conditional_format.changed`, `chart.changed`, `workbook.imported`, `workbook.exported`

## Tests

```bash
php artisan test
```

## Architecture

- Domain: `app/Domain/Spreadsheet/`
- API: `app/Http/Controllers/Api/V1/`
- Webhooks: `app/Listeners/DispatchWebhooks` + `app/Jobs/DeliverWebhookJob`
