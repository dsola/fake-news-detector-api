## Getting started
Requirements on host:

Docker and Docker Compose installed and running.
​

.env.local file with at least POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD, POSTGRES_VERSION, APP_ENV variables defined.
​

Build and start the stack (dev):

bash
docker compose build
docker compose up -d
Main services:

php: PHP 8.3 FPM, Symfony app in /var/www/html, using APP_ENV=dev and DATABASE_URL pointing to database (PostgreSQL 16).
​

nginx: Exposes the app on http://localhost:8080.
​

database: PostgreSQL \${POSTGRES_VERSION}-alpine on host port 5433, data stored in database_data volume.
​

Installing dependencies
Inside the PHP container (recommended):

bash
docker compose exec php composer install
This installs PHP and Symfony dependencies as defined in composer.json using the container’s PHP 8.3 environment, avoiding host-version drift.

For dev-only packages (test tools, Symfony profiler, etc.) ensure APP_ENV=dev or use --dev as needed.
​

To update dependencies:

bash
docker compose exec php composer update
Run composer install again after pulling new changes that modify composer.lock.
​

Database and migrations
Ensure the database container is healthy (Docker will run pg_isready via the healthcheck) before running migrations.
​

Create / update the schema in the dev environment:

bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
If you use fixtures for local dev:

bash
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
Use a separate test database (e.g. postfix _test) configured in DATABASE_URL under config/packages/test/doctrine.yaml so tests do not touch dev data.
​

Running tests
Unit and integration tests (PHPUnit / Symfony PHPUnit Bridge)
Require and use Symfony’s PHPUnit Bridge for Symfony 7.1:

bash
docker compose exec php composer require --dev symfony/phpunit-bridge
Run tests via the bridge:

bash
docker compose exec php ./vendor/bin/simple-phpunit
This gives better deprecation reporting and allows controlling the PHPUnit version via SYMFONY_PHPUNIT_VERSION in phpunit.xml.dist.

Typical useful variants:

bash

# Single test file

docker compose exec php ./vendor/bin/simple-phpunit tests/Service/FooTest.php

# With deprecation baseline

docker compose exec php SYMFONY_DEPRECATIONS_HELPER='baselineFile=./tests/allowed.json' ./vendor/bin/simple-phpunit
Ensure config/packages/test/ exists and contains test-specific config (e.g. twig.strict_variables: true) for stricter checks in tests.
​

Functional / HTTP tests
Use Symfony’s WebTestCase and the HTTP client to hit routes directly; the kernel is booted in test environment automatically.
​

Prefer hardcoding URLs in tests (e.g. /api/articles) rather than generating them with the router, to detect route changes that would break clients.
​

Running the app
After docker compose up -d, access the API/app at:

text
http://localhost:8080
Symfony console inside the PHP container:

bash
docker compose exec php bin/console <command>
Example:

bash
docker compose exec php bin/console cache:clear
docker compose exec php bin/console debug:router
Use environment-specific caches (var/cache/dev, var/cache/test, var/cache/prod) and avoid committing them to VCS.

Good practices: PHP 8.3 \& Symfony 7.1
Code style and static analysis
Enable strict typing and modern PHP 8.3 features:

Use declare(strict_types=1); at the top of PHP files.

Prefer typed properties, union types, attributes, and readonly where appropriate.
​

Add tools as dev dependencies and wire them into CI:

php-cs-fixer or friendsofphp/php-cs-fixer for code style.

phpstan or psalm for static analysis.

Run them inside the container so results match CI:

bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run
docker compose exec php vendor/bin/phpstan analyse src tests
Using these tools continuously prevents regressions and keeps the codebase consistent.
​

Symfony best practices
Keep configuration per environment under config/packages/{env}; avoid environment-specific conditionals in code.
​

Use dependency injection (services in config/services.yaml or autoconfiguration) instead of new in controllers.
​

Keep controllers thin: delegate logic to services, use DTOs / value objects for complex input, and avoid domain logic in controllers.
​

Run tests:

After each non-trivial change.

Before pushing / opening a PR.

In CI as a required check for merging.
​

Good practices: Docker \& Symfony
Mount the project as a volume in dev (as in your compose file) for live reload, but use a multi-stage Dockerfile for production with compiled assets and warmed caches.

Use environment variables for secrets and DSNs (DATABASE_URL, API keys) and avoid hard-coding them in config or code.
​

Keep the PHP image lean:

Install only necessary extensions (PDO, pgsql, intl, etc.).

Copy a hardened php.ini (disable display_errors in production, set sensible memory limits).
​

Separate concerns by container:

php for PHP-FPM, nginx for HTTP, database for PostgreSQL, and optionally dedicated containers for cache/queue/search services as the app grows.
​

For CI:

Reuse the same docker-compose.yml or a trimmed docker-compose.ci.yml to run composer install, database migrations, and simple-phpunit so the pipeline matches local dev.

Typical workflows
Fresh clone:

bash
cp .env .env.local   \# then adapt values
docker compose up -d --build
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php ./vendor/bin/simple-phpunit
Daily dev loop:

bash
docker compose up -d

# edit code locally

docker compose exec php ./vendor/bin/simple-phpunit
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction  \# if schema changed
This should give any contributor a clear, repeatable way to install dependencies, run tests, and follow solid PHP/Symfony/Docker practices for your app.

Here’s a clean conversion of your provided setup guide into a properly formatted **Markdown** file that can be used as `README.md` or developer documentation.

***

```markdown
# Getting Started

## Requirements on Host

- Docker and Docker Compose installed and running.
- `.env.local` file with at least the following variables defined:
  - `POSTGRES_DB`
  - `POSTGRES_USER`
  - `POSTGRES_PASSWORD`
  - `POSTGRES_VERSION`
  - `APP_ENV`

---

## Build and Start the Stack (Dev)

```bash
docker compose build
docker compose up -d
```


### Main Services

- **php**: PHP 8.3 FPM, Symfony app in `/var/www/html`, using `APP_ENV=dev` and `DATABASE_URL` pointing to PostgreSQL 16.
- **nginx**: Exposes the app on [http://localhost:8080](http://localhost:8080).
- **database**: PostgreSQL `${POSTGRES_VERSION}`-alpine on host port `5433`, storing data in the `database_data` volume.

---

## Installing Dependencies

Inside the PHP container (recommended):

```bash
docker compose exec php composer install
```

This installs PHP and Symfony dependencies as defined in `composer.json` using the container’s PHP 8.3 environment, avoiding host-version drift.

- For dev-only packages (test tools, Symfony profiler, etc.), ensure `APP_ENV=dev` or use the `--dev` flag as needed.

To update dependencies:

```bash
docker compose exec php composer update
```

Run `composer install` again after pulling new changes that modify `composer.lock`.

---

## Database and Migrations

Ensure the database container is healthy (`pg_isready` healthcheck) before running migrations.

Create or update the schema in the dev environment:

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

If you use fixtures for local dev:

```bash
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
```

Use a separate test database (e.g., with suffix `_test`) configured in `DATABASE_URL` under `config/packages/test/doctrine.yaml` to prevent tests from touching dev data.

---

## Running Tests

### Unit and Integration Tests (PHPUnit / Symfony PHPUnit Bridge)

Require and use Symfony’s PHPUnit Bridge for Symfony 7.1:

```bash
docker compose exec php composer require --dev symfony/phpunit-bridge
```

Run tests via the bridge:

```bash
docker compose exec php ./vendor/bin/simple-phpunit
```

This provides improved deprecation reporting and controls PHPUnit versions via `SYMFONY_PHPUNIT_VERSION` in `phpunit.xml.dist`.

**Useful variants:**

```bash
# Single test file
docker compose exec php ./vendor/bin/simple-phpunit tests/Service/FooTest.php

# With deprecation baseline
docker compose exec php SYMFONY_DEPRECATIONS_HELPER='baselineFile=./tests/allowed.json' ./vendor/bin/simple-phpunit
```

Ensure `config/packages/test/` exists and contains test-specific configuration (e.g., `twig.strict_variables: true`) for stricter checks.

---

### Functional / HTTP Tests

- Use `WebTestCase` and Symfony’s HTTP client to test routes directly; the kernel boots in the test environment automatically.
- Prefer hardcoding URLs in tests (e.g. `/api/articles`) to catch route changes that could break clients.

---

## Running the App

After the stack is up:

```
http://localhost:8080
```

Symfony console inside the PHP container:

```bash
docker compose exec php bin/console <command>
```

**Examples:**

```bash
docker compose exec php bin/console cache:clear
docker compose exec php bin/console debug:router
```

Use environment-specific caches (`var/cache/dev`, `var/cache/test`, `var/cache/prod`) and avoid committing them to VCS.

---

## Good Practices: PHP 8.3 \& Symfony 7.1

### Code Style and Static Analysis

Enable strict typing and use modern PHP 8.3 features:

- Add `declare(strict_types=1);` to each PHP file.
- Use typed properties, union types, attributes, and `readonly` where appropriate.

**Recommended tools (as dev dependencies):**

- `friendsofphp/php-cs-fixer` for code style.
- `phpstan` or `psalm` for static analysis.

Run them inside the container for consistent results:

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run
docker compose exec php vendor/bin/phpstan analyse src tests
```


### Symfony Best Practices

- Keep per-environment configs under `config/packages/{env}` — avoid conditionals in code.
- Use dependency injection instead of `new` in controllers.
- Keep controllers thin: delegate logic to services, use DTOs or value objects for complex input.

Run tests:

- After each significant change.
- Before pushing or opening a PR.
- In CI as a required merge check.

---

## Good Practices: Docker \& Symfony

- Mount the project as a volume in dev for live reload.
- For production, use a multi-stage Dockerfile with compiled assets and warmed caches.
- Use environment variables (e.g. `DATABASE_URL`, API keys) instead of hardcoding secrets.

**Keep the PHP image lean:**

- Install only needed extensions (`PDO`, `pgsql`, `intl`, etc.).
- Use a hardened `php.ini` in production (disable `display_errors`, set sane memory limits).

**Container separation:**

- `php` for PHP-FPM
- `nginx` for HTTP
- `database` for PostgreSQL
- Optional: dedicated containers for cache, queue, or search services.

**In CI:**

- Reuse `docker-compose.yml` or a trimmed `docker-compose.ci.yml` to run `composer install`, migrations, and tests for pipeline consistency.

---

## Typical Workflows

### Fresh Clone

```bash
cp .env .env.local # then adapt values
docker compose up -d --build
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php ./vendor/bin/simple-phpunit
```


### Daily Dev Loop

```bash
docker compose up -d
# edit code locally
docker compose exec php ./vendor/bin/simple-phpunit
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction  # if schema changed
```

