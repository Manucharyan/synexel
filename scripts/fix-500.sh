#!/usr/bin/env bash
#
# Repair common Synexel HTTP 500 causes after deploy.
# Usage: ./scripts/fix-500.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

GREEN='\033[0;32m'
NC='\033[0m'
log() { echo -e "${GREEN}==>${NC} $*"; }

PHP_BIN="${PHP_BIN:-php}"
ARTISAN="$PHP_BIN artisan"

[[ -f .env ]] || { echo "ERROR: .env missing"; exit 1; }

log "Bringing app out of maintenance mode (if stuck)"
$ARTISAN up 2>/dev/null || true

log "Clearing all caches"
$ARTISAN optimize:clear

log "Running database migrations"
$ARTISAN migrate --force

log "Ensuring storage symlink"
$ARTISAN storage:link 2>/dev/null || true

log "Fixing storage permissions"
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

log "Rebuilding config and view caches"
$ARTISAN config:cache
$ARTISAN view:cache

log "Restarting queue workers"
$ARTISAN queue:restart 2>/dev/null || true

log "Done. If still broken, run: ./scripts/diagnose.sh"
log "Check: tail -50 storage/logs/laravel.log"
