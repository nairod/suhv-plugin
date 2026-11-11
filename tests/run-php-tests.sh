#!/usr/bin/env bash
set -euo pipefail

plugin_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

docker run --rm \
  --volume "${plugin_root}:/plugin:ro" \
  --workdir /plugin \
  php:8.3-cli \
  php tests/test-shortcodes.php
