#!/usr/bin/env bash

set -x
set -e


if (( "$#" != 1 ))
then
    echo "Tag has to be provided"
    exit 1
fi

rm -rf build/jm-container

cp -r apps/jm build/jm-container
rm -rf build/jm-container/vendor
rm -rf build/jm-container/var/cache
rm -rf build/jm-container/var/logs
rm -rf build/jm-container/.git

mkdir -p build/jm-container/var/cache/prod
mkdir -p build/jm-container/var/logs
chmod u+rwX -R build/jm-container/var

(cd build/jm-container; composer install --prefer-dist --no-dev --ignore-platform-reqs --no-scripts --optimize-autoloader --no-interaction)

cp -f docker/jm/release/.env build/jm-container
(cd build/jm-container; ENQUEUE_DSN=amqp:// MONGO_DSN=mongodb://localhost:27017 bin/console cache:warmup)

cp docker/jm/release/Dockerfile build/jm-container
(cd build/jm-container; docker build --rm --force-rm --tag "formapro/comrade:$1" .)

docker login --username="$DOCKER_USER" --password="$DOCKER_PASSWORD"
docker push "formapro/comrade:$1"