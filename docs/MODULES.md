# MailBatch modules: how to add and update features

Every feature beyond the core campaign flow is a **module**: a folder in `app/Modules/{Name}/`.
Drop a folder in, run updates, and it appears in the menu with its own permissions. No core file is edited.

## Create a module in 1 minute

```bash
php artisan make:module "Email Templates" --depends=access
php artisan migrate                       # or: Admin > Modules > Run updates (no terminal needed)
php artisan test --filter=EmailTemplatesModuleTest
```

You get a working page, route, controller, view, migration and test. Open **Admin > Modules** to see it.

## Folder convention

```
app/Modules/EmailTemplates/
  Module.php                  metadata, permissions, menu, widgets, settings   (the only required file)
  Routes/web.php              signed-in routes   (middleware: web, auth, active, throttle)
  Routes/public.php           public routes      (middleware: web)  e.g. unsubscribe, tracking pixel
  Http/Controllers/  Http/Requests/
  Models/  Services/  Observers/  Console/  ...
  Resources/views/            used as view('email_templates::index')   (namespace = module key)
  Database/Migrations/        loaded automatically by `php artisan migrate`
tests/Feature/Modules/EmailTemplatesModuleTest.php
```

PHP namespace: `App\Modules\EmailTemplates\...` (already autoloaded, no composer change).

## `Module.php` reference

| Method | Purpose |
|---|---|
| `key()` / `name()` | required. key = `a-z`, `0-9`, `_` (becomes view namespace, permission prefix, settings prefix) |
| `version()` | bump it to ship an update (see *Updating*) |
| `dependsOn()` | `['access']`: module starts only when those are active; shown as "blocked" otherwise |
| `core()` | `true` = always on, cannot be disabled |
| `permissions()` | `['contacts.view' => 'View contacts']` → Gate abilities: `@can('contacts.view')`, `can:` middleware |
| `systemRoles()` | roles this module needs to exist |
| `defaultPermissions()` | `['admin' => ['contacts.*'], 'manager' => ['contacts.view']]` granted once per version. Patterns: `contacts.*`, `*`, `!contacts.delete` |
| `menu()` | sidebar entries: `label, route, icon, permission, match, group (main\|admin), section, order` |
| `widgets()` | dashboard widgets: `view, permission, col, order` |
| `settings()` | fields shown in Admin > Modules > Settings; read with `app(ModuleManager::class)->setting('key','name')` |
| `commands()` / `schedule()` | artisan commands, scheduled tasks (only run if cron exists) |
| `register()` / `boot()` | bind services / observers, listeners, route model bindings |

## Rules that keep modules independent

1. **Prefix everything with the module key**: route names (`contacts.index`), permissions (`contacts.view`), tables (`contacts`, `contact_lists`), settings.
2. **Do not edit core files.** Hook in with: menu/widgets/permissions, Eloquent observers, Laravel events.
3. **Talk to other modules through events.** To leave an audit entry:
   `ActivityRecorded::record('contacts.imported', 'Imported 5,000 contacts', $list, ['rows' => 5000]);`
   (the Activity Log module writes it; if it is disabled nothing breaks).
4. **Declare what you need** in `dependsOn()`. Never `class_exists`-check another module unless it is optional.
5. **Authorise twice**: route middleware `can:contacts.view` AND ownership/policy checks inside controllers.
6. **Never log secrets.** Activity entries drop any property whose key contains `password`, `token` or `secret`.

## Updating a module

1. Add a new migration file in `Database/Migrations/` (name it with a later timestamp).
2. Bump `version()` (e.g. `1.1.0`) and add new permissions to `permissions()` / `defaultPermissions()`.
3. Deploy, then **Admin > Modules > Run updates** (or `php artisan migrate`). New default permissions are granted once for the new version;
   permissions an admin removed earlier are not forced back.
4. Roll back with `php artisan migrate:rollback --path=app/Modules/X/Database/Migrations`.

## Enabling / disabling

Admin > Modules. Core modules (System, Access) are locked. Disabling a module hides its menu, routes, widgets and permissions;
its data stays in the database. A module that another active module needs cannot be disabled first.

## Roles and permissions

- **Super Admin** = the existing Admin flag on a user. Always has every permission.
- Other users get one role (Admin, Manager, Campaign Manager, Content Manager, Viewer, or custom). No role = Campaign Manager.
- Manage at **Admin > Roles & access**. The permission matrix lists every permission every active module declares.

## Commands

| Command | What it does |
|---|---|
| `make:module Name [--depends=a --depends=b] [--core]` | scaffold a module |
| `modules:list` | status, version, dependencies, pending migrations |
| `modules:sync` | record modules, create system roles, apply default permissions (runs automatically after every migration) |
| `activitylog:prune` | delete old activity entries |

## Hosting without a terminal / cron

- Updates: Admin > Modules > **Run updates** runs the migrations. Back up the database first.
- Scheduled tasks need one cron line; without it nothing breaks, they simply do not run.
