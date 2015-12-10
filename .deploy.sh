#!/bin/bash

set -e

# copy a default environment file
/bin/cp -n .env.example .env

# composer install
/usr/local/bin/composer.phar install --prefer-dist --no-progress
