# Northstar Commerce Workspace

A PHP 8 and MySQL Northwind catalog application. The product directory supports search and uses a JSON REST-style API for listing, creating, editing, and deleting products. The Thai add/edit page follows the centered Bootstrap card style of the supplied class example while keeping categories, suppliers, and product data connected to Northwind. The dashboard and directories also show customers, orders, categories, and suppliers.

## Run locally with MAMP

1. Start Apache and MySQL in MAMP.
2. Import [`database/dbNorthwind.sql`](database/dbNorthwind.sql) into MySQL. The dump creates and selects `db_northwind`, which is also the app's default connection name.
3. The default local settings are host `127.0.0.1`, MAMP MySQL port `8889`, database `db_northwind`, username `root`, and password `root`. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the Apache environment to override them, or edit `includes/db.php` if MAMP shows a different MySQL port.
5. Open `http://localhost/webapp/`.

The app requires PHP 8.1 or newer with PDO MySQL enabled.

## Product API

All product CRUD operations in the browser use `api/products.php` and JSON. Read requests return JSON; write requests require the session CSRF token sent in the `X-CSRF-Token` header.

| Method | Endpoint | Result |
| --- | --- | --- |
| GET | `/api/products.php?q=tea` | Search/list products (up to 250) |
| GET | `/api/products.php?id=1` | Read one product |
| POST | `/api/products.php` | Create a product |
| PUT | `/api/products.php?id=1` | Update a product |
| DELETE | `/api/products.php?id=1` | Delete a product if it is not used in order history |

The UI obtains the CSRF token from the product form. Validation runs in the browser and again in PHP before the database is changed.

## Deploy on Railway

The repository contains a `Dockerfile`, `railway.json`, and a database-aware `/health.php` endpoint. Follow the step-by-step deployment and submission guide in [docs/PROJECT_DOCUMENTATION.md](docs/PROJECT_DOCUMENTATION.md).

Railway MySQL environment variables (`MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, and `MYSQLPASSWORD`) are supported directly. You can also set the corresponding `DB_*` variables.

## Project files

- `index.php` — dashboard, directories, and product interface
- `api/products.php` — JSON API and server-side validation for product CRUD
- `includes/db.php` — PDO MySQL connection
- `database/dbNorthwind.sql` — course-provided Northwind database dump
- `assets/` — styles and browser-side API integration
- `assets/product-form.css` — teacher-example-inspired add/edit form styling
- `Dockerfile`, `railway.json`, `health.php` — Railway deployment setup
- `docs/PROJECT_DOCUMENTATION.md` — process documentation and submission checklist
