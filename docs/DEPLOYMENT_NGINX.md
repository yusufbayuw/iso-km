# ISO-KM / Laravel 11 / Nginx deployment (including aaPanel)

## How to diagnose the default Nginx 404

A browser page containing only `404 Not Found` and `nginx` means Nginx is
answering before the request reaches Laravel. For ISO-KM, the Filament panel
is registered at `/portal` in `PortalPanelProvider`. Laravel redirects `/`
to `/portal`. These routes are not fixed by changing roles or reseeding.

From the project directory:

```bash
cd /www/wwwroot/iso.tarunabakti.or.id
/www/server/php/82/bin/php artisan route:list --path=portal
```

If a route for `portal` exists, focus on the Nginx server handling the port:

```bash
ss -lntp | grep ':8101'
grep -RInE 'listen[[:space:]]+8101|server_name[[:space:]]+10\\.10\\.1\\.135' \
  /www/server/panel/vhost/nginx /www/server/nginx/conf 2>/dev/null
```

Look at the **actual virtual host** for port 8101, not only the public-domain
site on ports 80/443. A `server_name` mismatch may send the IP request to
an unrelated default server block.

For a Laravel site, ensure its active server block has:

```nginx
root /www/wwwroot/iso.tarunabakti.or.id/public;
index index.php index.html;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

The PHP location also needs to forward `.php` to the **actual PHP 8.2
PHP-FPM listener** (aaPanel normally provides an `enable-php-82.conf`
include). Keep the existing aaPanel PHP handling rather than guessing a
FastCGI socket. Do not duplicate a `location /` in an already-defined block.

### Safe validation

```bash
/www/server/nginx/sbin/nginx -t
# Only if valid:
/www/server/nginx/sbin/nginx -s reload
curl -I -H 'Host: 10.10.1.135' http://127.0.0.1:8101/portal
```

An unauthenticated working portal will typically return HTTP 302 to login
(or HTTP 200), **not** Nginx's bare 404. Compare with
`Host: iso.tarunabakti.or.id` to detect virtual-host mismatches.

- `/index.php` works while `/portal` fails: missing Laravel rewrite.
- Both show bare Nginx 404: wrong document root or wrong vhost/listen port.
- HTTP 502: investigate FastCGI/PHP-FPM listener and logs.
- Laravel 500: review `storage/logs/laravel.log`, cache dirs, and PHP extensions.
- Filament login/403: review `portal.access`, roles, and session/config.

## Security

Avoid serving the repository root. Only `public/` should be web-accessible:
`.env`, `vendor/`, and `storage/` must not be publicly exposed.

HTTP access through an IP such as `10.10.1.135:8101` is marked `Not secure`
by browsers because it lacks trusted TLS. Use a certificate for a managed
hostname (and HTTPS) before entering production credentials. HTTPS does not
itself repair a Nginx 404.

The Nginx site configuration lives on the deployment server and is not
controlled by `git pull`. This file is guidance, not an automatic change
to aaPanel's server configuration.
