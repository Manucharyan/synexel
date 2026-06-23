#!/usr/bin/env bash
#
# Synexel server diagnostics — run when you see HTTP 500 errors.
# Usage: ./scripts/diagnose.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "${GREEN}OK${NC}    $*"; }
warn() { echo -e "${YELLOW}WARN${NC}  $*"; }
fail() { echo -e "${RED}FAIL${NC}  $*"; }

PHP_BIN="${PHP_BIN:-php}"
ARTISAN="$PHP_BIN artisan"

echo "Synexel diagnostics"
echo "Project: $ROOT_DIR"
echo

# ── PHP ──────────────────────────────────────────────────────────────────────
if command -v "$PHP_BIN" >/dev/null 2>&1; then
  ok "PHP: $($PHP_BIN -v | head -1)"
else
  fail "PHP not found. Set PHP_BIN or install PHP 8.2+."
  exit 1
fi

# ── .env ─────────────────────────────────────────────────────────────────────
if [[ -f .env ]]; then
  ok ".env exists"
else
  fail ".env missing — copy .env.example to .env and configure it"
fi

if [[ -f .env ]] && grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  ok "APP_KEY is set"
elif [[ -f .env ]] && grep -q '^APP_KEY=.' .env 2>/dev/null && ! grep -q '^APP_KEY=$' .env; then
  ok "APP_KEY is set"
else
  fail "APP_KEY is empty — run: php artisan key:generate"
fi

# ── maintenance mode ─────────────────────────────────────────────────────────
if [[ -f storage/framework/down ]]; then
  warn "App is in maintenance mode (storage/framework/down exists)"
  warn "Run: php artisan up"
else
  ok "Not in maintenance mode"
fi

# ── storage permissions ──────────────────────────────────────────────────────
for dir in storage bootstrap/cache; do
  if [[ -w "$dir" ]]; then
    ok "$dir is writable"
  else
    fail "$dir is not writable — run: chmod -R ug+rwx storage bootstrap/cache"
  fi
done

# ── Laravel about / DB ───────────────────────────────────────────────────────
echo
echo "── Laravel environment ──"
$ARTISAN about --only=environment,cache,drivers 2>/dev/null || warn "Could not run artisan about"

echo
echo "── Migration status ──"
if $ARTISAN migrate:status 2>/dev/null; then
  pending="$($ARTISAN migrate:status 2>/dev/null | grep -c 'Pending' || true)"
  if [[ "$pending" -gt 0 ]]; then
    fail "$pending pending migration(s) — run: php artisan migrate --force"
  else
    ok "No pending migrations"
  fi
else
  fail "Could not read migration status (database connection problem?)"
fi

echo
echo "── Required schema checks ──"
$PHP_BIN -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
\$checks = [
    ['users', 'can_add_cells'],
    ['users', 'can_delete_cells'],
    ['audit_logs', 'outcome'],
    ['workbook_shares', null],
];
foreach (\$checks as [\$table, \$column]) {
    if (! Schema::hasTable(\$table)) {
        echo \"MISSING table: \$table\\n\";
        continue;
    }
    if (\$column !== null && ! Schema::hasColumn(\$table, \$column)) {
        echo \"MISSING column: \$table.\$column\\n\";
        continue;
    }
    echo \"OK: \$table\" . (\$column ? \".\$column\" : '') . \"\\n\";
}
" 2>/dev/null || warn "Schema check failed (DB may be unreachable)"

echo
echo "── Recent log errors (last 30 lines) ──"
LOG="storage/logs/laravel.log"
if [[ -f "$LOG" ]]; then
  tail -n 30 "$LOG"
else
  warn "No log file at $LOG yet"
fi

echo
echo "── Quick fix ──"
echo "Run: ./scripts/fix-500.sh"
echo "Or full redeploy: BRANCH=main ./scripts/deploy.sh"
