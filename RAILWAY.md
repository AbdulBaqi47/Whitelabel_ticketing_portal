# Railway deployment

This service requires a MySQL database reachable from Railway. `DB_HOST=127.0.0.1` only works on a local machine and must not be used in Railway.

1. In the Railway project, add a **MySQL** service.
2. In the Laravel service's **Variables** tab, add these references (replace `MySQL` if your database service has a different name):

   ```text
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<your existing Laravel app key>
   APP_URL=${{RAILWAY_PUBLIC_DOMAIN}}
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```

3. Redeploy. `railway-start.sh` runs safe migrations and then starts the HTTP server on Railway's `PORT`.

For a new, empty database that needs the project's initial data, run this once from the Laravel service shell after the deploy:

```sh
php artisan db:seed --force
```

Do not put database credentials or `.env` in Git. Railway stores the production values in its Variables tab.
