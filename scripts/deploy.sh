#!/usr/bin/env bash
#
# Synexel server deploy script
# Usage:
#   ./scripts/deploy.sh
#   BRANCH=main ./scripts/deploy.sh
#   USE_SAIL=yes ./scripts/deploy.sh
#
set -euo pipefail

# ── config (override with env vars) ──────────────────────────────────────────
BRANCH="${BRANCH:-main}"
USE_SAIL="${USE_SAIL:-auto}"          # auto | yes | no
RUN_TESTS="${RUN_TESTS:-no}"          # yes to run php artisan test after deploy
RUN_NPM_BUILD="${RUN_NPM_BUILD:-auto}" # auto | yes | no
COMPOSER_FLAGS="${COMPOSER_FLAGS:---no-dev --optimize-autoloader --no-interaction}"

# ── helpers ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}==>${NC} $*"; }
warn() { echo -e "${YELLOW}==>${NC} $*"; }
err()  { echo -e "${RED}==> ERROR:${NC} $*" >&2; }

die() {
  err "$@"
  exit 1
}

# Project root = parent of scripts/
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

log "Deploying Synexel from: $ROOT_DIR"

# ── pick php / artisan runner ────────────────────────────────────────────────
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
ARTISAN="$PHP_BIN artisan"
SAIL="./vendor/bin/sail"

if [[ "$USE_SAIL" == "auto" ]]; then
  if [[ -x "$SAIL" ]] && command -v docker >/dev/null 2>&1; then
    USE_SAIL="yes"
  else
    USE_SAIL="no"
  fi
fi

if [[ "$USE_SAIL" == "yes" ]]; then
  [[ -x "$SAIL" ]] || die "Sail not found. Run: composer install"
  ARTISAN="$SAIL artisan"
  PHP_BIN="$SAIL php"
  COMPOSER_BIN="$SAIL composer"
  log "Using Laravel Sail (Docker)"
else
  command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP not found. Install PHP 8.2+ or set USE_SAIL=yes"
  command -v "$COMPOSER_BIN" >/dev/null 2>&1 || die "Composer not found"
  log "Using local PHP"
fi

run_artisan() {
  $ARTISAN "$@"
}

# ── git pull ─────────────────────────────────────────────────────────────────
if [[ -d .git ]]; then
  log "Fetching and pulling branch: $BRANCH"
  git fetch origin "$BRANCH"
  git checkout "$BRANCH"
  git pull origin "$BRANCH"
else
  warn "Not a git repo — skipping git pull"
fi

# ── maintenance mode ─────────────────────────────────────────────────────────
log "Enabling maintenance mode"
run_artisan down --retry=60 || true

cleanup() {
  log "Disabling maintenance mode"
  run_artisan up || true
}
trap cleanup EXIT

# ── composer ─────────────────────────────────────────────────────────────────
log "Installing PHP dependencies"
# shellcheck disable=SC2086
$COMPOSER_BIN install $COMPOSER_FLAGS

# ── npm (optional) ───────────────────────────────────────────────────────────
should_npm="no"
if [[ "$RUN_NPM_BUILD" == "yes" ]]; then
  should_npm="yes"
elif [[ "$RUN_NPM_BUILD" == "auto" ]] && command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
  should_npm="yes"
fi

if [[ "$should_npm" == "yes" ]]; then
  log "Building frontend assets (npm)"
  npm ci
  npm run build
else
  warn "Skipping npm build (public/js assets are used directly)"
fi

# ── env check ────────────────────────────────────────────────────────────────
[[ -f .env ]] || die ".env file missing. Copy .env.example to .env and configure it first."

# ── migrations ───────────────────────────────────────────────────────────────
log "Running database migrations"
run_artisan migrate --force

# ── caches ───────────────────────────────────────────────────────────────────
log "Clearing and rebuilding caches"
run_artisan config:clear
run_artisan route:clear
run_artisan view:clear
run_artisan cache:clear
run_artisan config:cache
run_artisan route:cache
run_artisan view:cache

# ── storage permissions (safe on Linux servers) ────────────────────────────────
if [[ "$USE_SAIL" == "no" ]]; then
  log "Fixing storage and cache permissions"
  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || warn "Could not chmod storage/bootstrap/cache"
fi

# ── queue / horizon ──────────────────────────────────────────────────────────
log "Restarting queue workers"
run_artisan queue:restart || warn "queue:restart failed (queue may not be configured)"

if run_artisan list 2>/dev/null | grep -q "horizon:terminate"; then
  run_artisan horizon:terminate || warn "horizon:terminate failed"
fi

# ── optional tests ───────────────────────────────────────────────────────────
if [[ "$RUN_TESTS" == "yes" ]]; then
  log "Running test suite"
  run_artisan test
fi

log "Deploy finished successfully"
log "Branch: $BRANCH"
log "Tip: hard-refresh browser (Ctrl+Shift+R) to load new JS/CSS"
