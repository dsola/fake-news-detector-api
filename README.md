# Symfony Docker Compose (PHP-FPM + Nginx + MySQL)

## Build and start
docker compose build
docker compose up -d

## Stop
docker compose down

## View logs
docker compose logs -f

## Run Composer inside PHP container
docker compose exec php composer install

## Run Symfony console
docker compose exec php php bin/console

## Run migrations
docker compose exec php php bin/console doctrine:migrations:migrate

## Enter containers
docker compose exec php sh
docker compose exec nginx sh
docker compose exec db sh
