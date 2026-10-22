# Contamelo Finanzas

Sistema web de control financiero personal con análisis de rentabilidad y gestión patrimonial. Proyecto del Seminario Nuclear 6 (Ingeniería de Software, VIII semestre), desarrollado con Scrum.

**Stack:** Laravel, Blade, Tailwind CSS, Alpine.js, Vite, MySQL.

---

## 1. Requisitos previos

Instala lo siguiente en el equipo donde vas a correr el proyecto:

| Herramienta | Versión recomendada | Verificar con |
|---|---|---|
| PHP | 8.2 o superior (probado con 8.3) | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | LTS (18 o superior) | `node -v` |
| npm | incluido con Node | `npm -v` |
| Git | cualquiera reciente | `git --version` |
| MySQL / MariaDB | vía XAMPP o WAMP | phpMyAdmin |

### Extensiones de PHP necesarias

Normalmente vienen activas en XAMPP/WAMP. Si algo falla, verifica que estén habilitadas en `php.ini`:

`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `gd`.

> **Importante:** la versión de PHP de la **línea de comandos** debe ser 8.2 o superior. En Windows, XAMPP/WAMP pueden tener una versión distinta a la del PATH. Verifica siempre con `php -v` en la terminal.

---

## 2. Instalación paso a paso

### 2.1 Clonar el repositorio

```bash
git clone <URL-DEL-REPOSITORIO> contamelo_finanzas_gestion
cd contamelo_finanzas_gestion
```

### 2.2 Instalar dependencias

```bash
composer install
npm install
```

### 2.3 Crear el archivo de entorno

Windows (PowerShell):

```powershell
Copy-Item .env.example .env
```

Linux / macOS:

```bash
cp .env.example .env
```

Genera la clave de la aplicación:

```bash
php artisan key:generate
```

### 2.4 Crear la base de datos

1. Inicia MySQL desde XAMPP o WAMP (el servicio debe estar activo).
2. Abre phpMyAdmin (`http://localhost/phpmyadmin`).
3. Crea una base de datos llamada **`contamelo_finanzas`** con cotejamiento **`utf8mb4_unicode_ci`**.

### 2.5 Configurar el `.env`

Abre `.env` y ajusta estas variables. Laravel trae SQLite por defecto, así que es necesario cambiarlo a MySQL:

```env
APP_NAME="Contamelo Finanzas"
APP_URL=http://localhost:8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=contamelo_finanzas
DB_USERNAME=root
DB_PASSWORD=
```

> Si tu MySQL tiene contraseña para `root` o usa otro puerto, ajústalo aquí. **No subas el archivo `.env` al repositorio.**

La zona horaria del proyecto es `America/Bogota` y está definida en `config/app.php`.

### 2.5.1 Crear las tablas

```bash
php artisan migrate
```

Si el proyecto incluye seeders (categorías por defecto y datos de prueba):

```bash
php artisan db:seed
```

---

## 3. Ejecutar el proyecto

Necesitas **dos terminales** abiertas en la carpeta del proyecto.

**Terminal 1: servidor de Laravel**

```bash
php artisan serve
```

**Terminal 2: compilación de assets (Vite)**

```bash
npm run dev
```

Abre en el navegador: **http://localhost:8000**

Para probar, usa **Register** para crear un usuario. Si llegas al dashboard, todo está funcionando.

### Compilar para producción (opcional)

```bash
npm run build
```

Con esto ya no necesitas `npm run dev` y los assets quedan en `public/build`.

---

## 4. Comandos útiles

```bash
php artisan migrate:fresh --seed   # Reinicia la base de datos y carga datos de prueba
php artisan optimize:clear         # Limpia caché de configuración, rutas y vistas
php artisan route:list             # Lista las rutas de la aplicación
php artisan test                   # Ejecuta las pruebas
./vendor/bin/pint                  # Formatea el código según el estilo de Laravel
```

> `migrate:fresh` **borra todas las tablas**. Úsalo solo en desarrollo.

---

## 5. Solución de problemas

**`Failed opening required ... vendor/autoload.php`**
Faltan las dependencias. Ejecuta `composer install`.

**`No application encryption key has been specified` o `No APP_KEY variable was found`**
Falta la clave. Verifica que `.env` exista (copiado de `.env.example`) y ejecuta `php artisan key:generate`.

**`SQLSTATE[HY000] [1049] Unknown database`**
La base `contamelo_finanzas` no existe. Créala en phpMyAdmin (paso 2.4).

**`SQLSTATE[HY000] [2002] Connection refused` o no conecta**
MySQL no está encendido, o el puerto/credenciales del `.env` no coinciden. Revisa XAMPP/WAMP y los valores `DB_*`.

**Laravel usa SQLite en vez de MySQL**
`DB_CONNECTION` sigue en `sqlite` o las líneas `DB_*` están comentadas con `#`. Descoméntalas y ajústalas.

**La página carga sin estilos o con error de Vite (`Vite manifest not found`)**
Ejecuta `npm install` y luego `npm run dev` (o `npm run build`).

**`composer install` falla con `Failed to open stream: Resource temporarily unavailable` (Windows)**
Un antivirus o indexador bloquea archivos de `vendor`. Cierra otros programas que usen la carpeta, excluye la carpeta del proyecto del antivirus o reintenta. Si quedó a medias, borra `vendor` y repite `composer install`.

**Error de versión de PHP al instalar dependencias**
La terminal está usando una versión de PHP menor a 8.2. Revisa `php -v` y el PATH del sistema.

**Cambios en `.env` o configuración que no se reflejan**
Ejecuta `php artisan optimize:clear`.

---

## 6. Flujo de trabajo en equipo

- Rama `main`: versión estable. Rama `develop`: integración.
- Una rama por historia de usuario, por ejemplo `feature/US-12-registro-gastos`.
- Cambios mediante pull request con revisión de al menos un compañero.
- Cada integrante usa su **propia base de datos local**. El esquema se comparte con **migraciones y seeders**, nunca con volcados `.sql`.
- Después de traer cambios (`git pull`), ejecuta:

```bash
composer install
npm install
php artisan migrate
```

---

## 7. Estructura principal

```
app/
  Http/Controllers/    Controladores
  Models/              Modelos Eloquent
  Services/            Lógica de negocio y cálculos (si existe)
database/
  migrations/          Estructura de la base de datos
  seeders/             Datos iniciales
resources/views/       Vistas Blade
routes/web.php         Rutas web
.env.example           Plantilla de variables de entorno
```

---

## Equipo

Proyecto académico, Corporación Universitaria Empresarial Alexander von Humboldt. Seminario Nuclear 6, asignatura Gestión de proyectos de software.