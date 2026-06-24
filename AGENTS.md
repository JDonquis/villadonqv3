# AGENTS.md — VillaDonq V2

Laravel 10 + Inertia.js + Svelte 4 + Vite + Tailwind CSS

## Commands

```bash
composer install          # PHP deps
yarn install              # Node deps (yarn, not npm)
php artisan key:generate  # after copying .env
yarn run build            # production assets (Vite)
yarn run dev              # dev with HMR
php artisan serve         # http://localhost:8000
php artisan migrate       # DB setup (MySQL default)
vendor/bin/phpunit        # or: php artisan test
```

## Architecture

- **Inertia + Svelte**: not Blade — pages are `.svelte` files in `resources/js/Pages/`, served via Inertia responses from controllers
- **Entrypoints**: `resources/js/app.js`, `resources/css/app.css` (Vite config)
- **Routes**: `routes/web.php` — all dashboard routes wrapped in `auth` middleware
- **Controllers**: `app/Http/Controllers/` — main modules: Auth, User (personal), Student (matrícula), Payment, Section, MainConfig

## Gotchas

- `Payment` model uses `PaymentObserver` (registered in `AppServiceProvider`) — side effects on create/update/delete
- Test env uses `array` cache/mail, `sync` queue (see `phpunit.xml`)
- SQLite available for testing: uncomment `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` in phpunit.xml
- Routes and Svelte pages are in Spanish (e.g., `/dashboard/matricula`, `Matricula.svelte`)
