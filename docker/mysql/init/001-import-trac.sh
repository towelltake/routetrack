#!/bin/sh
set -eu

dump_file="/docker-mysql-seed/tracv2_nmwc.sql"

if [ ! -f "$dump_file" ]; then
  echo "Missing MySQL seed dump: $dump_file" >&2
  exit 1
fi

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
SQL

{
  echo "SET FOREIGN_KEY_CHECKS=0;"
  echo "SET UNIQUE_CHECKS=0;"
  cat "$dump_file"
  echo "SET UNIQUE_CHECKS=1;"
  echo "SET FOREIGN_KEY_CHECKS=1;"
} | mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}"
