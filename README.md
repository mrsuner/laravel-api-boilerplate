# Laravel API Boilerplate

A robust starter template designed to streamline RESTful API development with Laravel 12. This boilerplate comes pre-configured with essential tools and libraries to ensure code quality, documentation, and maintainability.

## Why another boilerplate?

Recently I have discovered some baas services and project, e.g. supabase, firebase, pocketbase and Tailbase. They are great and can help developers a lot to sketch up a prototype quickly.

However, AI has been bring in a huge changes with the development workflow, and those baas services' advantages are becoming less obvious. With AI, developers can generate code snippets, database schemas, and even entire modules in seconds. This significantly reduces the time and effort required to build backend services from scratch. And the most important part is that Laravel provides a clean and elegant syntax, making it easier to read and maintain the code, a better development experience and native scalability -- you don't need to worry about outgrowing the platform's limitations and vendor lock-in.

This boilerplate is target to let any developers familiar with Laravel to quickly start a new API project with best practices and essential tools pre-configured, so they can focus on building features and delivering value. it may not suit for all scenarios, but it's a better replacement for those BaaS services for most projects.

## 🚀 Features

- **Framework**: [Laravel 12](https://laravel.com)
- **Authentication**: Dual token + cookie auth (Sanctum), password / OTP / OAuth, signed-URL email verification
- **Configurable security**: per-endpoint rate limits, `Password::defaults()` policy, login email-verification gate
- **Standard API envelope**: base `Controller` response helpers plus a global exception → JSON renderer
- **API Documentation**: Automated docs via [Scribe](https://scribe.knuckles.wtf/)
- **Roles & Permissions**: Manage access control with [Laratrust](https://laratrust.santigarcor.me/)
- **File uploads**: Two-phase upload with TTL + scheduled cleanup; works with `local` or `s3`
- **Data Transfer Objects**: Type-safe data handling using [Spatie Laravel Data](https://spatie.be/docs/laravel-data)
- **Code Quality**: Automated code styling with [Laravel Pint](https://laravel.com/docs/pint)
- **Testing**: Comprehensive testing suite with [PHPUnit](https://phpunit.de/)
- **AI integration**: Laravel Boost MCP and shared agent rules (`CLAUDE.md` / `AGENTS.md` / `GEMINI.md` / `.cursor/rules` / `.github/copilot-instructions.md`)

## 📚 Documentation

Per-feature docs live in [`docs/`](docs/README.md). The index links every module — start there or jump straight to what you need:

| Area | Doc |
|---|---|
| Auth overview & route reference | [docs/authentication.md](docs/authentication.md) |
| OTP / passwordless | [docs/otp.md](docs/otp.md) |
| OAuth / social login | [docs/social-auth.md](docs/social-auth.md) |
| Email verification | [docs/email-verification.md](docs/email-verification.md) |
| Password policy | [docs/password-policy.md](docs/password-policy.md) |
| Rate limiting | [docs/rate-limiting.md](docs/rate-limiting.md) |
| API responses & exception envelope | [docs/api-responses.md](docs/api-responses.md) |
| RBAC (roles & permissions) | [docs/rbac.md](docs/rbac.md) |
| File uploads (TTL + cleanup) | [docs/files.md](docs/files.md) |
| Auth event email notifications | [docs/notifications.md](docs/notifications.md) |
| Roadmap memo | [docs/devlog/2026-05-10-boilerplate-roadmap.md](docs/devlog/2026-05-10-boilerplate-roadmap.md) |

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