# Mini app XKCD (PHP)

Mini-aplicación en PHP 8.1+ que consume la API pública de XKCD y expone una interfaz web sencilla junto a endpoints internos.

## Requisitos funcionales
- RF1: Mostrar el cómic actual de XKCD (título, imagen, alt-text, número, fecha).
- RF2: Permitir navegar a un cómic por número (?id=NUM).
- RF3: Botones “Previous”, “Next” y “Random” con validaciones (no ir <1 ni >actual).
- RF4: Endpoint interno GET `/api/favorites` que devuelve favoritos en JSON.
- RF5: Manejo de errores visible (cómic inexistente, red caída, input inválido).

## Requisitos no funcionales
- RNF1: PHP 8.1+ sin frameworks pesados.
- RNF2: Acceso a datos con PDO y consultas preparadas (SQLite).
- RNF3: Código fuente en inglés; README claro en español.
- RNF4: Despliegue online en servicio gratuito o guía paso a paso.
- RNF5: Estructura de carpetas clara y separada por capas.

## Instalación y ejecución local
1. Clona el repositorio:
   ```bash
   git clone <URL-del-repo>
   cd mini-app-php
   ```
2. Ejecuta el servidor embebido de PHP:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Accede a [http://localhost:8000](http://localhost:8000) en tu navegador.

## Uso
- Visualiza el cómic actual de XKCD.
- Navega por número usando `?id=NUM`.
- Botones Previous, Next y Random (con límites).
- Agrega cómics a favoritos.
- Endpoint interno: `GET /api/favorites` devuelve favoritos en JSON.
- Manejo de errores visible (cómic inexistente, red caída, input inválido).

## Estructura de carpetas
```
public/           # Punto de entrada y archivos estáticos
src/
  Presentation/   # Controladores (lógica de presentación y endpoints)
  Infra/          # Acceso a datos, logging, cliente API XKCD
logs/             # Trazas de la aplicación (app.log)
data/             # Base de datos SQLite (favorites.sqlite)
```

## Despliegue gratuito (Fly.io)

Esta aplicación se puede desplegar en Fly.io como backend PHP con persistencia para SQLite. A continuación pasos prácticos para un despliegue rápido usando Fly CLI y un Dockerfile simple.

1. Instala flyctl: https://fly.io/docs/hands-on/install-flyctl/
2. Inicia sesión y crea una app:
   ```bash
   fly auth login
   fly launch --name xkcd-mini --region ord --no-deploy
   ```
3. Crea un volumen persistente para la base de datos (ejemplo 1 GB):
   ```bash
   fly volumes create data --size 1 --region ord -a xkcd-mini
   ```
4. Añade un Dockerfile en la raíz del repo (ejemplo):
   ```Dockerfile
   FROM php:8.1-apache
   RUN docker-php-ext-install pdo pdo_sqlite
   COPY . /var/www/html/
   WORKDIR /var/www/html
   # permisos para SQLite data dir
   RUN mkdir -p /data && chown -R www-data:www-data /data
   EXPOSE 8080
   CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
   ```
   (Si prefieres usar Apache, ajusta el Dockerfile para poner el contenido en /var/www/html y usar apache2-foreground.)
5. Configura el mapeo de volumen en fly.toml (Fly creará fly.toml en fly launch). Añade bajo [[mounts]]:
   ```toml
   [[mounts]]
   source = "data"
   destination = "/data"
   ```
6. Asegúrate de que la app use la carpeta `/data` para la base SQLite (README y código ya usan `data/favorites.sqlite`). En el servidor Fly la ruta será `/data/favorites.sqlite`.
7. Despliega la app:
   ```bash
   fly deploy -a xkcd-mini
   ```
8. Revisa logs y estado:
   ```bash
   fly logs -a xkcd-mini
   fly status -a xkcd-mini
   ```

Notas importantes
- Fly.io ejecuta tu contenedor; la app debe crear o usar `data/favorites.sqlite` dentro del volumen `/data` para persistencia.
- Si usas el comando `php -S` en el Dockerfile, asegúrate de exponer el puerto correcto (Fly usa el puerto 8080 por convención en contenedores).
- No subas `data/*.sqlite` ni `logs/*.log` al repositorio (ya están en .gitignore).

## Notas
- El código está en inglés y el README en español.
- No se usan frameworks pesados.
- Acceso a datos con PDO y consultas preparadas.
- Logs básicos en `logs/app.log`.

## Autor
Guillermo Soria Correa
