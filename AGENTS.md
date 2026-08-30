# AGENTS.md — VillaDonq V3

Laravel 10 + Inertia.js + Svelte 4 + Vite + Tailwind CSS. School management app (matrícula, pagos, estados de cuenta, profesores/materias/planes de evaluación). UI and routes are in **Spanish**.

## Commands

```bash
composer install          # PHP deps
yarn install              # Node deps (yarn, NOT npm)
php artisan key:generate  # after copying .env
yarn run build            # production assets (Vite)
yarn run dev              # dev with HMR
php artisan serve         # http://localhost:8000
php artisan migrate       # MySQL DB (named `villadonq`, root/no password)
vendor/bin/phpunit        # or: php artisan test
```

Only smoke `ExampleTest`s exist — don't assume a covered test suite. Run `vendor/bin/phpunit --filter <name>` for a single test.

## Architecture

- **Inertia + Svelte, not Blade**: pages are `.svelte` files in `resources/js/Pages/`. Controllers return `Inertia::render('Dashboard/Xyz', ...)`.
- **Layout**: `resources/js/app.js` applies `DashboardLayout.svelte` when the page name starts with `Dashboard`. New dashboard pages must live under `Pages/Dashboard/`.
- **Entrypoints** (Vite inputs): `resources/css/app.css`, `resources/js/app.js`.
- **Routes**: `routes/web.php`, all behind `auth` + `role` middleware (see `CheckRole.php`). Roles = `administrator`, `representative`, `teacher` (mapped via `App\Enums\UserTypeEnum`, values 1/2/3).
- **Business logic lives in Services**, not controllers: `app/Services/*Service.php` (PaymentService, BalanceService, StudentService, AccountStatementService, UserService, TeacherService, etc.). Controllers are thin.
- **Modules** (`Dashboard/` pages ↔ controllers): Pagos, Matricula (Student), EstadosDeCuenta (AccountStatement), Personal (User), Configuracion (MainConfig), Profesores, Materias, PlanesEvaluacion, MisPagos (Representative), MisEstudiantes (StudentGrade), Secciones, Horarios (Schedule, admin), MiHorario (MySchedule, teacher).

## Payments / Balances (core, non-obvious)

- **Payments are soft-deleted via a `status` column, NOT Eloquent SoftDeletes**: `status = 1` active, `status = 0` deleted. Deletion/update sets `deleted_by` (auth user id). `Payment::update()` actually reverts balances, soft-deletes the old payment, and **creates a new payment**.
- `PaymentObserver` (registered in `AppServiceProvider`) writes a `PaymentHistory` row on create/update/delete — side effects to keep in mind.
- **Debt model**: `BalanceStudent` holds debt as *negative* fields: `inscription` + `january`..`december`. Negative = owed; positive = overpaid. One balance per student per `school_lapse_id`.
- `BalancePayment` records which payment covered which portion (month or `is_inscription`).
- **School-year month order** (current month index is resolved against this): `september → october → … → december → january → … → august`. Status per period comes from `BalanceStudentStatusEnum` (`pending`, `paid`, `debt`, `partially_paid`) computed in `BalanceService::determineMonthStatus()` using `MainConfig` monthly price, `day_of_monthly_payment`, and grace.

## Gotchas

- **Schedules (Horarios, admin-only)**: tables `schedules` (school_lapse_id, course_id, section_id, recess_start 24h `HH:MM`, recess_duration_minutes, unique combo) + `schedule_classes` (schedule_id, day 1-5, start_time/end_time 24h `HH:MM:SS`, matter_id, teacher_id, order). Data via `ScheduleService`; controller returns `Dashboard/Horarios` with `data.periods/courses/sections/course_sections/matters/teachers/filters/schedule` (schedule ghost = `{days: {1:[]..5:[]}, recess_*}`). UI stores 12h selects and converts via `to24String`/`from24String` helpers. Recess applies to all weekdays.
- **My Schedule (MiHorario, teacher-only read-only)**: `MyScheduleController@index` → `Dashboard/MiHorario`. `MyScheduleService::getIndexData(teacherId, lapse?)` lists ALL classes where that `teacher_id` has `schedule_classes` in the period (lapse `status 1` by default), grouped by day and each tagged with `course_name`/`section_name`; teacher field is omitted (it's the logged-in teacher). No course/section filter — the teacher sees their whole week, and each class card shows the section as read-only text. Teacher nav link `/dashboard/mi-horario`. Only query param is `school_lapse_id`.
- Test env (`phpunit.xml`) uses `array` cache/mail and `sync` queue. SQLite is **commented out**; uncomment `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` to run tests against SQLite instead of needing MySQL.
- `configure-scheduler.php` (repo root) sets up a platform scheduler (cron / Windows `schtasks`) to run `artisan schedule:run` every minute — touch it only to inspect scheduler wiring.
- Route ↔ page naming is Spanish and not always obvious: e.g. route `/dashboard/estados-de-cuenta` renders `EstadosDeCuenta.svelte`, `/dashboard/pagos` → `Pagos.svelte`.
- `Personal.svelte`, `Pagos.svelte`, `MisPagos.svelte` are large, frequently-edited pages.
- `opencode.md` in the repo root holds session-by-session change notes (dated) — read it for recent work context.
