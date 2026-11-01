# MVP Catálogos de Proveedores (v2)

Sistema de extracción de catálogos y listas de precios de herramientas y ferretería con LLM (Gemini), persistencia en SQLite y visualización/edición en Laravel.

## Requisitos previos

- **PHP 8.2+** (con extensiones `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`)
- **Composer**
- **Python 3.10+**

## Instalación y Configuración

1. Instalar dependencias del proyecto:
   ```bash
   composer install
   pip install -r python/requirements.txt
   ```

2. Configurar el archivo `.env`:
   ```env
   DB_CONNECTION=sqlite
   QUEUE_CONNECTION=database

   GEMINI_API_KEY="tu_clave_gemini"
   GEMINI_MODEL=gemini-3.5-flash-lite
   GEMINI_PRICE_IN=0.30
   GEMINI_PRICE_OUT=2.50
   PYTHON_BIN=python
   ```

3. Ejecutar migraciones y seeders:
   ```bash
   php artisan migrate --seed
   ```
   Esto crea los usuarios por defecto:
   - **Administrador:** `admin@example.com` / `password`
   - **Empleado:** `empleado@example.com` / `password`

## Ejecución del Sistema

1. **Terminal 1 - Servidor Web:**
   ```bash
   php artisan serve
   ```
   Accede a la app en `http://localhost:8000`.

2. **Terminal 2 - Worker de Cola:**
   ```bash
   php artisan queue:work --timeout=3600
   ```

## Roles y Permisos

- **Empleado:** Acceso a la tabla de productos (búsqueda, filtros por proveedor, filtro "solo para revisar", paginación, modal con los 8 datos de detalle y exportación a CSV).
- **Admin:** Todos los permisos anteriores más edición de productos, subida y monitoreo de catálogos y gestión de proveedores.
