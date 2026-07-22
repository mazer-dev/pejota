# PeJota

**ERP and CRM for solo entrepreneurs and freelancers.**

PeJota puts the whole business side of working for yourself in one place: clients and vendors,
projects, tasks, tracked work time, contracts, invoices, recurring costs and notes. It is a single
Laravel monolith with a Filament UI — no separate frontend to deploy, no services to orchestrate —
so you can self-host it on a small VPS in a few minutes.

Built with PHP, Laravel, FilamentPHP, Livewire and SQLite / MySQL / PostgreSQL.

![PeJota](https://github.com/user-attachments/assets/b859236d-6511-4e2f-96ad-b6278b57ab5d)

---

> ## ☁️ Rather not run a server? Use **[pejota.app](https://pejota.app)**
>
> **PeJota Cloud** is the hosted edition: the same product, installed, updated and operated for you.
> Sign up, invite your team and start working — no VPS, no `composer install`, no upgrades to babysit.
> Free trial, no credit card required.
>
> **→ [See what you get on PeJota Cloud](#pejota-cloud)**

---

## Features

### Daily work

- **Tasks** — organized by client and/or project, with custom statuses, priority, due date,
  planned vs. actual dates, estimated effort, tags, description and comments. Supports subtasks,
  postponing, task history, and grouping the list by client, project or due date. Global search
  included.
- **Recurring tasks** — define a recurrence (frequency, anchor date, generation mode and stop
  condition) and PeJota creates each occurrence for you on schedule.
- **Daily checks** — habit-style continuous tasks you tick off each day, with streak tracking and a
  dedicated dashboard widget.
- **Work sessions** — start and stop a timer straight from the top bar, tied to a client, project or
  task. Duration is calculated for you, and running sessions stay visible while you work.
- **Projects** — description, tags, active flag, and an optional client.
- **Clients & contacts** — client register with contact people, linked to projects, contracts, tasks
  and work sessions.
- **Vendors** — the same for who you buy from, linkable to contracts and projects.
- **Notes** — a block-based editor mixing links, code snippets (with language highlighting),
  Markdown, rich text and plain text in the same note. Taggable and searchable.

### Finance

- **Invoices** — line items pulled from your product catalog, linked to client, project or contract,
  with discounts, totals, status tracking and PDF export. Send them by e-mail from inside the app,
  with per-invoice delivery status. Built-in filters for pending, overdue and delinquent invoices.
- **Timesheet** — turn work sessions into a client-ready report: pick a date range, group and detail
  it how you want, include or hide values, filter to billable only, then export to **PDF or CSV**.
- **Contracts** — write the full contract body in the app, link it to a client, vendor or project and
  register the signatures.
- **Products & units** — catalog of what you sell, with cost, price, unit of measure and flags for
  service and digital items. Feeds invoice line items.
- **Accounts** — financial accounts with an initial balance and opening date.
- **Subscriptions** — keep track of the recurring services *you* pay for: price, currency, billing
  period, payment method, trial end and cancellation date.
- **Multi-currency** — reference currency register plus daily exchange rates fetched automatically
  from the ECB (via Frankfurter), so amounts in foreign currencies stay honest.

### Team & multi-company

- **Multi-company** — one login, many companies. All data is scoped per company, and you switch
  between them from the tenant menu.
- **Team** — invite people to your company by e-mail, with roles and an expiring invitation flow.
- **Activity log** — record-level history of what changed and who changed it.

### Settings & personalization

- **Custom statuses** — build the workflow you actually use. Each status maps to a phase
  (to do / in progress / closed), which drives automatic date-setting on tasks.
- **Tags and units** — your own taxonomies, shared across modules.
- **Company settings** — locale, timezone, date format and currency for the whole company.
- **Company mail settings** — per-company SMTP configuration for outgoing e-mail.
- **My preferences** — per-user overrides, so people in the same company aren't forced into one
  timezone or date format.
- **Internationalization** — English, Portuguese (pt-BR) and Spanish.

### Dashboard

Widgets for invoices, tasks, work sessions, overall numbers, running sessions and today's daily
checks.

---

## PeJota Cloud

**[pejota.app](https://pejota.app)** is the officially hosted PeJota. If you'd rather spend your time
on billable work than on a server, this is the shortcut.

### What Cloud adds on top of self-hosting

- **Nothing to install or maintain** — no VPS, no PHP version juggling, no web server, no cron.
- **Always up to date** — new features and fixes reach your account without you running a single
  migration.
- **E-mail that just works** — invoice delivery and team invitations are configured out of the box;
  no SMTP credentials to hunt down.
- **Free trial, no credit card** — create your company and use it before deciding anything.
- **Self-service billing** — subscribe, change plans, update your card and download your receipts
  from the billing portal, whenever you want.
- **Teams included** — invite people to your company from day one, same as self-hosted.
- **Support from the people who build it** — you talk to the maintainers, not a ticket queue.

### Modules available on Cloud plans

Every account gets the core of PeJota — tasks, projects, clients, work sessions, invoices,
timesheet, notes, dashboard, team and settings. On top of that, the paid plans unlock:

- Contracts
- Products & units catalog
- Financial accounts
- Vendors
- Subscriptions (your recurring costs)
- Multi-currency with automatic exchange rates
- Recurring tasks

Current plan composition, limits and pricing live at **[pejota.app](https://pejota.app)**.

---

## Requirements

- PHP **8.2+**
- Composer
- Node.js **18+** and npm
- SQLite (the default — zero setup) or MySQL / PostgreSQL

## Installation

```bash
git clone https://github.com/mazer-dev/pejota.git
cd pejota

composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`. SQLite is the default and needs nothing but the file:

```bash
touch database/database.sqlite
```

For MySQL or PostgreSQL, set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and
`DB_PASSWORD` instead.

Then create the schema, build the assets and run the installer:

```bash
php artisan migrate

npm install
npm run build

php artisan pj:install
```

`pj:install` is interactive: it creates your first user, configures your company, grants the platform
super-admin role and seeds the reference currencies.

Start it up:

```bash
php artisan serve
```

The application lives at **`/app`** — with the built-in server, that is <http://localhost:8000/app>.

### Scheduled jobs

Two things run on a schedule: generating occurrences of recurring tasks (daily at 00:30) and fetching
exchange rates (daily at 06:00). In production, add Laravel's scheduler to cron:

```
* * * * * cd /path/to/pejota && php artisan schedule:run >> /dev/null 2>&1
```

In development, `php artisan schedule:work` is enough.

### Optional configuration

- `PEJOTA_REGISTRATION_PAGE` — public sign-up is **off** by default. To open registration on your
  instance, point it at a Filament registration page class, e.g.
  `PEJOTA_REGISTRATION_PAGE="Filament\Auth\Pages\Register"`.
- `PEJOTA_INVITATION_EXPIRES_DAYS` — how long an e-mailed team invitation stays valid (default `7`).

### Development

```bash
npm run dev            # Vite dev server, required for CSS/JS changes
php artisan test       # test suite
vendor/bin/pint        # code style
```

---

## Upgrading

Released versions are published on the
[GitHub releases page](https://github.com/mazer-dev/pejota/releases). The usual upgrade is:

```bash
git pull
composer install
php artisan migrate
npm install && npm run build
```

<details>
<summary>Legacy: upgrading from 0.1.0 to 0.2.0</summary>

Release 0.2.0 introduced a breaking change in the `work_sessions` table migration. Because SQLite is
limited when altering columns, the database has to be refreshed to recreate the structures — and
since the migration files themselves changed, these steps are required on MySQL and PostgreSQL too.

1. Back up your database.
2. Export (dump) **only the data**.
3. Run `php artisan migrate:refresh`.
4. Import the exported data.
5. Set the new `is_running` field on existing records:

   ```sql
   UPDATE work_sessions SET is_running = 0;
   ```

Step 5 is needed because `is_running` is new and older records have no explicit value for it.

</details>

---

## How to contribute

- Browse the [issues list](https://github.com/mazer-dev/pejota/issues).
- Pick one labeled **ready to develop**.
- Comment on the issue saying you started it, so it can be moved to **doing** — this avoids two
  people working on the same thing.
- Follow the usual open source flow: branch, commit, pull request.
- Ask for help whenever you need it.
