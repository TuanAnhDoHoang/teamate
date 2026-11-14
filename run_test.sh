#!/bin/sh
set -eu

docker compose run --build --rm test-runner php artisan test
