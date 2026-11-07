# Database Setup

MySQL is the intended database. The team reported a database already prepared, but the reviewed ERD and an approved schema have **not** yet been committed here.

## Before committing the schema
1. Compare the existing tables against the latest ERD.
2. Export **structure only** (no customer records or credentials) from MySQL/phpMyAdmin into `schema.sql`.
3. Review primary keys, foreign keys, types, and constraints with the team.
4. Commit the agreed schema and document how to import it locally.
5. Use sample/seed data only if clearly fictional.

Never commit local passwords, private data, or unreviewed database dumps.
