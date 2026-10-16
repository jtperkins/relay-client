#!/usr/bin/env bash
# Regenerates the relay's contract fixtures and copies them into resources/fixtures/.
#
#   scripts/sync-fixtures.sh [path to the relay checkout, default ../messaging-app]
#
# The relay writes them inside its Sail container, into a new timestamped directory under its
# ignored storage/framework/testing/; this copies that directory here (replacing files in place,
# deleting none: a fixture the relay no longer writes shows in `git status` and goes only by hand)
# and prints the run directory. It never runs `rm`.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELAY="$(cd "${1:-$HERE/../messaging-app}" && pwd)"
RUN="$(date +%Y%m%d-%H%M%S)"
IN_CONTAINER="/var/www/html/storage/framework/testing/contract-fixtures/$RUN"

(cd "$RELAY" && ./vendor/bin/sail artisan relay:contract-fixtures "$IN_CONTAINER" \
  --secret=test_secret --timestamp=1760000000 --api)

mkdir -p "$HERE/resources/fixtures"
cp -R "$RELAY/storage/framework/testing/contract-fixtures/$RUN/." "$HERE/resources/fixtures/"

echo "Copied $RELAY/storage/framework/testing/contract-fixtures/$RUN to resources/fixtures/"
