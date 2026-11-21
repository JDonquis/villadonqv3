# Despliegue en VPS con Apache — VillaDonq + notificaciones en tiempo real

Guía para dejar funcionando la aplicación Laravel + Inertia/Svelte con:

- Laravel Reverb para notificaciones en tiempo real.
- Web Push con VAPID para notificaciones con la aplicación cerrada.
- Worker de cola para enviar notificaciones y push sin bloquear las peticiones.
- Apache como proxy WSS y servidor web.

> Reemplaza estos valores por los tuyos antes de ejecutar los comandos:
>
> | Variable | Ejemplo |
> |---|---|
> | `DOMINIO` | `escuela.ejemplo.com` |
> | `RUTA` | `/var/www/villadonq` |
> | `USUARIO_SSH` | `deploy` |
>
> Los ejemplos asumen Ubuntu/Debian con Apache 2.4 y PHP 8.3. Al final hay
> notas para cPanel/CentOS y Cloudflare.

---

## 0. Antes de empezar

- El dominio debe apuntar al VPS con un registro DNS tipo `A` o `AAAA`.
- Debes tener acceso SSH con `sudo`.
- La base de datos debe existir y tienes que tener usuario/contraseña.
- Si vienes de otro servidor, **haz respaldo de la base de datos antes de migrar**.
- Guarda las claves actuales del `.env` anterior si vas a reutilizar la misma
  base de datos, especialmente:
  - `APP_KEY`
  - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
  - `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`

Puertos:

- `80` y `443`: abiertos para Apache.
- `22`: SSH.
- `8080`: **no abrir al público**. Reverb escuchará solo en `127.0.0.1`.

---

## 1. Instalar paquetes del sistema

```bash
sudo apt update
sudo apt upgrade -y
```

Instala Apache, PHP, Composer, Node, Supervisor y Certbot:

```bash
sudo apt install -y \
  apache2 \
  git unzip curl \
  supervisor \
  certbot python3-certbot-apache \
  mysql-client
```

Instala PHP 8.3 y las extensiones que usa Laravel:

```bash
sudo apt install -y \
  php8.3 php8.3-cli libapache2-mod-php8.3 \
  php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl php8.3-sqlite3
```

Comprueba la versión y extensiones:

```bash
php -v
php -m | grep -E 'pcntl|sockets|pdo_mysql|mbstring|xml|curl|zip|bcmath|gd|intl'
```

Si falta `pcntl` o `sockets`, instálalos:

```bash
sudo apt install -y php8.3-pcntl php8.3-sockets
```

Instala Composer si no lo tienes:

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

Instala Node.js 20 y Yarn:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g yarn
node -v
yarn -v
```

---

## 2. Habilitar módulos de Apache

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers ssl
sudo a2enmod php8.3
sudo systemctl restart apache2
```

> `libapache2-mod-php8.3` permite que Apache ejecute PHP sin configurar
> PHP-FPM. Si prefieres PHP-FPM, en lugar de `libapache2-mod-php8.3` instala
> `php8.3-fpm` y configura `proxy_fcgi`/`setenvif` con `a2enconf php8.3-fpm`.

Verifica que `proxy_wstunnel` esté cargado:

```bash
apache2ctl -M | grep -E 'proxy|wstunnel|rewrite|headers|ssl'
```

Debe aparecer algo como `proxy_wstunnel_module`.

En CentOS/RHEL o cPanel debe estar disponible `mod_proxy_wstunnel`. Si no
aparece, hay que habilitarlo desde WHM/EasyApache o pedirlo al proveedor.

---

## 3. Subir el proyecto y ajustar permisos

Crea el directorio y clona el repositorio:

```bash
sudo mkdir -p /var/www/villadonq
sudo chown -R USUARIO_SSH:www-data /var/www/villadonq
git clone URL_DEL_REPOSITORIO /var/www/villadonq
cd /var/www/villadonq
```

Si ya tienes el proyecto subido por otro medio, entra a la ruta:

```bash
cd /var/www/villadonq
```

Ajusta permisos:

```bash
sudo chown -R USUARIO_SSH:www-data /var/www/villadonq
sudo find /var/www/villadonq -type d -exec chmod 775 {} \;
sudo find /var/www/villadonq -type f -exec chmod 664 {} \;
sudo chmod -R ug+rwX /var/www/villadonq/storage /var/www/villadonq/bootstrap/cache
```

Cuando exista el `.env`:

```bash
sudo chgrp www-data /var/www/villadonq/.env
sudo chmod 640 /var/www/villadonq/.env
```

Apache, Supervisor (Reverb) y el worker de cola deben poder leer el proyecto y
escribir en `storage` y `bootstrap/cache`.

---

## 4. Crear el `.env` de producción

```bash
cd /var/www/villadonq
cp .env.example .env
nano .env
```

Configuración mínima recomendada:

```env
APP_NAME=VillaDonq
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://DOMINIO

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=villadonq
DB_USERNAME=USUARIO_DB
DB_PASSWORD=CONTRASENA_DB

CACHE_DRIVER=file
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

BROADCAST_DRIVER=reverb

REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=DOMINIO
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

VAPID_SUBJECT="mailto:soporte@DOMINIO"
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VITE_VAPID_PUBLIC_KEY="${VAPID_PUBLIC_KEY}"
```

### Sobre `APP_KEY`

- Si reutilizas la base de datos y sesiones del servidor anterior, **copia la
  misma `APP_KEY`**.
- Si es una instalación nueva, puedes generarla con:

```bash
php artisan key:generate
```

### Sobre las claves de Reverb

Si vienes del entorno local, copia los mismos valores de `REVERB_APP_ID`,
`REVERB_APP_KEY` y `REVERB_APP_SECRET`. No es necesario regenerarlos.

Si necesitas generar credenciales nuevas:

```bash
php artisan reverb:install
```

Después ajusta manualmente `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`,
`REVERB_SERVER_HOST` y `REVERB_SERVER_PORT` como se muestra arriba.

> El navegador solo necesita la `REVERB_APP_KEY` pública. El
> `REVERB_APP_SECRET` es privado y nunca debe exponerse al frontend.
>
> Si cambias `REVERB_APP_KEY`, debes ejecutar `yarn run build` para que el
> frontend use la nueva key.

### Sobre VAPID (Web Push)

Si ya tienes claves VAPID en tu `.env` local, puedes copiarlas tal cual.

Si necesitas unas nuevas:

```bash
php artisan webpush:vapid
```

Ten en cuenta:

- `VAPID_PUBLIC_KEY` y `VITE_VAPID_PUBLIC_KEY` deben coincidir.
- `VAPID_PRIVATE_KEY` es privada: no debe subirse a Git ni mostrarse.
- Si cambias las claves, las suscripciones push existentes dejan de funcionar
  y los usuarios deben volver a activar las notificaciones.
- `VAPID_SUBJECT` debe ser un `mailto:` válido o la URL del sitio.

### Otros datos que debes actualizar

- `MAIL_*` si usas SMTP.
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y
  `GOOGLE_REDIRECT_URI=https://DOMINIO/login/google/callback`.
- `GOOGLE_MAPS_API_KEY`, si aplica.
- Credenciales de la base de datos.

---

## 5. Instalar dependencias y construir el frontend

```bash
cd /var/www/villadonq

composer install --no-dev --optimize-autoloader
yarn install --frozen-lockfile
yarn run build
```

Si el build falla por memoria:

```bash
NODE_OPTIONS=--max-old-space-size=2048 yarn run build
```

> Cada vez que cambies alguna variable `VITE_*`, incluida
> `VITE_REVERB_*` o `VITE_VAPID_PUBLIC_KEY`, debes volver a ejecutar
> `yarn run build`.

---

## 6. Base de datos y migraciones

```bash
cd /var/www/villadonq

php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
```

Comprueba:

```bash
php artisan migrate:status
php artisan about
```

Si la base de datos es nueva, **no ejecutes seeders de producción sin revisar
primero qué datos crean**. Para este proyecto normalmente se migra y se cargan
los datos reales desde el sistema anterior o desde respaldos.

---

## 7. Probar Reverb manualmente

Antes de pasarlo a Supervisor, ejecútalo en primer plano:

```bash
cd /var/www/villadonq
php artisan reverb:start --host=127.0.0.1 --port=8080
```

Debe quedar escuchando. En otra terminal:

```bash
ss -ltnp | grep 8080
```

Debe mostrar algo como:

```text
LISTEN 0 511 127.0.0.1:8080 ...
```

Detén el proceso con `Ctrl+C` y continúa con Supervisor.

---

## 8. Configurar Supervisor

Instala/verifica Supervisor:

```bash
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

Crea el archivo:

```bash
sudo nano /etc/supervisor/conf.d/villadonq.conf
```

Contenido:

```ini
[program:villadonq-reverb]
process_name=%(program_name)s
command=php /var/www/villadonq/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/villadonq
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/log/villadonq-reverb.log

[program:villadonq-queue]
process_name=%(program_name)s
command=php /var/www/villadonq/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/villadonq
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=60
redirect_stderr=true
stdout_logfile=/var/log/villadonq-queue.log
```

Crea los logs y ajusta permisos:

```bash
sudo touch /var/log/villadonq-reverb.log /var/log/villadonq-queue.log
sudo chown www-data:www-data /var/log/villadonq-reverb.log /var/log/villadonq-queue.log
```

Recarga Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Debe mostrar `RUNNING` para `villadonq-reverb` y `villadonq-queue`.

Comandos útiles:

```bash
sudo supervisorctl restart villadonq-reverb villadonq-queue
sudo supervisorctl tail -f villadonq-reverb stderr
sudo supervisorctl tail -f villadonq-queue stderr
```

Después de cada despliegue también conviene:

```bash
cd /var/www/villadonq
php artisan queue:restart
```

---

## 9. Configurar Apache

### 9.1 VirtualHost HTTP inicial

Crea el sitio:

```bash
sudo nano /etc/apache2/sites-available/villadonq.conf
```

Contenido:

```apache
<VirtualHost *:80>
    ServerName DOMINIO
    DocumentRoot /var/www/villadonq/public

    <Directory /var/www/villadonq/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/villadonq-error.log
    CustomLog ${APACHE_LOG_DIR}/villadonq-access.log combined
</VirtualHost>
```

Activa el sitio:

```bash
sudo a2ensite villadonq
sudo a2dissite 000-default
sudo apachectl configtest
sudo systemctl reload apache2
```

Comprueba que `https://DOMINIO` todavía no, pero `http://DOMINIO` responde.

### 9.2 Certificado SSL con Certbot

```bash
sudo certbot --apache -d DOMINIO
```

Elige la opción de redirigir HTTP a HTTPS.

Certbot crea el archivo:

```text
/etc/apache2/sites-available/villadonq-le-ssl.conf
```

Edítalo:

```bash
sudo nano /etc/apache2/sites-available/villadonq-le-ssl.conf
```

Dentro de `<VirtualHost *:443>` agrega el proxy de Reverb:

```apache
    ProxyPreserveHost On
    ProxyRequests Off
    ProxyTimeout 600

    # WebSocket de Reverb para el navegador
    ProxyPass        "/app"  "ws://127.0.0.1:8080/app" timeout=600
    ProxyPassReverse "/app"  "ws://127.0.0.1:8080/app"

    # Opcional: solo si Laravel publica a Reverb usando el dominio público.
    # En la configuración recomendada Laravel usa 127.0.0.1 y esto no hace falta.
    # ProxyPass        "/apps" "http://127.0.0.1:8080/apps" timeout=60
    # ProxyPassReverse "/apps" "http://127.0.0.1:8080/apps"
```

Verifica que el `DocumentRoot` y el bloque `<Directory>` sigan apuntando a:

```apache
    DocumentRoot /var/www/villadonq/public

    <Directory /var/www/villadonq/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
```

Recarga:

```bash
sudo apachectl configtest
sudo systemctl reload apache2
```

### 9.3 Si Certbot no creó el VirtualHost SSL

Puedes crearlo manualmente con las rutas de tus certificados:

```apache
<VirtualHost *:443>
    ServerName DOMINIO
    DocumentRoot /var/www/villadonq/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/DOMINIO/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/DOMINIO/privkey.pem

    ProxyPreserveHost On
    ProxyRequests Off
    ProxyTimeout 600

    ProxyPass        "/app"  "ws://127.0.0.1:8080/app" timeout=600
    ProxyPassReverse "/app"  "ws://127.0.0.1:8080/app"

    <Directory /var/www/villadonq/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/villadonq-error.log
    CustomLog ${APACHE_LOG_DIR}/villadonq-access.log combined
</VirtualHost>
```

### 9.4 Si usas cPanel

- Habilita `mod_proxy`, `mod_proxy_http`, `mod_proxy_wstunnel`, `mod_rewrite`,
  `mod_headers` y `mod_ssl` desde WHM/EasyApache 4.
- El VirtualHost lo administra cPanel. Usa **WHM → Apache Configuration →
  Include Editor** y agrega el bloque `ProxyPass` en el contexto
  **Post VirtualHost Include**.
- Si el hosting no permite `mod_proxy_wstunnel` ni procesos persistentes,
  Reverb no podrá funcionar en ese plan; necesitarás un VPS o un servicio
  WebSocket externo.

### 9.5 Si estás detrás de Cloudflare

- Activa el proxy naranja para el dominio.
- Usa SSL/TLS en modo **Full (strict)**.
- Cloudflare soporta WebSockets; no necesitas configuración extra en Apache,
  salvo que ya estés terminando TLS allí.
- Mantén `VITE_REVERB_HOST=DOMINIO`, `VITE_REVERB_PORT=443`,
  `VITE_REVERB_SCHEME=https`.

---

## 10. Firewall

Con UFW:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
sudo ufw status
```

Con firewalld:

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

No abras el puerto `8080`. Reverb solo debe escuchar en `127.0.0.1`.

---

## 11. Programar el scheduler de Laravel

Este proyecto ya usa el scheduler para tareas como
`balance:recalculate-status`.

Edita el crontab de `www-data`:

```bash
sudo crontab -u www-data -e
```

Agrega:

```cron
* * * * * /usr/bin/php /var/www/villadonq/artisan schedule:run >> /dev/null 2>&1
```

Si tienes problemas de permisos, usa un log:

```cron
* * * * * /usr/bin/php /var/www/villadonq/artisan schedule:run >> /var/log/villadonq-scheduler.log 2>&1
```

Y crea el archivo:

```bash
sudo touch /var/log/villadonq-scheduler.log
sudo chown www-data:www-data /var/log/villadonq-scheduler.log
```

---

## 12. Verificación técnica

Ejecuta:

```bash
cd /var/www/villadonq

php artisan about
php artisan migrate:status
php artisan route:list --path=dashboard/notificaciones
php artisan route:list --path=dashboard/push
sudo supervisorctl status
ss -ltnp | grep 8080
```

### Probar el WebSocket con curl

Obtén la key de Reverb:

```bash
cd /var/www/villadonq
KEY=$(php artisan tinker --execute="echo config('reverb.apps.apps.0.key');" | tail -n1)
echo "$KEY"
```

Prueba el handshake:

```bash
curl -i -N \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://DOMINIO/app/$KEY"
```

Debe responder:

```text
HTTP/1.1 101 Switching Protocols
```

Interpretación:

- `101`: proxy y Reverb funcionando.
- `502`: Reverb no está corriendo o no escucha en `127.0.0.1:8080`.
- `403`: revisar `REVERB_APP_KEY`.
- `400/404`: revisar `ProxyPass` y `mod_proxy_wstunnel`.

---

## 13. Prueba funcional de notificaciones

### 13.1 Notificación en tiempo real

1. Abre el sitio en el navegador e inicia sesión.
2. Debe aparecer la campanita en el header.
3. Abre **DevTools → Network → WS**.
4. Debe existir una conexión a `wss://DOMINIO/app/...` en estado conectado.
5. En otra sesión, registra un pago, aprueba un plan o publica un comunicado.
6. La campanita debe actualizar el contador y mostrar el toast sin recargar.

Si quieres forzar una notificación de prueba (con el worker de cola corriendo):

```bash
cd /var/www/villadonq
php artisan tinker --execute="app(App\Services\NotificationService::class)->send(App\Models\User::find(1), ['type'=>'test','title'=>'Prueba','body'=>'Notificación de prueba','icon'=>'mdi:bell','severity'=>'info'], '/dashboard/notificaciones');"
```

> Cambia `find(1)` por el ID del usuario que quieras probar.
>
> Con `QUEUE_CONNECTION=database`, el envío se encola. Revisa que
> `villadonq-queue` esté `RUNNING` en Supervisor.

También puedes revisar la tabla:

```bash
php artisan tinker --execute="echo App\Models\User::find(1)->notifications()->count();"
```

### 13.2 Web Push con la aplicación cerrada

1. Haz clic en **Activar notificaciones del navegador** dentro de la campanita.
2. Acepta el permiso del navegador.
3. Verifica que se guardó la suscripción:

```bash
cd /var/www/villadonq
php artisan tinker --execute="echo App\Models\User::find(1)->pushSubscriptions()->count();"
```

Debe devolver un número mayor que `0`.

4. Cierra la pestaña o minimiza el navegador.
5. Dispara una notificación con el comando de prueba o desde la app.
6. Debe aparecer la notificación del sistema.

Notas:

- Web Push requiere HTTPS. En `http://` solo funciona en `localhost`.
- En iPhone/iPad debe estar instalada como PWA (iOS 16.4 o superior).
- Si el usuario ya había activado push con otras claves VAPID, debe desactivar y
  volver a activar las notificaciones.

---

## 14. Actualizaciones del sistema

Proceso recomendado para cada despliegue:

```bash
cd /var/www/villadonq

php artisan down

git pull origin main

composer install --no-dev --optimize-autoloader
yarn install --frozen-lockfile
yarn run build

php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart

sudo supervisorctl restart villadonq-reverb villadonq-queue

php artisan up
```

Si cambiaste variables `VITE_*`, recuerda que `yarn run build` es obligatorio.

Cuando todo esté verificado, opcionalmente puedes cachear la configuración:

```bash
php artisan optimize
```

Si después necesitas cambiar el `.env`, ejecuta:

```bash
php artisan optimize:clear
```

---

## 15. Problemas comunes

### El WebSocket devuelve 502

- Reverb no está corriendo:
  ```bash
  sudo supervisorctl status
  sudo tail -n 100 /var/log/villadonq-reverb.log
  ```
- No está escuchando en el puerto correcto:
  ```bash
  ss -ltnp | grep 8080
  ```
- El `ProxyPass` apunta a otro host/puerto.

### El WebSocket devuelve 403

- La `REVERB_APP_KEY` no coincide entre `.env` y el frontend.
- El usuario no está autenticado o el canal privado no está autorizado.
- Revisa `storage/logs/laravel.log` y el log de Reverb.

### El WebSocket devuelve 400 o 404

- Falta `mod_proxy_wstunnel`.
- El `ProxyPass` está mal escrito.
- Estás usando `http://` en lugar de `ws://` en el `ProxyPass` interno.

### La campanita no se actualiza en tiempo real

- `BROADCAST_DRIVER` no es `reverb`.
- No reconstruiste el frontend después de cambiar `VITE_REVERB_*`:
  ```bash
  yarn run build
  ```
- El navegador está intentando conectar a `localhost:8080` porque el build se
  hizo con variables incorrectas.
- Reverb no está corriendo.

### Las notificaciones quedan en base de datos pero no se envían

- El worker no está corriendo:
  ```bash
  sudo supervisorctl status
  sudo tail -n 100 /var/log/villadonq-queue.log
  ```
- Revisa trabajos fallidos:
  ```bash
  php artisan queue:failed
  ```
- Reintenta:
  ```bash
  php artisan queue:retry all
  ```

### El botón “Activar notificaciones del navegador” no cambia

- En desarrollo, Vite no registra el service worker por defecto. La aplicación
  intenta registrar `/build/sw.js`; asegúrate de haber ejecutado `yarn run build`
  al menos una vez.
- Si el navegador tiene las notificaciones bloqueadas para el sitio, el botón
  mostrará un aviso. Habilítalas desde el candado/configuración del navegador.
- Si el botón muestra “No se encontró el service worker...”, revisa que
  `https://DOMINIO/build/sw.js` y `https://DOMINIO/push-sw.js` respondan.
- Después de cambiar `VITE_VAPID_PUBLIC_KEY` o `VITE_REVERB_*`, ejecuta
  `yarn run build` y recarga sin caché.

### Web Push no llega

- La web no está en HTTPS.
- El usuario no dio permiso de notificaciones.
- No existe fila en `push_subscriptions`.
- Las claves VAPID cambiaron y las suscripciones viejas quedaron inválidas.
- En iOS no está instalada como PWA.
- El service worker quedó viejo: prueba recargar sin caché o reinstalar la PWA.

### Error de tabla o columna inexistente

```bash
cd /var/www/villadonq
php artisan migrate --force
```

Si el error menciona `target_user_types`, `notified_at`, `notifications` o
`push_subscriptions`, es porque falta ejecutar las migraciones nuevas.

### Error de permisos en `storage` o `bootstrap/cache`

```bash
cd /var/www/villadonq
sudo chown -R USUARIO_SSH:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

---

## 16. Seguridad y respaldos

- Mantén `APP_DEBUG=false` en producción.
- No subas `.env` a Git.
- Protege `VAPID_PRIVATE_KEY` y `REVERB_APP_SECRET`.
- No expongas el puerto `8080`.
- Usa HTTPS obligatorio.
- Haz respaldo de la base de datos antes de cada migración.
- Si usas Cloudflare, usa SSL/TLS en modo **Full (strict)**.
- Revisa periódicamente:

```bash
tail -f /var/www/villadonq/storage/logs/laravel.log
sudo tail -f /var/log/villadonq-reverb.log
sudo tail -f /var/log/villadonq-queue.log
sudo tail -f /var/log/apache2/villadonq-error.log
```

---

## 17. Resumen de comandos esenciales

```bash
# Reverb
sudo supervisorctl restart villadonq-reverb
sudo tail -f /var/log/villadonq-reverb.log

# Cola
sudo supervisorctl restart villadonq-queue
sudo tail -f /var/log/villadonq-queue.log

# Apache
sudo apachectl configtest
sudo systemctl reload apache2
sudo tail -f /var/log/apache2/villadonq-error.log

# Laravel
cd /var/www/villadonq
php artisan optimize:clear
php artisan queue:restart
php artisan migrate --force

# Frontend
yarn run build
```

Con esto, la aplicación, Reverb, el worker y Web Push quedan funcionando en el
VPS con Apache como proxy WSS.
