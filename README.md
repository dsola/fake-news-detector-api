# fake-news-detector-api

An API that actually verifies the articles you are reading.

## Tech Stack

- **Framework**: Symfony 7.1
- **PHP**: 8.3
- **Database**: Doctrine ORM (SQLite for development, PostgreSQL for production)
- **Authentication**: JWT Bearer token (Lexik JWT Authentication Bundle)
- **API Documentation**: API Platform
- **Testing**: PHPUnit
- **Validation**: Symfony Validator

## Features

- ✅ RESTful JSON API with JSON-LD support
- ✅ Article resource for managing news articles
- ✅ Automatic validation with constraint violations
- ✅ Error handling with detailed error responses
- ✅ OpenAPI/Swagger documentation
- ✅ JWT authentication ready
- ✅ Automated tests with PHPUnit
- ✅ Docker setup for local development

## Installation

### Requirements

- PHP 8.3 or higher
- Composer 2.x
- Docker and Docker Compose (for local development)

### Setup

1. Clone the repository:
```bash
git clone https://github.com/dsola/fake-news-detector-api.git
cd fake-news-detector-api
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp .env .env.local
# Edit .env.local with your configuration
```

4. Generate JWT keys:
```bash
php bin/console lexik:jwt:generate-keypair
```

5. Create database and run migrations:
```bash
php bin/console doctrine:migrations:migrate
```

## Usage

### Running the Development Server

Using Symfony CLI (recommended):
```bash
symfony server:start
```

Or using PHP built-in server:
```bash
php -S localhost:8080 -t public/
```

### Using Docker

Start the services:
```bash
docker compose up -d
```

The API will be available at `http://localhost:8080`

### API Documentation

Once the server is running, visit:
- OpenAPI documentation: http://localhost:8080/api/docs
- API endpoints: http://localhost:8080/api

### Example API Requests

Create an article:
```bash
curl -X POST http://localhost:8080/api/articles \
  -H "Content-Type: application/ld+json" \
  -d '{
    "url": "https://example.com/article",
    "title": "Breaking News",
    "content": "This is the article content."
  }'
```

Get all articles:
```bash
curl http://localhost:8080/api/articles
```

Get a specific article:
```bash
curl http://localhost:8080/api/articles/1
```

### Authentication

To authenticate requests, first obtain a JWT token:
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "user@example.com",
    "password": "password"
  }'
```

Then use the token in subsequent requests:
```bash
curl http://localhost:8080/api/articles \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Testing

Run the test suite:
```bash
php bin/phpunit
```

Run tests with coverage:
```bash
php bin/phpunit --coverage-html coverage/
```

## Project Structure

```
├── config/              # Configuration files
│   ├── packages/        # Bundle configurations
│   ├── routes/          # Routing configuration
│   └── services.yaml    # Service definitions
├── migrations/          # Database migrations
├── public/              # Web server document root
│   └── index.php        # Front controller
├── src/
│   ├── Entity/          # Doctrine entities
│   ├── Repository/      # Doctrine repositories
│   └── Kernel.php       # Application kernel
├── tests/               # Automated tests
│   └── Api/             # API functional tests
├── var/                 # Cache and logs
├── vendor/              # Composer dependencies
├── .env                 # Environment configuration
├── composer.json        # Composer dependencies
├── docker-compose.yaml  # Docker services
└── phpunit.dist.xml     # PHPUnit configuration
```

## API Resources

### Article

Represents a news article for verification.

**Endpoints:**
- `GET /api/articles` - List all articles
- `POST /api/articles` - Create a new article
- `GET /api/articles/{id}` - Get a specific article
- `PUT /api/articles/{id}` - Update an article
- `PATCH /api/articles/{id}` - Partially update an article
- `DELETE /api/articles/{id}` - Delete an article

**Properties:**
- `id` (integer, read-only) - Article ID
- `url` (string, required) - Article URL
- `title` (string, required) - Article title
- `content` (text, required) - Article content
- `verificationStatus` (string) - Status: pending, verified, fake, misleading, true
- `verifiedAt` (datetime) - When the article was verified
- `createdAt` (datetime, read-only) - When the article was created

## Development

### Code Style

This project follows Symfony coding standards.

### Adding New Entities

Generate a new entity:
```bash
php bin/console make:entity
```

Create migration:
```bash
php bin/console make:migration
```

Run migrations:
```bash
php bin/console doctrine:migrations:migrate
```

### Adding Tests

Create test files in `tests/` directory following the existing structure.

## License

MIT
