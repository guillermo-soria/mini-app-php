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

## Despliegue gratuito (ejemplo Fly.io)
1. Regístrate en [Fly.io](https://fly.io/) y crea una nueva app.
2. Sube el código del proyecto.
3. Configura PHP 8.1+ y persistencia para la carpeta `data/`.
4. Expón el directorio `public/` como raíz web.
5. Accede a la URL pública que te proporciona Fly.io.

## Notas
- El código está en inglés y el README en español.
- No se usan frameworks pesados.
- Acceso a datos con PDO y consultas preparadas.
- Logs básicos en `logs/app.log`.

## Autor
Guillermo Soria Correa
