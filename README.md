# FinanzApp

**FinanzApp** es una plataforma web moderna para la gestión integral y el control de las finanzas personales. Permite registrar, clasificar y analizar ingresos y gastos con precisión, ofreciendo control sobre el presupuesto, categorización jerárquica y trazabilidad financiera.

---

## 🚀 Descripción del Proyecto

FinanzApp está diseñada para brindar una experiencia de usuario ágil, confiable y accesible, adaptada a las necesidades reales de control financiero personal.

### ✨ Características Principales

- **Gestión de Ingresos:** Registro de fuentes de ingreso recurrentes y esporádicas, clasificación tributaria y seguimiento de pagos activos/inactivos.
- **Control de Gastos:** Registro detallado de egresos con precisión decimal exacta, soporte para comercios/beneficiarios, métodos de pago y notas explicativas.
- **Categorías y Subcategorías Jerárquicas:** Organización multinivel de gastos con asignación de naturalezas (*Fijo / Variable*) y necesidades (*Esencial / Discrecional*), junto a un catálogo de íconos temáticos.
- **Diseño Moderno y Accesible:** Sistema de diseño personalizado con paleta armónica, tokens semánticos, soporte de modo oscuro/claro y cumplimiento de criterios de accesibilidad (a11y).
- **Validaciones Rigurosas:** Validación de cálculos y formatos monetarios en frontend y backend, evitando pérdidas de precisión o inconsistencias numéricas.

### 🛠️ Stack Tecnológico

- **Backend:** PHP 8.2+ · Laravel 11/12 · Eloquent ORM
- **Frontend:** Blade · Vanilla CSS / Tailwind CSS · Alpine.js · Vite
- **Base de Datos:** PostgreSQL (compatible con Supabase) / MySQL
- **Herramientas de Soporte:** Chart.js, Decimal.js

---

## 📋 Requisitos Previos

Asegúrate de contar con las siguientes herramientas instaladas en tu equipo:

| Herramienta | Versión recomendada | Comando de verificación |
|-------------|---------------------|--------------------------|
| **PHP** | 8.2 o superior (8.3 recomendado) | `php -v` |
| **Composer** | 2.x | `composer -V` |
| **Node.js** | 18 LTS o superior | `node -v` |
| **npm** | Incluido con Node.js | `npm -v` |
| **Git** | Versión reciente | `git --version` |
| **Base de Datos** | PostgreSQL (local o Supabase) / MySQL | Cliente respectivo |

> **Nota para Windows:** Asegúrate de que las extensiones requeridas de PHP (`pdo_pgsql`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `gd`) estén habilitadas en tu `php.ini` y que el ejecutable de PHP se encuentre agregado a tu variable de entorno `PATH`.

---

## ⚡ Instrucciones de Arranque

Sigue estos pasos para poner en marcha el proyecto en tu entorno local:

### 1. Clonar el repositorio

```bash
git clone <URL-DEL-REPOSITORIO> finanzapp
cd finanzapp
```

### 2. Instalar dependencias

Instala los paquetes de PHP y las dependencias de Node.js:

```bash
composer install
npm install
```

### 3. Configurar el archivo de entorno

Copia la plantilla de variables de entorno:

- **Windows (PowerShell):**
  ```powershell
  Copy-Item .env.example .env
  ```
- **Linux / macOS / Bash:**
  ```bash
  cp .env.example .env
  ```

Genera la clave de aplicación de Laravel:

```bash
php artisan key:generate
```

### 4. Configurar la base de datos

Abre el archivo `.env` y configura los datos de conexión según el motor que desees utilizar:

#### Opción A: PostgreSQL / Supabase
```env
DB_CONNECTION=pgsql
DB_HOST=tu_host_supabase_o_localhost
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

#### Opción B: MySQL local (XAMPP / WAMP)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finanzapp
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Ejecutar migraciones y datos iniciales

Crea las tablas en la base de datos:

```bash
php artisan migrate
```

*(Opcional)* Carga categorías predeterminadas y datos de prueba:

```bash
php artisan db:seed
```

### 6. Iniciar los servidores de desarrollo

Para trabajar en local, se recomienda abrir dos terminales en la raíz del proyecto:

**Terminal 1 — Servidor backend de Laravel:**
```bash
php artisan serve
```
El servidor quedará disponible en: `http://localhost:8000`

**Terminal 2 — Servidor de desarrollo de assets (Vite):**
```bash
npm run dev
```

> **Para producción:** Puedes generar los assets estáticos ejecutando:
> ```bash
> npm run build
> ```

---

## 🧪 Pruebas y Comandos Útiles

```bash
# Limpiar caché de configuración, rutas y vistas
php artisan optimize:clear

# Ejecutar pruebas automatizadas de PHP
php artisan test

# Ejecutar pruebas unitarias de frontend
npm test

# Restablecer la base de datos desde cero con datos de prueba (solo desarrollo)
php artisan migrate:fresh --seed
```

---

## 📂 Estructura del Proyecto

```
app/
  Http/Controllers/     Controladores de la aplicación
  Models/               Modelos de datos Eloquent
  Services/             Servicios y lógica de negocio
database/
  migrations/           Migraciones de base de datos
  seeders/              Semillas y catálogos iniciales
resources/
  css/                  Hojas de estilo y tokens de diseño
  js/                   Lógica cliente, validaciones y pruebas
  views/                Plantillas Blade y componentes UI
routes/
  web.php               Definición de rutas web
```

---

## 📄 Licencia

Proyecto de uso académico y profesional.