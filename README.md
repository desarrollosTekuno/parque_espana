# Sistema de Gestión de Membresías — Parque España

Sistema administrativo para la gestión de cuentas de membresías, cobranza, casilleros, reservaciones y operación general de dos clubes deportivos.

---

## Módulos principales

| Módulo | Descripción |
|--------|-------------|
| **Membresías** | Alta, cambio, separación y baja de cuentas familiares e individuales. Historial de cambios y árbol de cuentas derivadas. |
| **Cobranza** | Cargos de inscripción y mensualidades. Registro de cobros con múltiples métodos de pago por parque. Desglose de cargos pendientes. |
| **Documentos** | Expediente digital por integrante. Documentos requeridos por tipo de membresía + documentos de sistema (comprobante de descuento de inscripción, etc.). |
| **Casilleros** | Asignación, cambio y baja de casilleros por integrante. Historial de movimientos con comprobante. |
| **Reservaciones** | Gestión de amenidades, horarios, bloqueos y listas de invitados. |
| **Corte de caja** | Cortes por parque y corte global. |
| **Anuncios y publicidad** | Gestión de anuncios internos y anuncios comerciales (business ads). |
| **Historia clínica** | Datos médicos y contacto de emergencia por integrante. |
| **Ausencias** | Permisos de ausencia con porcentaje de cobro configurable y documento de respaldo. |
| **Encuestas** | Creación y seguimiento de encuestas internas. |
| **Feedback** | Sistema de tickets internos por categoría. |
| **Usuarios y roles** | Gestión de accesos y permisos por módulo. |
| **Catálogos** | Tipos de membresía, conceptos de cobro, métodos de pago, reglas de precio, tipos de documento, ubicaciones. |

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 12 (PHP ^8.2) |
| Frontend | Vue 3 (Composition API) + Vuetify 3 |
| Conector SPA | Inertia.js |
| Empaquetador | Vite |
| Base de datos | PostgreSQL (multi-esquema) |
| Almacenamiento | DigitalOcean Spaces (S3-compatible) |
| Notificaciones push | Firebase Cloud Messaging |
| Pagos | Conekta |

---

## Esquemas de base de datos (PostgreSQL)

El proyecto usa múltiples esquemas dentro de la misma base de datos:

| Esquema | Contenido |
|---------|-----------|
| `public` | Usuarios, clubes, roles, permisos, configuración |
| `memberships` | Cuentas, membresías, tipos, reglas de precio, historial, ausencias |
| `members` | Integrantes, documentos, direcciones, empleo, historia clínica |
| `billing` | Cargos, pagos, conceptos, métodos de pago, cortes de caja |
| `catalogs` | Países, estados, ciudades, nacionalidades, estados civiles, tipos de documento, relaciones |
| `reservations` | Reservaciones, amenidades, recursos, horarios, bloqueos |
| `lockers` | Asignaciones, historial de movimientos |
| `feedback` | Tickets, categorías, comentarios, adjuntos |

> Cada esquema se crea con `CREATE SCHEMA IF NOT EXISTS` antes de ejecutar migraciones. En los modelos se referencia como `DB::table('schema.tabla')`.

---

## Requisitos

```
PHP >= 8.2
Composer >= 2.7
Node.js >= 20
PostgreSQL >= 14
```

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone <repo-url>
cd parque_espana
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con tus credenciales (ver sección de variables de entorno).

### 4. Crear los esquemas en PostgreSQL

```sql
CREATE SCHEMA IF NOT EXISTS memberships;
CREATE SCHEMA IF NOT EXISTS members;
CREATE SCHEMA IF NOT EXISTS billing;
CREATE SCHEMA IF NOT EXISTS catalogs;
CREATE SCHEMA IF NOT EXISTS reservations;
CREATE SCHEMA IF NOT EXISTS lockers;
CREATE SCHEMA IF NOT EXISTS feedback;
```

### 5. Ejecutar migraciones y seeders

```bash
php artisan migrate
php artisan db:seed
```

### 6. Iniciar servidores de desarrollo

```bash
php artisan serve
npm run dev
```

---

## Variables de entorno

### Base de datos

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=parque_espana
DB_USERNAME=
DB_PASSWORD=
```

### DigitalOcean Spaces (almacenamiento de archivos)

```env
DO_SPACES_KEY=
DO_SPACES_SECRET=
DO_SPACES_REGION=
DO_SPACES_BUCKET=
DO_SPACES_ENDPOINT=
DO_SPACES_URL=
```

### Firebase (notificaciones push)

```env
FIREBASE_CREDENTIALS=           # Ruta al JSON de credenciales de servicio
FIREBASE_PROJECT=
FIREBASE_AUTH_TENANT_ID=
FIREBASE_DATABASE_URL=
FIREBASE_STORAGE_DEFAULT_BUCKET=
```

### Conekta (pagos)

```env
CONEKTA_SECRET_KEY=
CONEKTA_PUBLIC_KEY=
```

### API de ubicaciones

```env
COUNTRY_STATE_CITY_API_KEY=     # https://countrystatecity.in
```

---

## Comandos Artisan personalizados

| Comando | Descripción |
|---------|-------------|
| `php artisan memberships:generate-monthly-charges` | Genera los cargos de mensualidad del mes en curso |
| `php artisan memberships:process-age-transitions` | Aplica cambios automáticos de membresía por edad |
| `php artisan memberships:process-domiciliated-payments` | Procesa pagos domiciliados pendientes |
| `php artisan business-ads:expire` | Marca como vencidos los anuncios comerciales expirados |
| `php artisan catalogs:import-locations` | Importa catálogo de países, estados y ciudades |
| `php artisan payments:simulate-spei` | Simula confirmación de pago SPEI (solo desarrollo) |
| `php artisan notifications:prune-device-tokens` | Elimina tokens de dispositivo obsoletos de Firebase |
| `php artisan notifications:send-scheduled-emails` | Procesa la cola de correos programados |
| `php artisan sftp:test-connection` | Verifica la conexión con el servidor SFTP |

---

## Seeders disponibles

```bash
php artisan db:seed                          # Carga todos los seeders

# Seeders individuales relevantes
php artisan db:seed --class=ClubSeeder
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=DocumentTypeSeeder
php artisan db:seed --class=MembershipTypeSeeder
php artisan db:seed --class=BillingConceptSeeder
php artisan db:seed --class=PaymentMethodSeeder
php artisan db:seed --class=LocationCatalogsSeeder
php artisan db:seed --class=SystemVariableSeeder
```

---

## Estructura relevante del proyecto

```
app/
├── Console/Commands/          # Comandos artisan personalizados
├── Http/Controllers/
│   └── Web/AdminClub/         # Controladores del panel administrativo
├── Models/                    # Modelos por módulo (Billing, Members, Memberships, etc.)
└── Services/                  # Servicios de negocio (cargos, pagos, membresías)

resources/js/
├── Components/                # Componentes reutilizables (BaseButton, CustomFileUploadField, etc.)
├── Layouts/                   # AppLayout, Navigation
├── Pages/                     # Páginas Inertia por módulo
│   ├── Members/
│   ├── Billing/
│   ├── Reservations/
│   └── ...
└── constants/                 # Reglas de validación, formatos de fecha

database/
├── migrations/                # Migraciones por esquema y fecha
└── seeders/                   # 40+ seeders de catálogos y configuración inicial
```

---

## Autor

**Tekuno S.A. de C.V.**

---

## Licencia

Proyecto **propietario**. Prohibida su redistribución, modificación o uso comercial sin autorización expresa del autor.
