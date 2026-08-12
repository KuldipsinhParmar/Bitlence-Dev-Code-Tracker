<?php
/**
 * Local test config — copy this file to tests/wp-tests-config.php (git-ignored) and adjust
 * if your DB credentials differ from the ones in docker-compose.yml.
 *
 * Requires the `db` service in docker-compose.yml to be running and reachable on the mapped
 * host port (3311 by default), with a `wordpress_test` database created on it:
 *
 *   docker compose up -d db
 *   docker compose exec db mysql -uroot -prootpassword -e "CREATE DATABASE IF NOT EXISTS wordpress_test"
 */

define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'wpuser' );
define( 'DB_PASSWORD', 'wppassword' );
define( 'DB_HOST', '127.0.0.1:3311' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/johnpbloch/wordpress-core/' );
