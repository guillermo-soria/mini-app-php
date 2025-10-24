# Mini app XKCD (PHP)

Mini-aplicación en PHP 8.1+ que consume la API pública de XKCD y expone una interfaz web sencilla junto a endpoints internos.

## Requisitos funcionales
- Mostrar el cómic actual de XKCD (título, imagen, alt-text, número, fecha).
- Permitir navegar a un cómic por número (`?id=NUM`).
- Botones “Previous”, “Next” y “Random” con validaciones (no ir <1 ni >actual).
- Endpoint interno `GET /api/favorites` que devuelve favoritos en JSON.
- Manejo de errores visible (cómic inexistente, red caída, input inválido).

## Requisitos no funcionales
- PHP 8.1+ sin frameworks pesados.
- Acceso a datos con PDO y SQLite.
- Código fuente en inglés; README en español.
- Despliegue en Fly.io (ejemplo) u otra plataforma.
- Estructura de carpetas clara y separada por capas.

## Estado desplegado
Live demo: https://xkcd-mini.fly.dev/

## Ejecución local
1. Clona el repositorio:
   ```bash
   git clone <URL-del-repo>
   cd mini-app-php
   ```
2. Ejecuta el servidor embebido de PHP (para desarrollo):
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
3. Abre http://127.0.0.1:8000 en tu navegador.

> Nota: el proyecto ahora está organizado con un pequeño front controller (public/index.php), un bootstrap en `src/bootstrap.php` y vistas bajo `src/Views/`.

## Uso
- Visualiza el cómic actual de XKCD.
- Navega por número usando `?id=NUM`.
- Botones Previous, Next y Random (con límites).
- Agrega cómics a favoritos (persistencia SQLite).
- Endpoint interno: `GET /api/favorites` devuelve favoritos en JSON.
- HTMX se usa para navegación parcial: las peticiones con `HX-Request` devuelven solo el fragmento HTML (`<section id="comic">`) para que la UI actualice sin recargar la página.

## Estructura de carpetas (resumen)
```
public/           # Punto de entrada y archivos estáticos
src/              # Código PHP (bootstrap, controllers, infra, views)
logs/             # Trazas de la aplicación (app.log)
data/             # Base de datos SQLite (favorites.sqlite) — en producción monta un volumen
```

## Docker / contenedores
A continuación dos ejemplos de Dockerfile (elige uno según prefieras correr con Apache o con el servidor embebido para desarrollo).

Opción A — imagen con Apache (recomendada para producción simple):
```Dockerfile
FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_sqlite
COPY . /var/www/html/
WORKDIR /var/www/html
# Asegura que la carpeta de datos exista y permisos para SQLite
RUN mkdir -p /data && chown -R www-data:www-data /data
# Si el DocumentRoot debe ser /var/www/html/public, ajusta la configuración de Apache o copia solo /public
EXPOSE 80
CMD ["apache2-foreground"]
```

Opción B — imagen ligera usando el servidor embebido (útil para pruebas):
```Dockerfile
FROM php:8.1-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
COPY . /app
WORKDIR /app
RUN mkdir -p /data && chown -R www-data:www-data /data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

## Despliegue en Fly.io (resumen)
1. Instala flyctl y autenticate:
   ```bash
   fly auth login
   fly launch --name xkcd-mini --region ord --no-deploy
   ```
2. Crea un volumen persistente para la base de datos:
   ```bash
   fly volumes create data --size 1 --region ord -a xkcd-mini
   ```
3. En `fly.toml` añade (si no existe) el mapeo de volumen:
   ```toml
   [[mounts]]
   source = "data"
   destination = "/data"
   ```
4. Asegura que la aplicación use `/data/favorites.sqlite` en producción. Puedes configurar la variable de entorno `FAVORITES_DB` en fly.toml o con `fly secrets`:
   ```toml
   [env]
   FAVORITES_DB = "/data/favorites.sqlite"
   ```
   O desde CLI:
   ```bash
   fly secrets set FAVORITES_DB=/data/favorites.sqlite
   ```
5. Despliega:
   ```bash
   fly deploy -a xkcd-mini
   ```
6. Revisa logs y estado:
   ```bash
   fly logs -a xkcd-mini
   fly status -a xkcd-mini
   ```

### Nota sobre CI / GitHub Actions
Si usas `flyctl deploy --remote-only` en GitHub Actions, necesitas un token válido en los secretos del repositorio:
- Crea un token en https://fly.io (Account → Personal Access Tokens).
- Añádelo a GitHub Secrets con el nombre `FLY_API_TOKEN`.

## Consideraciones y recomendaciones
- El código por defecto usa `data/favorites.sqlite` en desarrollo si `FAVORITES_DB` no está definido. En producción (Fly) se recomienda apuntar a `/data/favorites.sqlite` mediante la variable de entorno.
- HTMX está integrado: las peticiones XHR con el header `HX-Request` devuelven solo el fragmento para un swap parcial. No se requieren cambios adicionales en el cliente.
- Si colaboras, usa `php -S` para pruebas locales y pruebas la configuración de Docker/Fly para producción.

## Autor
Guillermo Soria Correa
