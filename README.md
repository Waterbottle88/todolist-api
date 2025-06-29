# Todo List API

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-orange.svg)](https://mysql.com)
[![Redis](https://img.shields.io/badge/Redis-7.x-red.svg)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)](https://docker.com)
[![API](https://img.shields.io/badge/API-RESTful-green.svg)](https://restfulapi.net)

A production-ready RESTful API for task management built with Laravel 12, implementing modern PHP development practices and enterprise-grade architecture patterns.

## Features

- **RESTful API Design** with semantic versioning (v1)
- **Token-based Authentication** using Laravel Sanctum
- **Comprehensive Task Management** including CRUD operations, hierarchical tasks, and completion tracking
- **Enterprise Architecture** featuring DTOs, Service Layer, Repository pattern, and comprehensive validation
- **Standardized API Responses** through custom middleware for consistent formatting and error handling
- **Complete Test Coverage** with feature and unit tests
- **API Documentation** auto-generated using Swagger/OpenAPI
- **Containerized Development** with Docker and Laravel Sail support

## System Requirements

### Prerequisites
- PHP 8.2 or higher
- Composer (latest stable version)
- Node.js 18+ and npm
- MySQL 8.0 or MariaDB 10.5+
- Redis 7.x (recommended for caching and sessions)
- Docker (optional but recommended for development)

## Installation

### Docker Installation (Recommended)

```bash
# Clone the repository
git clone <repository-url> todo-api
cd todo-api

# Configure environment
cp .env.example .env

# Install project dependencies
composer install
npm install

# Initialize Docker environment
./vendor/bin/sail up -d

# Configure application
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# Application will be available at http://localhost
```

### Manual Installation

```bash
# Ensure PHP 8.2+, MySQL, and Redis are running
composer install
npm install

# Configure environment variables
cp .env.example .env
php artisan key:generate

# Initialize database
php artisan migrate --seed

# Start development environment
composer dev
```

**Note:** The `composer dev` command starts the Laravel development server, queue worker, log monitoring, and Vite asset compilation concurrently.

## Development Commands

### Application Management
```bash
# Start development environment (server, queue, logs, Vite)
composer dev

# Run test suite
composer test

# Alternative test execution
php artisan test

# Build production assets
npm run build
```

### Code Quality and Documentation
```bash
# Generate API documentation
php artisan l5-swagger:generate

# Format code according to PSR-12 standards
./vendor/bin/pint

# List API routes
php artisan route:list --path=api
```

## API Endpoints

### Health Check
```
GET /api/health
```

### Authentication
```
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout      # Requires auth
POST /api/v1/auth/logout-all  # Requires auth
GET  /api/v1/auth/me          # Requires auth
```

### Tasks
```
GET    /api/v1/tasks           # List all tasks
POST   /api/v1/tasks           # Create task
GET    /api/v1/tasks/{id}      # Get task
PUT    /api/v1/tasks/{id}      # Update task
DELETE /api/v1/tasks/{id}      # Delete task
PATCH  /api/v1/tasks/{id}/complete  # Mark as complete

GET    /api/v1/tasks/stats     # Get task statistics
GET    /api/v1/tasks/search    # Search tasks
GET    /api/v1/tasks/{id}/children  # Get subtasks
```

### User
```
GET /api/v1/user  # Get current user info
```

## Authentication

The API implements token-based authentication using Laravel Sanctum.

### Authentication Flow

1. **Registration/Login**: Obtain an access token through the authentication endpoints
2. **Token Usage**: Include the token in the Authorization header for protected endpoints
3. **Logout**: Revoke the token when the session ends

### Implementation Example

```bash
# User registration
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john.doe@example.com","password":"password","password_confirmation":"password"}'

# Authenticated request
curl -X GET http://localhost/api/v1/tasks \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json"
```

## Testing

The application includes comprehensive test coverage for all API endpoints and core functionality.

### Test Execution

```bash
# Execute complete test suite
composer test

# Run specific test files
php artisan test tests/Feature/Api/V1/TaskTest.php
php artisan test tests/Feature/Api/V1/AuthTest.php

# Generate coverage report (requires Xdebug)
php artisan test --coverage
```

### Test Utilities

The `tests/TestCase.php` provides authentication helpers:
- `authenticatedUser()` - Creates and authenticates a test user
- `createAuthenticatedApiUser()` - Returns user instance, token, and formatted headers
- `apiHeaders($token)` - Generates standard API request headers

## API Documentation

API documentation is generated using L5-Swagger (OpenAPI/Swagger specification).

### Documentation Generation

```bash
# Generate OpenAPI documentation
php artisan l5-swagger:generate

# Access documentation
# Swagger UI: http://localhost/api/documentation
# JSON specification: storage/api-docs/api-docs.json
```

### Response Format

All API responses follow a standardized format:

```json
{
  "success": true,
  "status_code": 200,
  "data": { ... },
  "meta": {
    "api_version": "v1",
    "timestamp": "2024-01-01T12:00:00Z",
    "request_id": "unique-identifier",
    "execution_time": 45.67
  }
}
```

## Project Architecture

### Directory Structure

```
app/
├── DTOs/                 # Data Transfer Objects
├── Enums/                # Application enumerations
├── Http/
│   ├── Controllers/Api/  # RESTful API controllers
│   ├── Middleware/       # Custom HTTP middleware
│   ├── Requests/         # Form request validation
│   └── Resources/        # API response resources
├── Models/               # Eloquent ORM models
├── Repositories/         # Data access abstraction
├── Services/             # Business logic layer
└── Rules/                # Custom validation rules

tests/
├── Feature/Api/          # API integration tests
└── Unit/                 # Unit tests
```

### Architecture Patterns

- **Repository Pattern**: Abstracts data access logic
- **Service Layer**: Encapsulates business logic
- **Data Transfer Objects**: Type-safe data transfer
- **Form Request Validation**: Centralized input validation
- **API Resources**: Consistent response transformation

## Key Features

### Technical Excellence
- **Custom Middleware**: Implements consistent error handling and response formatting
- **Clean Architecture**: Employs service layer, repository pattern, and DTOs beyond basic MVC
- **Robust Validation**: Utilizes form requests with custom validation rules
- **Complete Test Coverage**: Feature tests for all API endpoints
- **Development Efficiency**: Hot reload, concurrent process management, comprehensive logging
- **Production Readiness**: Rate limiting, error handling, and security best practices

### Enterprise Standards
- **PSR-12 Compliance**: Follows PHP coding standards
- **Type Safety**: Strict typing throughout the application
- **Documentation**: Comprehensive inline documentation
- **Error Handling**: Custom exceptions with appropriate HTTP status codes

## Troubleshooting

### Common Issues and Resolutions

**PHP Version Compatibility**
Ensure PHP 8.2+ is installed. Consider using Docker/Laravel Sail for consistent environment management.

**Database Connection Failures**
Verify database credentials in the `.env` configuration file match your database server settings.

**Test Suite Failures**
Confirm that a dedicated `testing` database is configured and accessible.

**Cross-Origin Resource Sharing (CORS)**
Verify that your frontend application URL is properly configured in `config/cors.php`.

**Laravel Sanctum Configuration**
Ensure `SANCTUM_STATEFUL_DOMAINS` environment variable is correctly set for your deployment domain.

## Contributing

### Development Workflow

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/feature-name`)
3. Execute test suite (`composer test`)
4. Commit changes with descriptive messages
5. Push to your feature branch
6. Submit a Pull Request

### Code Standards
- Follow PSR-12 coding standards
- Maintain comprehensive test coverage
- Update documentation for new features
- Use meaningful commit messages following conventional commit format

## License

This project is licensed under the MIT License. See the LICENSE file for complete terms and conditions.

---

**Support**: For issues and debugging, monitor application logs using `php artisan pail` or `./vendor/bin/sail artisan pail`.