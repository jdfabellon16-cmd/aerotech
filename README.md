# AeroTech Solutions IT Asset Management

AeroTech's asset operations portal provides authenticated REST APIs and a React single-page application for procurement, asset intake, assignment, transfers, maintenance, software licenses, and lifecycle/disposal workflows.

> **Framework version:** this repository currently targets Laravel 13 (PHP 8.3+), rather than Laravel 11. The project was kept on its existing framework version.

## Technology

- Laravel 13, PHP 8.3+, Eloquent, Sanctum personal access tokens, and Spatie Laravel Permission
- MariaDB 11 for production; SQLite is configured for local development and automated tests
- React 18, Vite, React Router, TanStack Query, Axios, Tailwind CSS, React Hook Form, and Zod
- Database-backed queues and notifications, daily scheduled reminders, and DomPDF reports

## Requirements

- PHP 8.3+ with the PDO SQLite extension (and PDO MySQL for MariaDB)
- Composer
- Node.js and npm
- MariaDB 11 for a MariaDB-backed deployment (optional for local SQLite development)

## Local setup

From the repository root:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

The example environment uses SQLite. Create the local database file if it does not exist, then migrate and seed:

```powershell
if (-not (Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite }
php artisan migrate --seed
```

To use MariaDB instead, create an empty database and set the following values in `.env`:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aerotech
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Then run `php artisan migrate --seed`.

Install frontend dependencies and start the application and asset compiler in separate terminals:

```powershell
npm install
npm run dev
```

```powershell
php artisan serve
```

Open `http://127.0.0.1:8000`. The React application is served by Laravel on the same origin as the API.

### Queue and scheduler

Notifications are queued to the configured queue connection. For local development, keep a queue worker and the scheduler running in separate terminals:

```powershell
php artisan queue:work
```

```powershell
php artisan schedule:work
```

The reminder job runs daily at 08:00 in `Asia/Manila`. Configure a production cron entry to run `php artisan schedule:run` every minute and use a persistent queue worker. Set the mail environment variables to deliver emails; the example `.env` logs mail instead.

### Demo accounts

The seeder creates role-specific demo users. Each seeded account has the password `password`; use only in a local development environment.

| Persona | Email |
| --- | --- |
| IT Asset Manager | `it-asset-manager@aerotech.example` |
| Help Desk Technician | `help-desk-technician@aerotech.example` |
| IT Operations Manager | `it-operations-manager@aerotech.example` |
| Department Manager | `department-manager@aerotech.example` |
| Finance Manager | `finance-manager@aerotech.example` |
| General Manager | `general-manager@aerotech.example` |
| Purchasing Officer | `purchasing-officer@aerotech.example` |
| Warehouse Supervisor | `warehouse-supervisor@aerotech.example` |
| HR | `hr@aerotech.example` |
| Procurement Staff (read-only assets) | `procurement-staff@aerotech.example` |
| Employee/Requestor | `employee-requestor@aerotech.example` |

## Tests and build

```powershell
php artisan test
npm test
npm run build
```

The feature tests cover purchase request approval/rejection and resubmission, PO receiving and duplicate asset tags, license seat limits, and warranty checks. The Vitest suite covers frontend asset status presentation.

## Main workflows

1. **Procurement and intake:** purchase requests move through department, finance, and general management approvals; rejections need a reason and can be resubmitted. Purchasing tracks POs, and warehouse receiving creates audited asset records from PO items while enforcing unique tags and serials.
2. **Assignment and check-out:** employees request standard equipment; technicians validate policy and availability, record physical/courier handoff evidence, and generate accountability forms. The assigned employee must acknowledge before check-out completes; active assignments can be checked in.
3. **Transfers:** users request permanent or temporary moves. Temporary loans are limited to one month and can be constrained by an expected replacement date. Approval is followed by recipient acceptance and a location-change log.
4. **Maintenance:** incident tickets support assessment checklists, warranty checks, paid-repair approval, repair records, and completion.
5. **Software licenses:** requests, approvals, purchasing records, seat-limited assignment, compliance-check history, and two-stage renewal review.
6. **Retirement and disposal:** lifecycle scans flag assets by configured age and repair history. Disposal requires finance and management approvals and records data-wipe completion, certificate, and recipient/vendor before the asset is retired.

Available report endpoints currently export the asset register as CSV and PDF. Dashboard and module APIs provide the related operational summaries and queues.

## Roles and authorization

Roles are seeded in `RolesAndPermissionsSeeder`. API access uses permissions rather than trusting frontend navigation; requests must be authorized by the current user. Purchasing Officers and Procurement Staff receive asset read access without asset-management permissions. Employees can only perform user-owned actions such as acknowledging their own check-out or accepting a transfer addressed to them.

## Documentation

- [API route list and authentication](docs/API.md)

## Integration readiness

Directory, purchasing, and identity concerns are separated behind API controllers, models, and services to support later HR synchronization, PO import, and directory/LDAP integrations. The current directory, supplier, and role data are demo-seeded; external integrations are not connected.
