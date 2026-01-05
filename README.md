# Laravel API Boilerplate

A robust starter template designed to streamline RESTful API development with Laravel 12. This boilerplate comes pre-configured with essential tools and libraries to ensure code quality, documentation, and maintainability.

## 🚀 Features

- **Framework**: [Laravel 12](https://laravel.com)
- **API Documentation**: Automated docs via [Scribe](https://scribe.knuckles.wtf/)
- **Roles & Permissions**: Manage access control with [Laratrust](https://laratrust.santigarcor.me/)
- **Data Transfer Objects**: Type-safe data handling using [Spatie Laravel Data](https://spatie.be/docs/laravel-data)
- **Code Quality**: Automated code styling with [Laravel Pint](https://laravel.com/docs/pint)
- **Testing**: Comprehensive testing suite with [PHPUnit](https://phpunit.de/)

## 🛠 Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd laravel-api-boilerplate
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Setup**
   Configure your database credentials in `.env`, then run:
   ```bash
   php artisan migrate --seed
   ```

## ⚡ Usage

### Development Server
Start the local development server:
```bash
composer run dev
```
Or separately:
```bash
php artisan serve
```

### Running Tests
Execute the test suite using PHPUnit:
```bash
php artisan test
```

### Code Formatting
Fix code style issues using Laravel Pint:
```bash
./vendor/bin/pint
```

### Generating API Documentation
Generate static HTML documentation:
```bash
php artisan scribe:generate
```

## 📦 Key Packages

- `knuckleswtf/scribe`: Generates API documentation from your code.
- `santigarcor/laratrust`: Handles roles and permissions.
- `spatie/laravel-data`: Powerful data transfer objects.

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).