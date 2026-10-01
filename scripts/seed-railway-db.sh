#!/bin/sh
set -eu

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASS:?DB_PASS is required}"

export MYSQL_PWD="$DB_PASS"

attempt=0
until mysqladmin --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" ping --silent >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Railway MySQL did not become ready in time." >&2
        exit 1
    fi
    sleep 2
done

if mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --database="$DB_NAME" \
    --batch --skip-column-names --execute="SHOW TABLES LIKE 'tb_products'" | grep -q 'tb_products'; then
    echo "Northwind tables already exist; skipping import."
    exit 0
fi

echo "Importing Northwind data into the configured Railway database."
sed \
    -e '/^CREATE DATABASE IF NOT EXISTS `db_northwind`/d' \
    -e '/^USE `db_northwind`;/d' \
    /var/www/html/database/dbNorthwind.sql \
    | mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --database="$DB_NAME"

echo "Northwind data import completed."
