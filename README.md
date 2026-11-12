# Swd Shop

A server-rendered PHP 8 shop for XAMPP and MySQL. The shop uses the `swd_shop`
database, PDO prepared statements, session-backed cart quantities, CSRF tokens,
and live database prices.

## Requirements

- XAMPP with PHP 8 and MySQL/MariaDB
- Apache `mod_env` enabled (included in the standard XAMPP Apache configuration)
- PDO MySQL enabled in PHP

## Setup

1. Copy this folder to `C:\xampp\htdocs\swd\09-php-shop`.
2. Create the database if needed:

   ```sql
   CREATE DATABASE swd_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Import the schema and catalog into a **new, empty** `swd_shop` database:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -p swd_shop < C:\xampp\htdocs\swd\09-php-shop\database.sql
   ```

   Enter the MySQL password when prompted (press Enter if root has no
   password), or import `database.sql` using phpMyAdmin. To populate or
   refresh the example catalog, run:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\swd\09-php-shop\seed.php
   ```

   The export creates the tables and inserts the example catalog; it does not
   drop existing tables. Do not import it into a live shop database that
   already has these tables. For an existing installation, keep its schema
   and use `seed.php` only if you want to add or refresh the example catalog.

4. Configure database access in the same Apache `<Directory>` section. The
   defaults shown below work with a local XAMPP MySQL root account that has no
   password; change the user/password to match your local setup:

   ```apache
   SetEnv SHOP_DB_HOST "127.0.0.1"
   SetEnv SHOP_DB_PORT "3306"
   SetEnv SHOP_DB_NAME "swd_shop"
   SetEnv SHOP_DB_USER "root"
   SetEnv SHOP_DB_PASSWORD ""
   ```

   These settings can also be supplied as process environment variables:
   `SHOP_DB_HOST` (default `127.0.0.1`), `SHOP_DB_PORT` (default `3306`),
   `SHOP_DB_NAME` (default `swd_shop`), `SHOP_DB_USER` (default `root`), and
   `SHOP_DB_PASSWORD` (default empty).

5. Create an administrator password hash. Use a unique password and keep it
   private:

   ```powershell
   C:\xampp\php\php.exe -r "echo password_hash('replace-with-a-long-unique-password', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Add these directives alongside the database settings in the Apache
   configuration (for example, in the existing
   `<Directory "C:/xampp/htdocs">` section in
   `C:\xampp\apache\conf\httpd.conf`):

   ```apache
   SetEnv SHOP_ADMIN_USERNAME "admin"
   SetEnv SHOP_ADMIN_PASSWORD_HASH "$2y$...paste-the-generated-hash..."
   ```

   Set `SHOP_DB_*` in the same Apache configuration when your database
   credentials differ from the defaults. Restart Apache after changing its
   configuration. Do not put a plaintext password or password hash in a
   committed file.

6. Open [http://localhost/swd/09-php-shop/](http://localhost/swd/09-php-shop/).
   Admin sign-in is at `/admin/login.php`; customers can track an order at
   `/order-lookup.php`.

The admin area uses `password_verify()` against the configured
`password_hash()` hash. If no username and valid password hash are configured,
admin sign-in remains unavailable rather than falling back to a default
credential.

## Order status workflow

Orders move from `pending` to `paid` or `cancelled`, from `paid` to `shipped`
or `cancelled`, and from `shipped` to `delivered`. Delivered and cancelled
orders are terminal. Cancelling an eligible order restores its purchased
quantities to inventory in the same transaction as the status change.

## Database export

`database.sql` contains the table definitions and example category/product
catalog. Order and order-item table definitions are included, but customer
order rows are intentionally omitted from the export to avoid distributing
personal delivery and contact information.

To re-export after changing the schema, use XAMPP's `mysqldump.exe` with
`--no-data --skip-add-drop-table` for the table definitions, then include only
the category and product catalog data. Do not export customer order rows to a
shared source repository.
