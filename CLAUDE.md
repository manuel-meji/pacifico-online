# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Pacíficos Online is a multi-merchant e-commerce platform built as a university course project (UCR IF6100, II Ciclo 2026). One Laravel 13 / PHP 8.3 repo is shared by 7 subgroups (SG1–SG7, one module each) plus an Architecture Committee that owns the shared core (`Nucleo`). Code, comments, identifiers and docs are in Spanish; follow that.

Laravel Boost is already installed. Its generated guidelines (PHP style, Pint, PHPUnit, artisan usage, Boost MCP tools) live in `AGENTS.md`, and its skills in `.agents/skills/`. Follow them. UI work must follow `design.md`: no emojis as icons; use Lucide or inline SVG.

## Commands

```bash
composer install && npm install        # deps (PHP/Composer come from Laragon: C:\laragon\bin\...)
php artisan serve                      # dev server on :8000
composer run dev                       # artisan dev (server + vite)
npm run build                          # vite build (Tailwind 4)

php artisan test --compact                               # all tests (phpunit.xml: sqlite :memory:)
php artisan test --compact tests/Feature/M1_Portal/DoblesCoherenciaTest.php
php artisan test --compact --filter=test_el_modo_real_entrega_el_servicio_del_proveedor
php artisan test --filter=M4_Inventario                  # one module's tests

vendor/bin/pint --dirty --format agent                   # format changed PHP files
php artisan route:list --path=m4
```

### Database: shared, never reset

`.env` points to a single **PostgreSQL database on Supabase that all subgroups share** (Transaction Pooler, port 6543).
- **Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback` or `db:wipe`** (`docs/GUIA_DESARROLLO_LARAVEL.md` still lists `migrate:fresh`; ignore that row). Doing so destroys every team's data.
- To run new migrations: temporarily set `DB_PORT=5432` (Session mode), run `php artisan migrate`, then switch back to 6543.
- Some migrations are PostgreSQL-only. The M4 migrations use `DB::unprepared` with plpgsql triggers, so they won't run on the sqlite test database.
- Migrations in `database/migrations/<Module>/` subfolders only run if a provider registers them with `loadMigrationsFrom`. Right now `AppServiceProvider` registers only `M5_Pedidos`.
- `db/<Module>/` holds reference SQL schemas from the design deliverables. These are not executed.

## Architecture: contract-driven modular monolith

Modules: `M1_Portal`, `M2_Comercios`, `M3_Catalogo`, `M4_Inventario`, `M5_Pedidos`, `M6_Pagos`, `M7_Contabilidad`, `Nucleo`. Each one owns the same slice in every layer:

| Layer | Location |
|---|---|
| Routes | `routes/modules/<mN_name>.php`, mounted in `routes/web.php` with prefix `/mN` and name prefix `mN.` |
| Thin controllers | `app/Http/Controllers/<Module>/` |
| Public PHP contract | `app/Contracts/<Module>/*Interface.php` |
| OpenAPI contract | `contracts/<mN-name>/v1/openapi.yaml` (kebab-case) |
| Business logic | `app/Services/<Module>/` |
| Private Eloquent models | `app/Models/<Module>/` |
| Blade views | `resources/views/<mN-name>/` (kebab-case) |

`.github/CODEOWNERS` assigns each module's folders to that subgroup's coordinator. Keep changes inside the module you were asked to work on.

### Mandatory cross-module rules (graded)

- **No cross-module data access.** Never JOIN another module's tables, and never import another module's Eloquent models. Other modules are reached only through their `app/Contracts/...` interface, injected via the container. Foreign keys to other modules' tables are allowed in migrations, but reads still go through the contract.
- **Single data owner.** Only the owning module writes to its tables.
- **Contract first, versioned.** A module publishes its interface, OpenAPI spec and test doubles before implementing. Breaking interface changes need a new contract version and Committee approval, so don't silently change another module's interface. M4 handles extension by adding `InventarioModuleInterface` alongside the old interface, so existing M1/M5 consumers keep working.
- **RN-12 tenant isolation:** a merchant (`comercio`) must never see another merchant's data. This is planned as a `Nucleo` global scope on `comercio_id`.
- **RN-04 stock concurrency:** M4 reservations use `DB::transaction` + `lockForUpdate()` with a TTL (default 15 min, `M4_RESERVA_MINUTOS`) and an idempotency key. See `docs/adr/`.

### Wiring: real services vs. test doubles

- `AppServiceProvider` binds each module's `*ServiceInterface` to its real `*Service`.
- Modules that consume others can switch each dependency between a test double and the real provider through `config/integraciones.php`, keyed by consumer and then provider:
  - **M1** (`M1IntegracionesServiceProvider`): contextual binding (`when(CONSUMIDORES_M1)->needs(...)`), so only M1 services get doubles. Doubles live in `app/Services/M1_Portal/Dobles/` and are scoped per request. Env vars: `M1_M2_MODO`, `M1_M3_MODO`, …
  - **M4** (`M4IntegracionesServiceProvider`): M4 declares its *own* narrow dependency interfaces in `app/Contracts/M4_Inventario/Dependencias/`. It binds them to either a `Dobles/*Stub` or a `Dependencias/*Real` adapter that calls the provider's PHP interface in-process. Env vars: `M4_M2_MODO`, `M4_M3_MODO`, `M4_NUCLEO_MODO`. The provider also registers M4's API routes under `/api/m4/v1` (`routes/modules/m4_api.php`). See `docs/despliegue/M4_despliegue_minimo.md`.
  - `modo` must be `'doble'` (default) or `'real'`. Any other value throws.
- New providers must be added to `bootstrap/providers.php`. Requests to `api/*` render exceptions as JSON (`bootstrap/app.php`).
- Tests swap behaviour by overriding config, e.g. `config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class])`, using failure-variant doubles in `tests/Doubles/<Mn>/`. Mockery on the contract interface is the other accepted pattern (`docs/GUIA_DESARROLLO_LARAVEL.md` §4).

### End-to-end purchase flow (validated at the final defense)

M1 search → M3 product detail → M5 cart/order → M4 `solicitarReserva` → M6 payment (idempotent) → M5 marks paid → on dispatch M4 `confirmarSalidaPorDespacho`, M7 `registrarAsientoDespacho`, M2 `registrarComision`. Inter-module changes should keep this path working.

## Other directories

- `frontend/` is a standalone React + Tailwind prototype for M5 screens with its own `package.json`. It is not wired into Laravel. See `frontend/README.md`.
- `docs/` holds the architecture doc (`ARQUITECTURA.md`), the dev guide, ADRs (`docs/adr/`), formal change requests (`docs/solicitudes-cambio/SC-NN-*.md`, from template `SC-00`), and committee and subgroup minutes. Contract changes are normally backed by an SC or ADR.
