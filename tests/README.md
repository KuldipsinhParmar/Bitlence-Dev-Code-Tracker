# Running the test suite

One-time setup:

```bash
composer install

docker compose up -d db
docker compose exec db mysql -uroot -prootpassword -e "CREATE DATABASE IF NOT EXISTS wordpress_test"

cp tests/wp-tests-config-sample.php tests/wp-tests-config.php
```

`tests/wp-tests-config.php` is git-ignored — edit it if your DB credentials differ from
`docker-compose.yml`. It expects the `db` service's port 3306 mapped to `127.0.0.1:3311` on the
host (already set in `docker-compose.yml`), since PHPUnit runs on the host, not inside a container.

Then, any time:

```bash
composer test
```
