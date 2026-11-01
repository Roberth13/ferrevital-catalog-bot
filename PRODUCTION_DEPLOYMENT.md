# GUÍA DE DESPLIEGUE EN PRODUCCIÓN — FERREVITAL CATALOG BOT

Este documento detalla los requisitos del sistema, configuración de variables de entorno, migraciones, gestión de colas/workers, almacenamiento y protocolos de recuperación para desplegar `ferrevital-catalog-bot` en un entorno de producción o preproducción (Linux Ubuntu/Debian o equivalente).

---

## 1. Requisitos del Servidor

### A. Lenguaje y Extensiones PHP
- **PHP:** `^8.3` (validado con PHP 8.3.14)
- **Extensiones Obligatorias:**
  - `ext-pdo_mysql` (Conexión a base de datos MySQL/MariaDB)
  - `ext-gd` (Procesamiento y segmentación de imágenes OCR)
  - `ext-fileinfo` (Validación segura de tipos MIME)
  - `ext-curl` (Llamadas HTTP a la API de Google Gemini)
  - `ext-mbstring` (Normalización de texto y codificación UTF-8)
  - `ext-zip` (Exportación en streaming con SimpleExcelWriter)
  - `ext-pcntl` y `ext-posix` (Gestión de señales en workers de cola)

### B. Binarios de Sistema Operativo (OCR y Poppler)
En servidores Linux (Ubuntu/Debian), instalar los paquetes oficiales:
```bash
sudo apt update
sudo apt install -y poppler-utils tesseract-ocr tesseract-ocr-spa tesseract-ocr-eng
```
- **Binarios instalados:**
  - `pdftotext` (`/usr/bin/pdftotext`)
  - `pdfinfo` (`/usr/bin/pdfinfo`)
  - `pdftoppm` (`/usr/bin/pdftoppm`)
  - `tesseract` (`/usr/bin/tesseract`)

### C. Base de Datos
- **Motor:** MySQL 8.0+ o MariaDB 10.5+
- **Juego de caracteres:** `utf8mb4` / `utf8mb4_unicode_ci`

---

## 2. Variables de Entorno (`.env`)

Copiar el archivo `.env.example` y configurar los valores de producción:
```bash
cp .env.example .env
php artisan key:generate
```

### Configuración Específica para Producción
```dotenv
APP_NAME="Ferrevital Catalog Bot"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://catalogo.ferrevital.com

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ferrevital_catalog
DB_USERNAME=ferrevital_user
DB_PASSWORD=YOUR_SECURE_PASSWORD

# Colas y Workers
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1300

# Binarios Poppler (Rutas en Linux)
POPPLER_BIN_PATH=/usr/bin
PDFTOPPM_PATH=/usr/bin/pdftoppm

# Binarios Tesseract OCR (Rutas en Linux)
TESSERACT_PATH=/usr/bin/tesseract

# Configuración IA (Google Gemini)
AI_PROVIDER=gemini
GEMINI_API_KEY=YOUR_GEMINI_API_KEY
GEMINI_MODEL=gemini-3.6-flash
AI_EXTRACTION_PROMPT_VERSION=v1
AI_RANKING_PROMPT_VERSION=v1
PARSER_VERSION=v1
```

---

## 3. Migraciones y Seeders

Ejecutar las migraciones y sembrar el proveedor genérico por defecto (`id = 1`):
```bash
php artisan migrate --force
php artisan db:seed --force
```

El seeder `DatabaseSeeder` ejecuta de forma idempotente:
```php
\App\Models\Supplier::firstOrCreate(
    ['id' => 1],
    ['name' => 'Proveedor Genérico', 'slug' => 'proveedor-generico']
);
```

---

## 4. Directorios de Almacenamiento y Permisos

Los archivos PDF subidos se almacenan en el disco privado `storage/app/private/catalogs/` (inaccesibles directamente vía HTTP).

Configurar permisos para el usuario del servidor web (`www-data` o `nginx`):
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 5. Configuración de Workers (Supervisor)

Para procesar catálogos en segundo plano sin interrumpir la interfaz web, configurar **Supervisor**:

Crear `/etc/supervisor/conf.d/ferrevital-worker.conf`:
```ini
[program:ferrevital-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ferrevital-catalog-bot/artisan queue:work database --sleep=3 --tries=6 --timeout=1200 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ferrevital-catalog-bot/storage/logs/worker.log
stopwaitsecs=1200
```

> [!IMPORTANT]
> - `timeout=1200`: Permite que `ProcessCatalogJob` procese catálogos OCR pesados (hasta 20 minutos).
> - `DB_QUEUE_RETRY_AFTER=1300`: Evita que un worker duplique la ejecución de un catálogo en curso.
> - `stopwaitsecs=1200`: Permite terminación limpia sin matar jobs en ejecución durante despliegues.

Iniciar los procesos en Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ferrevital-worker:*
```

---

## 6. Monitoreo y Observabilidad

### Tablas Clave a Monitorear
1. `catalogs`:
   - `status`: `uploaded` $\to$ `processing` $\to$ `completed` / `failed`.
   - `total_products`: Conteo consolidado de productos procesados.
2. `job_batches`:
   - Monitorea el progreso de los lotes `catalog-{id}` (`pending_jobs`, `failed_jobs`).
3. `catalog_logs`:
   - Registro de productos creados, actualizados, duplicados o rechazados por SKU vacío.
4. `failed_jobs`:
   - Registro de jobs que agotaron sus 6 reintentos.

---

## 7. Protocolos de Recuperación y Contingencia

| Evento | Comportamiento del Sistema | Acción del Operador |
| :--- | :--- | :--- |
| **HTTP 429 (Cuota Gemini excedida)** | Lanza `AiRateLimitException` y aplica backoff exponencial `[5, 10, 20, 40, 80, 160]s`. | Ninguna. Se recupera automáticamente. |
| **HTTP 503 (Servicio no disponible)** | Lanza `AiTemporaryException` y reintenta hasta 6 veces. | Ninguna. Se recupera automáticamente. |
| **Worker reiniciado / caído** | El job se reanuda desde la cola; `DB::upsert()` por `(supplier_id, codigo)` previene duplicados. | Iniciar supervisor (`supervisorctl restart all`). |
| **Catálogo en `failed`** | Queda registrado en `catalogs.status = 'failed'` con error en `catalog_logs`. | Revisar `storage/logs/laravel.log` y reintentar job fallido vía `php artisan queue:retry`. |
| **Falta binario Poppler/Tesseract** | `ProcessCatalogJob` falla con mensaje claro indicando la ruta del ejecutable. | Instalar `poppler-utils` / `tesseract-ocr` o corregir rutas en `.env`. |
