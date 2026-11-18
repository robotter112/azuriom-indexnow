#!/bin/sh
# Runs the tests of IndexNow: the self-checks without Laravel, then the feature
# test in a fresh Azuriom with SQLite and a second plugin that knows nothing
# about IndexNow (tests/stubs/indexnow-demo), once as installed and once with
# route and config cache (AzBoost) on.
#
#   sh tests/ci.sh [work directory]
#
# Needs curl, unzip and PHP with gd, pdo_sqlite, zip, intl and bcmath. Uses the
# release package of Azuriom, which already contains vendor/.
set -eu

PLUGIN=$(cd "$(dirname "$0")/.." && pwd)
WORK=${1:-$(mktemp -d)}
VERSION=${AZURIOM_VERSION:-1.2.13}
# SHA-256 of the official package. Another version needs its own (AZURIOM_SHA256): without a
# known checksum nothing is downloaded, let alone installed.
if [ "$VERSION" = "1.2.13" ]; then
    SHA256=${AZURIOM_SHA256:-3166f93903f678dbc44caf7ec289a04b3b46e35752c5e877fea1264d94309b69}
else
    SHA256=${AZURIOM_SHA256:?AZURIOM_SHA256 is needed for Azuriom $VERSION}
fi

php "$PLUGIN/tests/ClientTest.php"
php "$PLUGIN/tests/UrlSourceTest.php"

mkdir -p "$WORK/azuriom"
curl -fsSL -o "$WORK/azuriom.zip" "https://github.com/Azuriom/Azuriom/releases/download/v$VERSION/Azuriom-$VERSION.zip"
echo "$SHA256  $WORK/azuriom.zip" | sha256sum -c --quiet
unzip -q "$WORK/azuriom.zip" -d "$WORK/azuriom"
cd "$WORK/azuriom"

cp .env.example .env
sed -i -e 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' -e '/^DB_\(HOST\|PORT\|DATABASE\|USERNAME\|PASSWORD\)=/d' .env
touch database/database.sqlite
php artisan key:generate --force --quiet
php artisan migrate --force --quiet
php artisan db:seed --force --quiet

mkdir -p plugins/indexnow plugins/indexnow-demo
cp -R "$PLUGIN"/. plugins/indexnow/
rm -rf plugins/indexnow/.git plugins/indexnow/tests/stubs
cp -R "$PLUGIN"/tests/stubs/indexnow-demo/. plugins/indexnow-demo/

php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $m = app(Azuriom\Extensions\Plugin\PluginManager::class);
    exit($m->enable("indexnow") && $m->enable("indexnow-demo") ? 0 : 1);'
php artisan migrate --force --quiet

php plugins/indexnow/tests/feature.php

php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    exit(app(Azuriom\Support\Optimizer::class)->cache() ? 0 : 1);'
INDEXNOW_AZBOOST=1 php plugins/indexnow/tests/feature.php
