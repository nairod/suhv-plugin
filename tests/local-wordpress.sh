#!/usr/bin/env bash
set -euo pipefail

plugin_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
network_name=suhv-webcomponents-test
database_name=suhv-webcomponents-db
wordpress_name=suhv-webcomponents-wp

cleanup=false
if [[ "${1:-}" == "--stop" ]]; then
  docker rm --force "${wordpress_name}" "${database_name}" 2>/dev/null || true
  docker network rm "${network_name}" 2>/dev/null || true
  exit 0
fi

docker network inspect "${network_name}" >/dev/null 2>&1 || docker network create "${network_name}" >/dev/null
docker rm --force "${wordpress_name}" "${database_name}" 2>/dev/null || true

docker run --detach --name "${database_name}" --network "${network_name}" \
  --env MYSQL_DATABASE=wordpress \
  --env MYSQL_USER=wordpress \
  --env MYSQL_PASSWORD=wordpress \
  --env MYSQL_RANDOM_ROOT_PASSWORD=yes \
  mariadb:11 >/dev/null

docker run --detach --name "${wordpress_name}" --network "${network_name}" --publish 8080:80 \
  --volume "${plugin_root}:/var/www/html/wp-content/plugins/suhv-webcomponents:ro" \
  --env WORDPRESS_DB_HOST="${database_name}:3306" \
  --env WORDPRESS_DB_NAME=wordpress \
  --env WORDPRESS_DB_USER=wordpress \
  --env WORDPRESS_DB_PASSWORD=wordpress \
  wordpress:6.8-php8.3-apache >/dev/null

echo 'WordPress is starting at http://localhost:8080'
echo 'Complete the one-time installer, activate SUHV Web Components, then create a page with:'
echo '[uniho-team-games team-id="429626" season="2025" page-size="5"]'
echo 'Run tests/local-wordpress.sh --stop to remove the local containers and network.'
