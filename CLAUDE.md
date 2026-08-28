# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 12 + Inertia.js (Vue 3 + TypeScript) ERP for a company running dining/catering services at mining sites. It covers menu planning, nutritional/recipe management, purchasing, inventory (clothing/EPP/equipment), staff, POS/sales, and reporting. Backend is a classic Laravel monolith (controllers return `Inertia::render(...)`), no separate API/SPA split.

## Commands

**Full local dev stack** (server + queue worker + Vite):

```
composer dev
```

**With SSR:**

```
composer dev:ssr
```

**Backend tests** (Pest, run via PHPUnit):

```
composer test
# or directly:
php artisan test
php artisan test --filter=SaleTest        # single file/test
```

**Frontend:**

```
npm run dev          # Vite dev server only
npm run build         # production build
npm run build:ssr     # build + SSR bundle
npm run lint           # eslint --fix
npm run format         # prettier --write resources/
npm run format:check
```

There is no `npm test` / JS test runner configured — frontend correctness is checked via `vue-tsc` (part of build) and manual verification.

## Architecture

### Domain hierarchy (org structure)

`Dealership` → `Mine` → `Unit` → `Cafe` (comedor) is the core geographic/organizational tree that most other data hangs off of. A `Subdealership` can also attach to a `Mine`. `Business` and `Service` attach polymorphically to various entities via a `Serviceable`/`serviceable_type` morph (see `App\Models\Serviceable` — used to relate `Service` to `Cafe`, `Unit`, `Mine`, etc.).

### Permissions model

Uses `spatie/laravel-permission`. Route-level authorization is _not_ done with policies/gates in the route file — instead `App\Http\Middleware\CheckRoutePermission` (registered as `check.permission`) inspects the **first URL segment** of every authenticated request, looks up `Permission` rows by `route_name`, and checks `$user->hasPermissionTo(...)`. Implications when adding routes:

- If a new top-level route prefix should be gated, a matching `permissions.route_name` row must exist (or it's implicitly open, like `dashboard`/`settings`/`profile`).
- Because gating is keyed off the first segment, routes that should be reachable by a role that doesn't own that segment are deliberately placed under a _different_ segment they do have (see the `pos/find-dinner-by-dni` comment in `routes/web.php` for a real example — don't "fix" that by moving it back under `dinners`).
- Both reads and writes are checked. Two maps in the middleware handle segments that don't line up 1:1 with a permission:
  - `SEGMENT_PERMISSION_ALIASES` — segments with **no** `permissions` row of their own (`dishes`, `mines`, `cafes`, `equipment-dispatches`, …). Applies to reads and writes. Without it these are open to any authenticated user.
  - `WRITE_PERMISSION_ALIASES` — segments that **do** own a permission but are written to from other modules' screens (POS registering a diner under `dinners`, Headcount managing `roles`/`staff`, …). Write-only **on purpose**: applying it to GET would hand a `pos` user the whole Comensales padrón. Keep that separation when adding entries.
- A segment with no permission row and no alias entry is still **fail-open** (any authenticated user). That's deliberate for loose endpoints, not an oversight — but it means adding a prefix without a permission row leaves it public-to-authenticated.
- There are **no policies/gates**: nothing in the codebase calls `authorize()`, `Gate::`, or `->can()`. The nine `App\Policies\*` stubs that used to live here were `make:policy` scaffolding whose 63 methods all returned `false`; they were never wired up and were removed. Authorization is module-level via the middleware — there is currently no per-record check anywhere.

### Frontend structure (Inertia/Vue)

- Pages live in `resources/js/pages/<feature>/*.vue` and are resolved by name from PHP (`Inertia::render('planning/Index', [...])` → `resources/js/pages/planning/Index.vue`). Keep controller `Inertia::render` calls and page file paths in sync.
- `resources/js/components/ui/*` is shadcn-vue (`components.json`, style `new-york`, baseColor `neutral`) — regenerate/extend via the shadcn-vue CLI conventions rather than hand-rolling primitives.
- Path alias `@/*` → `resources/js/*` (defined in both `vite.config.ts` and `tsconfig.json`).
- Shared Inertia props (auth user + permissions + roles, flash messages, Ziggy routes, sidebar state) are injected globally in `App\Http\Middleware\HandleInertiaRequests::share()` — any page can rely on `usePage().props.auth`, `.flash`, etc. without the controller passing them explicitly.
- Domain types used across pages (`Cafe`, `WeeklyProgram`, `DishCategory`, ...) are declared in `resources/js/types/index.d.ts`.
- Broadcasting uses Laravel Echo + Pusher, configured both in `resources/js/app.ts` (`configureEcho`) and standalone in `echo.js`.

### Excel/PDF

Imports/exports for bulk data (dishes, ingredients, recipes, nutritional factors, sales reports, purchase orders, etc.) live in `app/Imports` and `app/Exports` (`maatwebsite/excel`). PDFs (tickets, purchase orders, dispatch guides) are generated with `barryvdh/laravel-dompdf`. When adding a new bulk-import feature, follow the existing pattern of one `*Import` class per entity plus a controller `import` action rather than parsing files ad hoc in the controller.

### Naming inconsistency to be aware of

Most models use standard singular PascalCase (`Dish`, `Ingredient`), but a chunk of the food/nutrition domain uses Laravel's older snake_case convention baked into the class name itself (e.g. `Dish_category`, `Ingredient_category`, `Ingredient_city_provider`, `Dish_ingredient_level`, `Sale_detail`, `Sale_type`, `Payment_method`). These are pre-existing and intentional — don't "normalize" the class names as a drive-by change; it would break `Route::model` bindings, imports, and relations across many files.

### Tests

Pest is configured (`tests/Pest.php`) but most existing test files are plain PHPUnit-style classes extending `Tests\TestCase`. Tests run against in-memory SQLite (`phpunit.xml`). Feature tests live under `tests/Feature`, mirroring controller/domain names (`SaleTest`, `DinnerTest`, `ReportSalesTest`, etc.).

### Git workflow

After completing the update, commit the changes with a descriptive message and push them to the remote repository with a git push origin dev.
