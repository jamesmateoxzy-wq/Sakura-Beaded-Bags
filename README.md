# Sakura Beaded Bags

A college web-development project for Sakura Beaded Bags, built with **HTML, CSS, JavaScript, PHP, and MySQL**.

## Project status

**Planning / beginning software development.** The team has completed UI/UX design and wireframes, prepared the database, and is revising the ERD. Application coding has not started yet.

The current UI designs focus on an **admin/business management system**. Whether a customer-facing storefront is required is **not yet confirmed**; the `customer/` directory is reserved for that possibility and is not a commitment to build it.

### Admin modules shown in the current designs
- Dashboard
- Order management
- Product costing and pricing
- Inventory management
- Payments and sales
- Financial reports

## Technology
- Frontend: HTML, CSS, JavaScript
- Backend: PHP (no framework chosen)
- Database: MySQL
- Collaboration: GitHub

## Repository structure

```text
admin/           Admin interface and related PHP pages (to be implemented)
customer/        Reserved for a storefront if requirements confirm it
assets/
  css/           Shared stylesheets
  js/            Shared JavaScript
  images/        Static website images
includes/        Reusable PHP files and local configuration (later)
database/        SQL schema and database setup documentation
docs/            Requirements, planning, and team workflow notes
```

## Getting started

1. Clone the repository: `git clone https://github.com/jamesmateoxzy-wq/Sakura-Beaded-Bags.git`
2. Install a local PHP and MySQL environment, such as XAMPP.
3. Read [the project overview](docs/PROJECT_OVERVIEW.md), [team workflow](docs/TEAM_WORKFLOW.md), and [database notes](database/README.md).
4. Agree on the exact features, ERD, database schema, and first sprint before starting development.
5. Work on separate feature branches and open pull requests rather than pushing unfinished work directly to `main`.

> **Important:** This repository is a starter structure only. There is no working website or committed database schema yet. Never commit passwords, real customer data, or local secrets.

## Team
- James — Scrum Master; previously responsible for the database
- Gab — Previous Scrum Master; documentation
- Rags — UI/UX design
- Tribs — ERD revision
- Diz — Wireframe

Development task ownership and the Product Owner are still to be confirmed by the team.
