# Análisis del sistema ParquesEsp para la carga inicial de datos

**Fecha:** 2026-09-30
**Objetivo:** entender cómo está construido el sistema (tablas, relaciones y procesos automáticos) para definir qué información hay que precargar con plantillas antes de que el sistema entre en operación: usuarios/socios, cuentas, membresías, cargos, pagos, historial de pagos y el resto de datos vigentes.

**Fuentes revisadas**

- Base de datos local `PARQUES` (PostgreSQL): estructura real de las 146 tablas, llaves foráneas, índices únicos, restricciones `CHECK` y número de filas actual. Las 252 migraciones del proyecto están aplicadas (ninguna pendiente), así que la estructura descrita aquí es la vigente.
- Código: `app/Models`, `app/Services` (en especial `Billing`, `Access` y `Migration`), `app/Console/Commands`, `app/Observers`, `routes/console.php` y los controladores del panel `AdminClub`.
- Seeders (`database/seeders`) y documentos previos de migración (`database/seeders/data/*.md`).

> Los conteos de filas son de la base local de desarrollo al 2026-09-30. Las filas en tablas de socios/cuentas (`members.members` = 23, `memberships.accounts` = 15, cuentas `TEST-001`…`TEST-015`) son datos de prueba, no datos reales.

---

## 1. Visión general

| Aspecto | Detalle |
| --- | --- |
| Backend | Laravel 12 (PHP 8.3), Inertia.js |
| Frontend | Vue 3 + Vuetify 3 (panel web), API REST para la app móvil |
| Base de datos | PostgreSQL con 18 esquemas (`members`, `memberships`, `billing`, `catalogs`, `clubs`, etc.) |
| Archivos | DigitalOcean Spaces (S3). En BD solo se guarda la ruta del archivo |
| Pagos en línea | Conekta (tarjeta en app, SPEI, domiciliación) |
| Control de acceso | Dispositivos físicos por club (`devices.devices`) que reciben comandos en cola (`devices.commands`) |
| Clubes | 2: **Parque España I (`PE1`, sin IVA)** y **Parque España II (`PE2`, con IVA)** |

Superficies del sistema:

1. **Panel web** con dos contextos: *Administrator* (superadmin: clubes, usuarios, roles, correo, credenciales Conekta, notificaciones, accesos de socios) y *AdminClub* (operación diaria de cada club: socios, cobranza, caja, casilleros, amenidades, reservaciones, actas, publicidad, encuestas, quejas, sitio web).
2. **App móvil** para socios (roles `socio_titular` / `socio_dependiente` por club).
3. **Sitio web público** (contenido en el esquema `website`).

---

## 2. Modelo de datos central

La parte que más importa para la migración es la cadena **Socio → Cuenta → Membresía → Cargo → Pago**:

```
clubs.clubs (PE1, PE2)
   │
   ├── memberships.types (23 tipos, cada uno pertenece a un club)
   │       └── memberships.pricing_rules + pricing_rule_fee_history (cuota por año)
   │
   ├── memberships.account_groups ── agrupa cuentas de la MISMA familia en PE1 y PE2
   │       │
   │       └── memberships.accounts (la "cuenta" o número de socio; tiene club)
   │               │
   │               ├── memberships.account_members ── members.members (personas)
   │               │        (titular / integrante, parentesco, código de acceso)
   │               │
   │               ├── memberships.memberships (membresía por club: tipo, cuota, fechas, estatus)
   │               │
   │               ├── billing.charges (cargos: mensualidades, inscripción, casillero, multas...)
   │               │        ▲
   │               │        │ billing.payment_applications (cuánto de cada pago cubrió cada cargo)
   │               │        │
   │               ├── billing.payments (pagos recibidos: método, folio, fecha, cajero)
   │               │
   │               └── billing.credit_balances (saldo a favor, 1 por cuenta)
   │
   └── members.lockers, amenities.*, devices.*, etc.
```

Conceptos clave:

- **Socio / usuario (`members.members`)**: una persona. Puede pertenecer a varias cuentas (tabla puente `account_members`). Tiene `migration_origin_id` (único), pensado específicamente para guardar el número de socio del sistema anterior.
- **Cuenta (`memberships.accounts`)**: la unidad de cobro. Es `individual` o `family`, tiene estatus (`pending`, `active`, `suspended`, `cancelled`) y un club. Tiene dos números:
  - `membership_number` (obligatorio, único): el sistema lo genera como `PE1-20260930141300123` (código de club + fecha y hora) al dar de alta desde el panel.
  - `internal_account_number` (opcional, único): el número de cuenta "humano" del club. Es el que se busca en cobranza, se imprime en el ticket y se captura en el alta.
- **Grupo de cuentas (`account_groups`)**: une la cuenta de PE1 y la de PE2 de una misma familia. Con esto el sistema aplica paquetes interclub, decide cuál membresía se cobra y bloquea/desbloquea el acceso en ambos parques.
- **Membresía (`memberships.memberships`)**: una por cuenta y club (índice único `account + club`). Guarda el tipo, la cuota mensual vigente (`monthly_fee`), si se factura (`is_billable`), fechas de inicio/fin y la regla de precio o paquete que la justifica.
- **Cargo (`billing.charges`)**: lo que se le cobra a la cuenta. Tiene `amount` (importe original) y `balance` (lo que falta), periodo (`period_year`/`period_month`) y estatus `pending | partial | paid | cancelled`. **No tiene columna de club**: el club sale de `membership_id` → membresía → club.
- **Pago (`billing.payments`)**: dinero recibido. Tiene club, método de pago, fecha, `folio` (único), cajero (`received_by`), estatus `registered | cancelled` y `payment_group_id` (un cobro pagado con varios métodos genera varios pagos con el mismo grupo).
- **Aplicación (`billing.payment_applications`)**: relaciona pago ↔ cargo (único por par). `applied_amount` es el dinero aplicado y `discount` la parte condonada sin dinero. El saldo de un cargo es: `amount − Σ applied_amount − Σ discount`.

---

## 3. Procesos automáticos que afectan la carga

Estos procesos corren solos y **cambian datos a partir de lo que se cargue**. Hay que tenerlos en cuenta al diseñar la plantilla y el importador.

| Proceso | Cuándo corre | Qué hace | Implicación para la migración |
| --- | --- | --- | --- |
| `memberships:generate-monthly-charges` | Día 1 de cada mes, 01:00 | Crea el cargo de mensualidad del mes para cada membresía `active`/`suspended`, principal, facturable, con cuota > 0 y `start_date` ≤ fin del mes. No duplica si ya existe cargo del periodo. | La cuota (`monthly_fee`), `is_billable`, `start_date` y estatus de cada membresía deben quedar correctos: a partir del primer día 1 después de la carga, el sistema cobrará con esos datos. |
| Relleno automático de mensualidades (`ensureMonthlyChargesUpToToday`) | Cada vez que el cajero busca al socio en Cobranza | Revisa desde el **cargo de mensualidad más antiguo** del grupo hasta el mes actual y **crea cualquier mes faltante**. Si la cuenta no tiene ningún cargo, solo crea el mes actual. | Si se importa historial con huecos (meses sin cargo), el sistema los generará como adeudo nuevo. Para evitarlo: importar todos los meses del periodo, o fijar `accounts.billing_backfill_floor` (fecha desde la cual se permite rellenar), o registrar bajas/reactivaciones en `membership_history` (el sistema salta los periodos entre "Baja voluntaria de cuenta" y "Reactivación de cuenta"). |
| `ProcessMembershipDelinquency` | Diario, 02:00 | Si una cuenta (o su grupo) tiene **3 o más meses distintos de mensualidad vencida** (`pending`/`partial`, `due_date` anterior a ayer), pone `access_status = blocked` a todos los integrantes y manda el bloqueo a los torniquetes. Al pagar, desbloquea. | Si se importan mensualidades viejas sin pagar, al día siguiente se bloquea el acceso de esos socios. Si el adeudo previo se carga como un solo cargo de "adeudo anterior", **no** cuenta como mensualidad y no bloquea. Es una decisión de negocio a tomar (ver sección 8). |
| `memberships:process-age-transitions` | Programado cada minuto | Detecta integrantes que por edad deben cambiar de tipo (familiar → solidaria → individual; rangos < 24, 24–26, > 26 años) y los registra en `pending_age_transitions` para aprobación manual. | Requiere **fecha de nacimiento y parentesco** correctos. Tras la carga aparecerán transiciones pendientes; es esperado. |
| Folio automático (`PaymentObserver` + `FolioService`) | Al crear un pago | Si el pago no trae folio, genera `CLUB-SERIE-AAMMDD-NNN`, donde la serie es el código del cajero (`users.code`) o `AUTO`/`SPEI`, y el consecutivo se reinicia por club, serie y día (`billing.folio_sequences`). | Los pagos importados deben traer su folio del sistema anterior (columna única de hasta 60 caracteres). Si no lo traen, el sistema les asigna uno con la fecha del pago y serie `AUTO`. No hace falta "continuar" la numeración anterior, porque la secuencia es diaria. |
| `users:provision-access` (manual) | Se ejecuta a mano | Para cada integrante de cuentas activas sin `access_code`, genera un número de tarjeta, lo guarda en `account_members.access_code` y crea el usuario en todos los dispositivos activos del club. | Si el sistema anterior tiene tarjetas físicas que se seguirán usando, hay que importar su número en `access_code`; si no, se generan nuevas y hay que reimprimir o recodificar. Requiere los dispositivos del club dados de alta. |
| Roles de app (`MembershipAccountMemberObserver`) | Al crear o cambiar un integrante | Si el socio ya tiene usuario de app (`members.user_id`), le asigna el rol `socio_titular` o `socio_dependiente` del club. | Los usuarios de app se crean después (panel Administrator → accesos de socios, con la contraseña por defecto configurada en `mobile_app.variables`). No se piden contraseñas en la plantilla. |
| `billing:process-domiciliated-payments` | Manual/programable | Cobra mensualidades pendientes con la tarjeta domiciliada del socio (Conekta). | Las tarjetas domiciliadas **no se pueden migrar** (son tokens de Conekta). Los socios que domicilian deberán registrar su tarjeta otra vez. |
| `ExpireDailyPassCards`, `MarkReservationNoShows`, `ProcessScheduledDailyPasses` | Cada 15 min / cada minuto | Expiran tarjetas de pase diario, marcan inasistencias y procesan pases programados. | Solo afectan si se migran pases o reservaciones futuras. |

---

## 4. Reglas de cobro que el importador debe respetar

1. **Concepto de mensualidad según composición de la familia.** Hay 9 conceptos en la "familia" de mensualidad (`MONTHLY_FEE`, `MONTHLY_FEE_INTERMEDIATE`, `MONTHLY_FEE_PASS`, `MONTHLY_FEE_PASS_INTERMEDIATE`, `MONTHLY_FEE_PARKS`, `MONTHLY_FEE_PARKS_INTERMEDIATE`, `MONTHLY_FEE_PARKS_FI` y las cuotas de permiso `CUOTA_PERMISO` y `CUOTA_75_PERMISO`). Todos cuentan para: detectar si un mes ya se cobró, el relleno automático y la morosidad. Las mensualidades históricas deben cargarse con alguno de estos códigos y con `period_year`/`period_month`, o el sistema no las reconocerá como mensualidad.
2. **Cuota mensual.** Se resuelve con `memberships.pricing_rules` (por tipo, edad, tipo de origen y si tiene membresía en ambos parques) y el importe de cada año en `pricing_rule_fee_history`. Si la familia tiene cuenta en PE1 y PE2 (mismo `account_group`), se busca un paquete en `interclub_package_rules` (+ `interclub_package_rule_fee_history`): la membresía más reciente queda facturable con la cuota del paquete y la otra queda `is_billable = false`.
3. **Inscripción** según sufijo del tipo: `_BEN` → `CUOTA_INSCRIPCION_BENEFICENCIA`, `_ASC` → `CUOTA_INSCRIPCION_ESPANOLES`, `_PE1` → `CUOTA_INSCRIPCION_PARQUE_I`, resto → `INSCRIPTION`.
4. **Anualidad con descuento** (`billing.annual_discount_rules`, 3 reglas): pagar el año por adelantado en cierto mes da descuento; el descuento se registra como **saldo a favor** (`credit_balances` + `credit_movements`) y se aplica al mes libre (diciembre).
5. **Permisos por ausencia** (`absence_permits`): durante el permiso la mensualidad se cobra al 25 % o 75 % con concepto de permiso, y se puede bloquear acceso y reservaciones.
6. **Pagos por parque.** Un pago pertenece a un club y solo puede aplicarse a cargos de ese club (excepto mensualidades de cuentas del mismo grupo). Por eso **cada cargo importado debe quedar ligado a la membresía del club correcto**.
7. **Subtotal/IVA** del pago se calcula según si el concepto aplica IVA en el club donde se cobra (PE2 sí, PE1 no). Si el sistema anterior guarda el desglose, se puede importar; si no, se calcula.
8. **Casilleros**: la asignación se hace contra un cargo `LOCKERS` (cuota de casillero) de la cuenta; la asignación es por año (`unique locker + año`).
9. **Pases diarios y listas de invitados** generan cargos `GUEST_LIST`.

---

## 5. Inventario de tablas y qué hacer con cada una

Clasificación usada en la columna **Carga inicial**:

| Clave | Significado |
| --- | --- |
| **PRE** | Catálogo ya sembrado por el sistema. Solo se confirma o se completa; no se captura desde cero. |
| **CONF** | Configuración del club. Se precarga con lo que ya existe y el cliente confirma o corrige. |
| **MIGRAR** | Dato maestro o vigente que viene del sistema anterior y hay que cargar. |
| **HIST** | Historial. Se migra solo si se decide conservarlo (ver sección 8). |
| **VIGENTE** | Solo se migran registros abiertos o futuros al momento del corte. |
| **SIS** | Lo genera el sistema al operar. No se migra. |
| **TEC** | Configuración técnica o credenciales. La hace el equipo técnico, nunca por Excel. |

### 5.1 `members` — personas

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `members` | Datos personales de cada socio/usuario: nombre, apellidos, nacimiento, sexo (`H`/`M`), teléfono, correo, ocupación, escuela, lugar de nacimiento (país/estado/ciudad), nacionalidad, estado civil, foto, `migration_origin_id`. | 23 (prueba) | **MIGRAR** (núcleo) |
| `addresses` | Domicilio (calle, colonia, CP, país/estado/ciudad, años en la ciudad, principal). | 0 | **MIGRAR** (si se tiene) |
| `employment_info` | Empresa, dirección y teléfono laboral (1 por socio). | 0 | MIGRAR (opcional) |
| `clinical_histories` | Historia clínica y contacto de emergencia (1 por socio). | 0 | MIGRAR (opcional) |
| `documents` | Expediente: tipo de documento, ruta del archivo, verificado, club (null = válido en ambos). | 0 | MIGRAR si se entregan los archivos |
| `lockers` | Inventario de casilleros (club, número, categoría `ninos/ninas/caballeros/damas`, estatus). | 5,738 | **CONF** (sembrados todos como disponibles; actualizar estatus) |
| `locker_assignments` | Casillero asignado a un socio por año, importe pagado, fechas. | 0 | **VIGENTE** |
| `locker_assignment_histories` | Cambios de casillero. | 0 | HIST |
| `acts` | Actas administrativas (folio único, socio, cuenta, club, infracción). | 0 | VIGENTE (casos abiertos) |
| `act_files` | Archivos de evidencia de un acta. | 0 | VIGENTE (con archivos) |
| `fines` | Multa ligada a un acta (importe, concepto, vencimiento). | 0 | VIGENTE (si sigue pendiente, conciliar con su cargo) |
| `warnings` | Amonestación ligada a un acta, con suspensión opcional. | 0 | VIGENTE (suspensiones activas) |
| `payment_sources` | Tarjetas guardadas en Conekta. | 0 | **SIS** (no migrable) |
| `conekta_customers` | Cliente Conekta por socio y club. | 0 | **SIS** |

### 5.2 `memberships` — cuentas y membresías

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `accounts` | La cuenta: número, tipo, estatus, club, grupo, cancelación, cuenta de origen (separaciones), número interno, `billing_backfill_floor`. | 15 (prueba) | **MIGRAR** (núcleo) |
| `account_groups` | Grupo que une cuentas de PE1 y PE2 de la misma familia. | 3 (prueba) | **MIGRAR** (solo si hay familias en ambos parques) |
| `account_members` | Quién pertenece a cada cuenta: titular, parentesco, código de acceso, vigencia y estatus de acceso. | 31 (prueba) | **MIGRAR** (núcleo) |
| `memberships` | Membresía por club: tipo, tipo de origen, cuota, reparto, fechas, estatus, facturable, regla de precio/paquete. | 15 (prueba) | **MIGRAR** (núcleo) |
| `account_fiscal_data` | Datos de facturación de la cuenta (RFC, régimen, uso CFDI, CP). | 0 | MIGRAR (si facturan) |
| `absence_permits` | Permisos por ausencia (fechas, % de cobro, bloqueos). | 0 | VIGENTE |
| `account_reactivations` | Registro de reactivaciones. | 0 | HIST |
| `membership_history` | Bitácora de cambios de tipo/cuota y de bajas/reactivaciones. **La usa el relleno automático** para no cobrar meses en que la cuenta estuvo dada de baja. | 0 | HIST, pero **necesaria** para cuentas con baja/reactivación dentro del periodo migrado |
| `pending_age_transitions` | Transiciones de edad pendientes de aprobar. | 0 | SIS (el proceso las detecta solo) |
| `types` | 23 tipos de membresía (6 de PE1, 17 de PE2). | 23 | **PRE** |
| `type_required_documents` | Documentos que pide cada tipo. | 156 | PRE |
| `pricing_rules` / `pricing_rule_fee_history` | Reglas de cuota y su importe por año. | 88 / 88 | **CONF** (confirmar importes vigentes y de años anteriores si se migra historial) |
| `interclub_package_rules` / `..._fee_history` | Paquetes para familias con membresía en ambos parques. | 32 / 32 | CONF |
| `separation_reasons` | Motivos de separación de cuenta (solo "Divorcio"). | 1 | PRE |

### 5.3 `billing` — cobranza

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `concepts` | 162 conceptos de cobro. Muchos códigos (`01A`, `12`, `70x`, `54x`, etc.) vienen del sistema anterior; varios están inactivos. Incluye `CUOTA_ADEUDO_ANTERIOR` y `CUOTA_ADEUDO_AMBOS_PARQUES`. | 162 | **PRE** (confirmar el mapeo de conceptos anteriores) |
| `concept_club_amounts` | Importe vigente de cada concepto por club (y si aplica IVA). | 0 | **CONF** |
| `payment_methods` | 6 métodos: `CASH`, `BANK_TRANSFER`, `APP_PAYMENT`, `CHECK`, `CREDIT_CARD`, `DEBIT_CARD`. | 6 | PRE |
| `club_payment_methods` | Métodos aceptados por club (+ llaves Conekta). | 12 | CONF (sin llaves) / TEC (llaves) |
| `annual_discount_rules` | Reglas de descuento por pago anual. | 3 | PRE/CONF |
| `charges` | Cargos. | 0 | **MIGRAR** (núcleo financiero) |
| `payments` | Pagos. | 0 | **MIGRAR** (núcleo financiero) |
| `payment_applications` | Relación pago ↔ cargo. | 0 | **MIGRAR** (núcleo financiero) |
| `credit_balances` | Saldo a favor (1 por cuenta). | 0 | MIGRAR (saldo al corte) |
| `credit_movements` | Movimientos que explican el saldo a favor. | 0 | MIGRAR si se migra historial |
| `collection_notes` | Notas de cobranza por cuenta. | 0 | VIGENTE |
| `folio_sequences` | Consecutivo de folios por club/serie/día. | 0 | SIS |
| `cash_cuts`, `cash_cut_denominations`, `global_cash_cuts` | Cortes de caja por cajero y globales. | 0 | HIST (normalmente no) |
| `spei_orders` | Órdenes SPEI de Conekta. | 0 | SIS |

### 5.4 `catalogs` — catálogos generales

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `countries` / `states` / `cities` | Ubicaciones (México, España, EUA). | 3 / 156 / 37,506 | PRE |
| `nationalities` | Nacionalidades. | 194 | PRE (ver hallazgo 8.4: `members.nationality_id` no apunta aquí) |
| `marital_statuses` | Estados civiles. | 7 | PRE |
| `relationships` | Parentescos: Titular, Cónyuge, Hijo(a), Madre. | 4 | PRE (¿faltan otros parentescos del sistema anterior?) |
| `relationships_document_types` | Documentos por parentesco. | 22 | PRE |
| `document_types` | Tipos de documento. | 17 | PRE |
| `cancellation_reasons` | Falta de pago, defunción, divorcio, expulsión, permiso, voluntaria. | 6 | PRE |

### 5.5 `clubs`, `files` y configuración general

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `clubs.clubs` | Datos de cada club: fiscales, contacto, redes, logo, mapa, IVA. | 2 | CONF |
| `clubs.club_addresses` | Domicilio del club. | 2 | CONF |
| `clubs.rules` | Reglas de reservación (máximo de reservas activas, días de anticipación, mismo día). | 0 | CONF |
| `files.files` | Catálogo de formatos (solicitud de usuario, de permiso, de locker). | 4 | PRE |
| `files.club_files` / `club_file_counters` | Archivo de cada formato por club y su consecutivo. | 0 / 0 | CONF (subir los formatos del club) |
| `reservations.system_variables` | Variables de reservación por club. | 9 | CONF |
| `guest_lists.variables` | Precios y topes de invitados por club. | 10 | CONF |
| `mobile_app.variables` | Variables de la app (contraseña inicial de socios). | 1 | TEC |
| `public.email_configs` | SMTP por club. | 2 | TEC |
| `devices.devices` | Torniquetes/controladores de acceso (IP, puerto, usuario, contraseña). | 2 | TEC |

### 5.6 Usuarios del sistema y permisos (`public`)

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `users` | Cuentas de inicio de sesión: personal administrativo **y** socios con app. `code` es la serie de folios del cajero. | 3 | **MIGRAR** solo personal (nombre, correo, club, rol, código de cajero). Socios con app: se crean después. |
| `user_clubs` | A qué clubes tiene acceso cada usuario. | 2 | MIGRAR (personal) |
| `roles`, `permissions`, `role_has_permissions`, `permission_has_contexts`, `contexts`, `model_has_roles` | Roles y permisos (11 roles: superadmin, admin de club, cobranza, gerentes, amenidades, socio titular/dependiente). | varias | PRE; asignar rol al personal |
| `notifications*`, `email_logs`, `device_tokens`, `audits`, `jobs`, `cache`, `sessions` | Bitácoras y colas. | 0 | SIS |

### 5.7 Amenidades, clases y reservaciones

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `amenities.amenities` | Amenidades por club (pádel, jardines, tenis, frontón, alberca en PE1). | 5 | CONF (PE2 sin amenidades) |
| `amenities.resources` | Canchas/espacios reservables, capacidad y duración de turno. | 23 | CONF |
| `amenities.schedules` | Horario por día. | 30 | CONF |
| `amenities.locations` | Coordenadas del recurso. | 0 | CONF (opcional) |
| `amenities.blocked_periods` | Bloqueos de un recurso. | 0 | VIGENTE (futuros) |
| `classes.coaches`, `coach_specialties`, `coach_availabilities`, `specialties` | Entrenadores y su disponibilidad. | 0 | CONF (en la BD local están vacías aunque hay seeder) |
| `classes.class_schedules` / `class_enrollments` | Clases semanales e inscritos. | 0 | CONF / VIGENTE |
| `reservations.reservations` | Reservaciones (recurso, socio, fechas, jardín, clase). | 0 | VIGENTE (solo futuras) |
| `reservations.status` | Estatus: activa, cancelada, finalizada, inasistencia, asistido. | 5 | PRE |

### 5.8 Invitados, pases y acceso

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `guest_lists.guest_lists` / `guest_list_items` | Listas de invitados (con o sin reservación) y sus invitados. | 0 | VIGENTE (eventos futuros) |
| `guest_lists.day_passes` / `day_pass_visitors` | Pases de día vendidos. | 0 | HIST |
| `guest_lists.visitor_incidents` | Incidentes de visitantes. | 0 | VIGENTE (con seguimiento) |
| `guest_lists.cafeteria_visits` | Visitas a cafetería con documento retenido. | 0 | SIS |
| `devices.guest_users`, `daily_pass_cards`, `scheduled_daily_passes`, `scheduled_daily_pass_visitors` | Tarjetas temporales y pases programados. | 0 | VIGENTE (solo programados a futuro) |
| `devices.commands` | Cola de comandos a los torniquetes. | 0 | SIS |

### 5.9 Otros módulos

| Tabla | Para qué sirve | Filas | Carga inicial |
| --- | --- | ---: | --- |
| `advertising.physical_ads`, `physical_ad_sizes` | Anuncios físicos rentados a socios (con cargo). | 0 / 8 | VIGENTE (contratos activos) / CONF |
| `advertising.business_ads`, `business_categories`, `business_ad_statuses` | Directorio de negocios de socios. | 0 / 0 / 6 | VIGENTE (si hay publicados) |
| `announcements.*` | Comunicados, torneos, eventos. | 0 | Inicia vacío |
| `surveys.*` | Encuestas. | 0 | Inicia vacío |
| `feedback.*` | Quejas y sugerencias (tickets). Catálogos sembrados. | 0 | Inicia vacío (salvo casos abiertos) |
| `website.*` | Contenido del sitio web (carrusel, eventos, tour virtual). | 0 | CONF (contenido, no migración) |

---

## 6. Información que hay que cargar, en orden

Orden de dependencias (cada bloque necesita que el anterior ya exista):

```
0. Catálogos y configuración  →  1. Personal  →  2. Socios  →  3. Cuentas + grupos
→  4. Membresías  →  5. Integrantes  →  6. Cargos  →  7. Pagos  →  8. Aplicaciones
→  9. Saldos a favor / control  →  10. Casilleros, permisos, actas, reservas futuras
→  11. Accesos (tarjetas) y usuarios de app
```

### 6.1 Núcleo (imprescindible para operar)

| # | Bloque | Datos mínimos por fila | Llave que lo identifica | Se relaciona por |
| --- | --- | --- | --- | --- |
| 1 | **Personal** (`users`, `user_clubs`, rol) | Nombre, correo, club(es), rol, código de cajero | Correo | — |
| 2 | **Socios** (`members`) | Número de socio anterior, nombre, apellido paterno y materno, fecha de nacimiento, sexo, teléfono, correo; opcional: lugar de nacimiento, nacionalidad, estado civil, ocupación, escuela | `migration_origin_id` = número de socio anterior | — |
| 3 | **Cuentas** (`accounts`) | Número de cuenta, club, individual/familiar, estatus, fecha de cancelación y motivo si aplica, grupo si tiene cuenta en el otro parque | Número de cuenta (ver 8.1) | Club |
| 4 | **Membresías** (`memberships`) | Cuenta, club, tipo de membresía, fecha de inicio, estatus, cuota mensual vigente, si se factura | Cuenta + club | Cuenta, tipo |
| 5 | **Integrantes** (`account_members`) | Cuenta, número de socio, titular sí/no, parentesco; opcional: número de tarjeta de acceso actual | Cuenta + socio | Cuenta, socio |
| 6 | **Cargos** (`charges`) | Referencia/folio del cargo, cuenta, club, concepto (código), importe, fecha de emisión, vencimiento, año y mes del periodo, estatus (si está cancelado, fecha y motivo) | Referencia del cargo (no existe columna: iría en `metadata`) | Cuenta + club → membresía |
| 7 | **Pagos** (`payments`) | Folio del pago, cuenta, club, fecha y hora, método, importe, referencia bancaria/banco/cheque, cajero (si se conoce), estatus (cancelado: fecha y motivo) | `folio` (único) | Cuenta, club, método |
| 8 | **Aplicaciones** (`payment_applications`) | Folio del pago, referencia del cargo, importe aplicado, descuento | Pago + cargo | Pago, cargo |
| 9 | **Saldo a favor** (`credit_balances`) | Cuenta, importe al corte | Cuenta (1 fila por cuenta) | Cuenta |
| 10 | **Saldo de control** (no se guarda como cargo) | Cuenta, club, fecha de corte, saldo pendiente, vencido y a favor que reconoce el cliente | Cuenta + club | Sirve para conciliar lo importado |

El saldo de cada cargo (`balance`) y su estatus (`pending`/`partial`/`paid`) **no se capturan**: se calculan a partir de importe − aplicaciones − descuentos.

### 6.2 Complementario del socio

Domicilio, información laboral, historia clínica y contacto de emergencia, datos fiscales de la cuenta, documentos (tipo + nombre del archivo; los archivos se entregan aparte y se suben a Spaces).

### 6.3 Estado vigente de otros módulos

- **Casilleros**: estatus real del inventario y asignaciones del año en curso (con su cargo `LOCKERS` en finanzas).
- **Permisos por ausencia** activos o futuros.
- **Actas con multa pendiente o suspensión vigente** (la multa también es un cargo: cargarla una sola vez).
- **Reservaciones, listas de invitados, clases con inscritos y pases programados** a futuro.
- **Anuncios físicos** con contrato vigente.
- **Notas de cobranza** vigentes.

### 6.4 Configuración a confirmar (no es migración, pero debe estar lista)

Importes por concepto y club (`concept_club_amounts`), cuotas por año (`pricing_rule_fee_history`, incluidos años anteriores si se migra historial), métodos por club, formatos del club (`files.club_files`), dispositivos de acceso, SMTP, llaves de Conekta, amenidades y horarios de PE2.

### 6.5 Lo que no se captura

IDs internos, roles/permisos técnicos, contraseñas, llaves de Conekta/SMTP/dispositivos, tokens de tarjeta, folios consecutivos, cortes de caja, colas de comandos, bitácoras, transiciones de edad, y los catálogos grandes ya sembrados (ciudades, nacionalidades).

---

## 7. Importador existente (`php artisan migrate:data`)

Ya existe un importador en `app/Services/Migration`, invocado con:

```
php artisan migrate:data <archivo.xlsx> [--dry-run] [--only=usuarios,membresias,...]
```

- Lee las hojas **por posición** (no por nombre): hoja 2 Usuarios, 3 Membresías, 4 Integrantes, 5 Domicilios, 6 Empleo, 8 Historial por período, 9 Casilleros. Encabezados en la fila 2 y datos desde la fila 4.
- Busca las columnas por nombre exacto (`ID_ORIGEN`, `NO_CUENTA`, `PARQUE`, `TIPO_MEMBRESIA`, `CONCEPTO_CODIGO`, `MONTO_CARGO`, `MONTO_PAGADO`, `PAGO_REF`, etc.).
- Todo corre en una transacción; `--dry-run` hace rollback al final.
- Precarga clubes, tipos, conceptos y métodos por **código** (`PE1`, `PE1_FAM`, `MONTHLY_FEE`, `CASH`).

Cómo arma cada parte:

| Importador | Qué crea | Observaciones |
| --- | --- | --- |
| Usuarios | `members` por `migration_origin_id` | **Error:** intenta guardar `nationality` y `marital_status`, columnas que no existen (las reales son `nationality_id` y `marital_status_id`). Con esas columnas en el Excel falla cada fila. |
| Membresías | `accounts` + `memberships` + grupos | Usa `NO_CUENTA` como `membership_number` (no llena `internal_account_number`). Deduce familiar/individual si el código tiene `_FAM`. Calcula la cuota con las reglas de precio vigentes hoy, no con la cuota real que pagaba el socio. Aplica paquete interclub a grupos con ambos parques. |
| Integrantes | `account_members` | Busca parentesco por nombre exacto. |
| Historial por período | `charges`, `payments`, `payment_applications` | Espera **una fila por cargo con su pago en la misma fila** (no hojas separadas de cargos, pagos y aplicaciones). Deduplica cargos por cuenta+club+concepto+periodo+monto. Si no hay método de pago, usa el primero del catálogo. No maneja pagos o cargos cancelados, saldo a favor ni descuentos. |
| Casilleros | `lockers` + `locker_assignments` | — |

No existen importadores para: personal, datos fiscales, historia clínica, documentos, saldos a favor, permisos, actas, reservaciones ni los demás módulos.

**Conclusión:** la plantilla nueva (`Plantilla_Migracion_Cliente.xlsx` / `Plantilla_Migracion_Unificada.xlsx`, con hojas separadas de Cargos, Pagos y Aplicaciones) **no es compatible** con este importador. Hay que adaptarlo (leer por nombre de hoja, nuevas hojas financieras, corregir el mapeo de socios) una vez que la estructura de la plantilla quede cerrada.

---

## 8. Hallazgos y decisiones pendientes

### 8.1 ¿Qué número identifica a la cuenta?

El sistema tiene `membership_number` (generado, único, obligatorio) e `internal_account_number` (el número del club, buscable y único). El importador actual mete el número anterior en `membership_number`. **Decisión:** usar el número anterior en ambos, o generar `membership_number` y guardar el anterior en `internal_account_number` (más consistente con las altas nuevas). En cualquier caso, **una misma referencia de cuenta debe usarse en todas las hojas** (cuentas, integrantes, cargos, pagos, casilleros).

### 8.2 ¿Desde cuándo se migra el historial financiero y cómo se representa el adeudo previo?

Opciones:

- **A. Historial completo del periodo** (cargo por cargo, pago por pago): conserva el detalle y la morosidad funciona igual que en el sistema anterior. Es más trabajo de captura.
- **B. Adeudo previo como un solo cargo** con el concepto existente `CUOTA_ADEUDO_ANTERIOR` (o `CUOTA_ADEUDO_AMBOS_PARQUES`) + historial desde la fecha de inicio elegida. Ese cargo no cuenta como mensualidad: **no bloquea el acceso por morosidad** ni se desglosa por mes.

En ambos casos hay que fijar `billing_backfill_floor` o asegurar que no queden meses sin cargo, para que el relleno automático no genere mensualidades de más (sección 3). A diferencia de lo anotado en documentos previos, **sí existe** un concepto para adeudo anterior (`CUOTA_ADEUDO_ANTERIOR`).

### 8.3 Morosidad inmediata

Con la opción A, las cuentas con 3 o más mensualidades vencidas quedarán bloqueadas en el torniquete la madrugada siguiente a la carga. Confirmar si es lo deseado o si se debe desactivar el proceso durante la transición.

### 8.4 Nacionalidad apunta al catálogo de países

`members.nationality_id` tiene llave foránea hacia `catalogs.countries` (3 países), no hacia `catalogs.nationalities` (194). Con la estructura actual solo se pueden registrar nacionalidades mexicana, española o estadounidense. Conviene corregirlo antes de migrar socios de otras nacionalidades.

### 8.5 Multas registradas como mensualidad

`ActController` crea el cargo de la multa con `concept_id = 5` fijo, que en la BD es `MONTHLY_FEE_PASS` (mensualidad de pase). Efectos: la multa cuenta como mensualidad para morosidad y puede hacer que el sistema crea que el mes ya está cobrado. Si se migran multas pendientes, cargarlas con un concepto propio y corregir este código.

### 8.6 Otros puntos

- `members.state` y `members.city` (texto) están en desuso: el lugar de nacimiento usa `birth_country_id` / `birth_state_id` / `birth_city_id`.
- Las cuentas separadas no tienen campo para la fecha de separación ni el documento de soporte (solo `origin_account_id` y `separation_reason`).
- Los cargos no tienen columna para la referencia del sistema anterior; se guardaría en `metadata` (JSON), como ya hace el importador (`imported: true`).
- El catálogo de parentescos solo tiene 4 valores (Titular, Cónyuge, Hijo(a), Madre). Revisar contra los parentescos del sistema anterior (padre, nieto, etc.).
- `ProcessMembershipAgeTransitions` está programado **cada minuto** (la línea diaria está comentada en `routes/console.php`); probablemente debería volver a diario.
- En la BD local, `classes.coaches` y `classes.specialties` están vacías aunque hay seeders con datos; PE2 no tiene amenidades configuradas.
- PE1 y PE2 tienen la misma razón social, RFC y URL de facturación en los seeders; verificar con el cliente.
- Las tarjetas domiciliadas (Conekta) no se migran; los socios deberán volver a registrarlas.

### 8.7 Información que hay que pedir al cliente antes de cerrar la plantilla

1. Una exportación real (aunque sea parcial) de socios, cuentas e integrantes, para confirmar si cada integrante tiene número propio y cómo se identifica la cuenta en cada parque.
2. Fecha de corte y fecha desde la que se quiere conservar historial de cargos y pagos.
3. Si el sistema anterior guarda la relación pago → cargo (o solo pagos y cargos por separado).
4. Tabla de equivalencia entre sus conceptos y métodos de pago y los del catálogo.
5. Si hay tarjetas de acceso físicas vigentes y su numeración.
6. Lista del personal que operará (nombre, correo, club, función, código de cajero).
7. Qué módulos tienen registros abiertos hoy (casilleros, permisos, actas, reservas futuras, anuncios).

---

## 9. Cómo validar la carga

1. Cada integrante apunta a un socio y una cuenta existentes; cada cuenta tiene exactamente un titular.
2. Cada cuenta activa tiene al menos una membresía activa con cuota > 0 (o `is_billable = false` justificado por paquete interclub).
3. Cada cargo tiene membresía (club) y concepto válidos; cada aplicación apunta a un pago y un cargo existentes; ningún cargo queda con saldo negativo.
4. Por cuenta y club: Σ saldos de cargos − saldo a favor = saldo de control que reconoce el cliente.
5. `memberships:generate-monthly-charges --dry-run` para el mes siguiente no genera cargos inesperados ni omite cuentas.
6. Buscar varias cuentas en Cobranza y confirmar que el relleno automático no creó meses de más.
7. Revisar qué cuentas quedarían morosas (3+ meses) antes de dejar correr el proceso de bloqueo.
8. Casilleros ocupados en el inventario coinciden con las asignaciones del año.
9. Probar un caso de cada tipo: cuenta individual, familiar, familia en ambos parques, pago que cubre varios cargos, cargo con pagos parciales, pago cancelado, descuento y saldo a favor.

---

## 10. Estructura propuesta de `Plantilla_Migracion_Cliente.xlsx`

**Fecha:** 2026-09-30. Un solo libro con **20 pestañas**: Instrucciones, Catalogos (ya terminada) y **18 de captura**, en el orden en que hay que cargarlas (cada pestaña usa datos de las anteriores). El formato visual es el del borrador `public/Plantilla_Migracion_DatosDP_230626.xlsx`: encabezados sin acentos, rojo para obligatorio, azul para opcional y fila 3 con la instrucción. El cliente llama a las personas **usuarios**, no socios.

| # | Pestaña | Tablas del sistema | Una fila por… | Qué lleva |
| --- | --- | --- | --- | --- |
| 00 | Instrucciones | — | — | Leyenda de colores, orden de llenado y reglas generales |
| 01 | Catalogos | varios | — | Terminada: catálogos alineados con la BD |
| **Clubes** | | | | |
| 02 | Clubes | `clubs`, `club_addresses`, `rules` | club | Datos generales, fiscales, contacto, redes sociales, domicilio y reglas de reservación. Precargado con PE1 y PE2; el cliente solo confirma |
| 03 | Metodos por club | `club_payment_methods` | club + método | Qué métodos acepta cada parque (sin llaves de Conekta) |
| 04 | Importes por club | `concept_club_amounts` | club + concepto | Importe vigente y si aplica IVA |
| 05 | Personal | `users`, `user_clubs` | persona | Nombre, correo, club, rol y **serie de caja**. En Fox los recibos tienen series (A, C, D, F, H, I, J) y en el sistema la serie es el código del cajero (`users.code`) |
| **Usuarios** | | | | |
| 06 | Usuarios | `members` | persona | `NUMERO_USUARIO` (el número de Fox, ej. USR-352), nombre, apellidos, nacimiento, sexo, teléfono, correo, lugar de nacimiento, nacionalidad y estado civil |
| 07 | Domicilios | `addresses` | domicilio | Por número de usuario |
| 08 | Informacion laboral | `employment_info` | usuario | Opcional |
| 09 | Informacion medica | `clinical_histories` | usuario | Datos médicos y contacto de emergencia (es la misma tabla). Opcional |
| **Cuentas** | | | | |
| 10 | Cuentas | `accounts` + `memberships` + `account_groups` | cuenta | Número de cuenta, club, tipo de membresía, estatus, fecha de inicio, **cuota mensual actual**, si se cobra, cuenta en el otro parque, cuenta de origen, y cancelación (fecha y motivo) si aplica |
| 11 | Integrantes | `account_members` | usuario en cuenta | Número de cuenta, número de usuario, si es titular, parentesco y tarjeta de acceso actual |
| 12 | Datos fiscales | `account_fiscal_data` | cuenta | Opcional |
| **Dinero** | | | | |
| 13 | Cargos | `charges` | cargo | Referencia del cargo, cuenta, concepto, periodo, importe, vencimiento y estatus. Incluye los **pendientes** |
| 14 | Pagos | `payments` | línea de pago | Folio del recibo, **club donde se cobró**, fecha, método, importe, referencia, banco o cheque, cajero y estatus |
| 15 | Aplicaciones | `payment_applications` | recibo + cargo | Qué cargos cubrió cada recibo, con importe y descuento |
| 16 | Saldos al corte | `credit_balances` + control | cuenta | Fecha de corte, saldo pendiente, saldo a favor y nota de cobranza (`collection_notes`) |
| **Operación vigente** | | | | |
| 17 | Casilleros | `lockers` | casillero | Estatus actual del inventario |
| 18 | Asignaciones casilleros | `locker_assignments` | asignación | Casillero, usuario, año, fechas e importe |
| 19 | Permisos de ausencia | `absence_permits` | permiso | Solo los vigentes. Fox cobró 671 cuotas de permiso en 2026, así que hay permisos activos |

### Decisiones de diseño

- **Dos llaves en todo el libro:** `NUMERO_USUARIO` y `NO_CUENTA`, tal como vienen de Fox. No se repiten nombres en otras pestañas.
- **Cuenta y membresía en una sola pestaña.** En el sistema cada cuenta pertenece a un club, así que una fila es una cuenta con su membresía.
- **Grupo entre parques:** en lugar del código `PE01-x:PE02-y` del borrador, una columna `CUENTA EN EL OTRO PARQUE`. El importador arma el `account_group`.
- **Recibos en tres pestañas: Cargos, Pagos y Aplicaciones.** Así se evita el problema del borrador, que repetía cada recibo en muchas filas (combinación de cargos por formas de pago).
  - Las aplicaciones van por recibo y no por forma de pago, porque Fox sabe qué cargos cubrió cada recibo pero no con qué tarjeta se pagó cada uno.
  - El importador reparte las formas de pago del recibo entre sus cargos al cargarlo.
- **Adeudo mes por mes:** las mensualidades pendientes van como cargos con su año y mes, no como un solo monto. Así el sistema calcula bien la morosidad y el relleno automático no vuelve a generar esos meses. Lo que en Fox ya es "Cuota adeudo anterior" se carga con ese concepto (`CUOTA_ADEUDO_ANTERIOR`).
- **Saldos al corte** sirve para comprobar que lo cargado cuadra con lo que dice Fox. No crea deuda nueva; solo carga el saldo a favor.

### Fuera de la plantilla por ahora

- **Amenidades, clases, reservaciones, listas de invitados, pases y dispositivos:** se configuran en el sistema; es poco probable que Fox tenga esos módulos.
- **Actas y multas:** solo si hay casos abiertos.
- **Anuncios físicos:** solo si hay contratos vigentes (en Fox hay cobros de anuncios).
- **Documentos y fotos:** requieren entregar los archivos aparte.
- **No migrables:** cortes de caja, SPEI y tarjetas domiciliadas.

Cualquiera de estos bloques se puede agregar después como pestaña nueva sin mover las demás.
