# PeJota

Solo entrepreneus and Freelancer ERP and CRM.

Build on top of PHP, Laravel, FilamentPHP, Sqlite / Mysql, and other cool and technologies
that allow a quick high quality monolith to be created.

![image](https://github.com/user-attachments/assets/b859236d-6511-4e2f-96ad-b6278b57ab5d)


**PLEASE READ THE UPGRADE SECTION IF YOU ALREADY HAS PEJOTA INSTALLED**

## Installation

- Clone the repository
- Run `composer install`
- Run `cp .env.example .env`
- Run `php artisan key:generate`
- Configure your `.env` file with your database credentials
- Run `php artisan migrate`
- Run `npm install`
- Run `npm run build`
- Run `php artisan pj:install` to create the user and company records

## How to contribute

- See the issues list
- Choose one labeled as **ready to develop**
- Comment the issue informing that you started it, than I'll set it to **doing** to avoid two persons take the same issue to work on
- Follow the good pratices of open source development flows
- Ask for help if needed

## Releases

- **0.2.0 (Current)**: This release introduces a breaking change related to the migration of the `work_sessions` table. Please refer to the "Upgrades" section for detailed instructions.
- **0.1.0**: The initial release, which includes a breaking change in compatibility with subsequent commits to the main branch.

## Upgrades

### Upgrading from 0.1.0 to 0.2.0

The 0.2.0 release introduces a breaking change in the migration of the `work_sessions` table. Due to limitations in SQLite when altering fields, it is necessary to refresh the database to recreate all structures. Even if you are using MySQL or PostgreSQL, these steps are required because the migration files have changed.

#### Upgrade Steps:

1. Create a backup of your database.
2. Export (dump) only the data.
3. Run `php artisan migrate:refresh`.
4. Import the exported data.
5. Update the `work_sessions` records to set the `is_running` field to 0 by running:
   ```sql
   UPDATE work_sessions SET is_running = 0;
   ```
   
This step is required because the is_running field is new, and previous records did not have this value explicitly set.

## Features

### Clients

Simple clients register to associate with Projects, Contracts, Tasks and Work Sessions.

### Vendors

Simple vendors register to associate with Projects, Contracts, Tasks and Work Sessions.

### Projects

Simple projects register, with description, tags and active status.

Each project can be associated with one or none client.

### Contracts

Simple contract management, that can be associated with a vendor or project. You can write the whole content in it and set the signatures.

### Tasks

Tasks module with tasks control, by client and/or project, with data for planned and actual dates, also with dedicated due date information.

Data that can be associated: tags, description, estimated effort (min or hours)

Table data grouped by clients, due date, projects.

Global searh of tasks.

### Work Sessions

Control sessions of work, related to client / project / task.

Each session has a start and end period, used to calculate the duration.

### Invoices

Simple invoice management, with the ability to export to PDF.

### Notes

Save Links, Rich Text, Markdown, Plain text notes and Code Snippets.

### Subscriptions

Control your subscriptions, with a simple way to manage the subscriptions you have.

### Settings

#### Statuses

Create your own status to better control your workflow.

#### Company

General settings of system use by your company.
