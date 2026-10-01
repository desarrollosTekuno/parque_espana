# Matriz de cobertura de `Plantilla_Migracion_Cliente.xlsx`

**Fecha:** 2026-10-01
**Para qué sirve:** comprobar, columna por columna, que lo que se capture en la plantilla alcanza para dejar el sistema funcionando, sin depender de la palabra de nadie. Cada columna de cada tabla que toca la migración tiene que tener un origen.

**Cómo se hizo:**
- **Base de datos:** se leyó la estructura real de la BD `PARQUES` (todas las migraciones aplicadas): columnas, obligatoriedad y valores por defecto.
- **Mapeo:** cada columna se cruzó con las pestañas y columnas de la plantilla (versión del 2026-10-01, 19 pestañas).
- **Revisión automática en dos sentidos:**
  1. Que ninguna columna de la BD se quede sin origen.
  2. Que ninguna columna de la plantilla se quede sin destino.
- **Reglas del sistema:** se tomaron directamente del código. Al final se cita archivo y línea de cada una.

## Cómo leer la matriz

| Origen | Significado |
| --- | --- |
| **Plantilla** | El dato lo captura la persona: pestaña · columna |
| **Lo calcula la carga** | El programa de carga lo obtiene de otros datos de la plantilla o de una regla del sistema (igual que lo hace la pantalla de alta) |
| **Sistema / valor por defecto** | Lo pone la base de datos (identificadores, fechas de creación, valores por defecto) |
| **No aplica** | La columna no se usa en la migración (con el motivo) |
| **FALTANTE** | No tiene origen. Es un hueco que hay que resolver |

"Obligatoria en BD" = la columna no acepta vacío. "Con valor por defecto" = no acepta vacío, pero la base pone un valor si no se manda.

## Resultado

| Concepto | Cantidad |
| --- | ---: |
| Tablas revisadas | 33 |
| Columnas revisadas | 428 |
| Vienen de la plantilla | 216 |
| Las calcula la carga | 80 |
| Sistema / valor por defecto | 99 |
| No aplican | 33 |
| **FALTANTES** | **0** |
| Columnas de la plantilla sin destino en la BD | **0** |

**Todas las columnas tienen origen y todas las columnas de la plantilla tienen destino.**

Cambios desde la versión anterior (16 faltantes):
- **Aplicaciones** quedó dentro de Cargos: FOLIO DEL RECIBO, IMPORTE PAGADO y DESCUENTO llenan `billing.payment_applications`.
- **Casilleros** se creó. El inventario (`members.lockers`) ya está en el sistema, así que la pestaña solo asigna.
- **Saldo a favor** (`billing.credit_balances`) pasa a "No aplica": solo lo usa el pago anual y ninguna pantalla lo muestra. La pestaña se llama ahora **Notas de cobranza**.
- Identificadores estandarizados: **NUMERO DE CUENTA** (la cuenta, el "No. Cuenta" de pantalla) e **ID DE USUARIO** (la persona, `members.members.migration_origin_id`).
- Cargos ya no lleva persona ni club: el cargo va al titular y el club sale de la cuenta.
- Nuevas: **Entrenadores** (con su horario, una fila por bloque), **Reservaciones** (pasadas y futuras; la clase es una reservación con ENTRENADOR) y **Listas de invitados** (una fila por invitado). En Catálogos se agregó **AMENIDADES Y RECURSOS**.
- Fuera por decisión: clases grupales (`classes.class_schedules`), inscritos (`class_enrollments`: el sistema no tiene pantalla para inscribir) y anuncios (se capturan en el sistema).

## Reglas del alta que valida el sistema y cómo las respeta la plantilla

| Regla del sistema | Dónde la valida el código | Cómo la cubre la plantilla |
| --- | --- | --- |
| Cada membresía tiene exactamente un titular | `MemberController.php:2130` | Integrantes · ES TITULAR (instrucción: un solo titular) |
| Individual y Solidaria no permiten varios integrantes | `MemberController.php:2140` | Instrucción en Integrantes · ES TITULAR |
| Los no titulares deben tener parentesco | `MemberController.php:2151` | Integrantes · PARENTESCO (obligatorio si no es titular) |
| Solidaria requiere membresía familiar de origen | `MemberController.php:2165` y `:2175` | Membresias · TIPO DE MEMBRESIA ANTERIOR (obligatorio si es Solidaria) |
| Hijo(a) debe ser menor de 24 años | `MemberController.php:1445` | Instrucción en Integrantes · PARENTESCO |
| Familia en 2 parques: el titular debe ser el mismo | `MemberController.php:2078` | Instrucción en Membresias · CUENTA EN EL OTRO PARQUE |
| Un titular no puede tener 2 membresías en el mismo club | `MemberController.php:2093` | Una fila por membresía y club; la carga lo valida |
| La misma persona no se duplica entre parques | `MemberController.php:2180-2300` (reutiliza integrantes) | Usuarios · ID DE USUARIO único por persona, el mismo en ambos parques |
| La nacionalidad es un país (3 opciones) | `MemberController.php:2859` (`'nationalities' => $countries`) | Usuarios · NACIONALIDAD: Mexicana, Española o Estadounidense |
| Al dar de alta se registra el historial de la membresía | `MemberController.php:2462` | La carga escribe "Alta de membresía" en `membership_history` |
| Al dar de alta se crea la tarjeta de acceso | `MemberController.php:2536` | Integrantes · NUMERO DE TARJETA DE ACCESO; si va vacío, se genera |
| Al dar de alta se cobran inscripción y mensualidad | `MemberController.php:2491` | La carga **no** genera esos cargos; el historial entra por Cargos |
| Número interno de cuenta único | `MemberController.php:1927` | Membresias · NUMERO DE CUENTA, único |

## Procesos automáticos que dependen de lo que se cargue

| Proceso | Código | Qué necesita de la plantilla |
| --- | --- | --- |
| La cuota mensual sale de la regla de precios del año | `Membership.php:73` (`resolveLiveMonthlyFee`) | Tipo, tipo anterior, edad del titular, otro parque. La carga asigna la regla; CUOTA MENSUAL sirve para comprobar |
| Generación de mensualidades (día 1, 01:00) | `routes/console.php:21` | Estatus, fecha de inicio y fin, genera cobro |
| Relleno de meses faltantes al buscar al socio | `MembershipChargeService.php:578` | Mensualidades con año y mes en Cargos; fecha de corte como piso (`:720`); periodos de baja en el historial (`:671`) |
| Concepto de mensualidad según composición | `MembershipChargeService.php:942` | Tipo de membresía y membresía en el otro parque |
| Vencimiento de mensualidades (día 10) | `MembershipChargeService.php:1079` | Cargos · FECHA DE VENCIMIENTO (si va vacío, día 10) |
| Bloqueo por morosidad: 3 o más meses vencidos (diario 02:00) | `MembershipDelinquencyService.php:12, 52` y `routes/console.php:27` | Mensualidades pendientes con periodo y vencimiento correctos |
| Transiciones por edad (actualmente cada minuto) | `routes/console.php:23` | Fecha de nacimiento y parentesco |
| Cobro de caja: una línea por forma de pago, mismo grupo | `PaymentRegistrationService.php:122, 300` | Pagos · FOLIO DEL RECIBO (agrupa) |
| El pago se registra en el club donde se cobra; la terminal del otro parque se guarda aparte | `PaymentRegistrationService.php:195, 327` | Pagos · CLUB DONDE SE COBRO y PARQUE DE LA FORMA DE PAGO |
| Canal de liquidación para cortes y reportes | `PaymentRegistrationService.php:316` | Pagos · METODO DE PAGO (la carga pone `settlement_channel`) |
| Un pago solo cubre cargos de su club (salvo mensualidades del grupo) | `PaymentRegistrationService.php:414` | El club del cargo sale de la cuenta; Pagos · CLUB DONDE SE COBRO |
| Folio automático si no viene | `FolioService.php:16` | Pagos · FOLIO DEL RECIBO (se conserva el de Fox) |
| Pago cancelado no reduce el saldo | `PaymentCancellationService.php:146` | Pagos · CANCELADO |
| El casillero se ve pagado por su cargo LOCKERS ligado al casillero | `LockerAssignmentController.php:161` y `CollectionController.php:280` | Casilleros · REFERENCIA DEL CARGO (la carga pone el cargo a nombre de la persona del casillero) |

## Reglas para la carga (no las captura la persona)

- Buscar los catálogos por nombre, aceptando mayúsculas o minúsculas y con o sin acentos. Los países se muestran en español (México, España, Estados Unidos), aunque en la BD están en inglés.
- Tipo de membresía = nombre + club (hay tipos con el mismo nombre en ambos clubes).
- Para pagos históricos no exigir referencia, banco ni número de cheque (Fox no los tiene en todos).
- La fecha de corte es una sola. Se captura en Instrucciones y se usa como `billing_backfill_floor`.
- Todo lo cargado se marca en `metadata` con `imported: true`.
- El folio de Fox se conserva; las formas de pago extra del mismo recibo llevan sufijo -2, -3 (el folio es único por forma de pago).
- FECHA DE EMISION vacía = fecha del pago, o fecha de corte si no está pagado (sin fecha, el cargo no aparece en el estado de cuenta de la app).
- Casilleros: año = año de la fecha de corte; casillero marcado "ocupado"; historial inicial en `locker_assignment_histories`.
- Cajeros que ya no laboran: usuario sin rol, contraseña aleatoria y correo interno no real.

## Detalle por tabla

### `clubs.clubs`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `name` | Sí | Plantilla | Clubes · NOMBRE DEL CLUB |
| `is_active` | Sí (con valor por defecto) | Plantilla | Clubes · ACTIVO (vacío = SÍ) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `code` | No | Plantilla | Clubes · CODIGO DEL CLUB |
| `logo_path` | No | Plantilla | Clubes · ARCHIVO DEL LOGO (archivo de la carpeta) |
| `social_whatsapp` | No | Plantilla | Clubes · WHATSAPP |
| `social_instagram` | No | Plantilla | Clubes · INSTAGRAM |
| `social_facebook` | No | Plantilla | Clubes · FACEBOOK |
| `social_youtube` | No | Plantilla | Clubes · YOUTUBE |
| `mapa_path` | No | Plantilla | Clubes · ARCHIVO DEL MAPA (archivo de la carpeta) |
| `legal_name` | No | Plantilla | Clubes · RAZON SOCIAL |
| `rfc` | No | Plantilla | Clubes · RFC |
| `billing_url` | No | Plantilla | Clubes · URL DE FACTURACION |
| `applies_iva` | Sí (con valor por defecto) | Plantilla | Clubes · APLICA IVA |
| `email` | No | Plantilla | Clubes · CORREO |
| `phone` | No | Plantilla | Clubes · TELEFONO |
| `website` | No | Plantilla | Clubes · SITIO WEB |
| `social_twitter` | No | Plantilla | Clubes · X (TWITTER) |
| `social_threads` | No | Plantilla | Clubes · THREADS |

### `clubs.club_addresses`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `club_id` | Sí | Lo calcula la carga | Club de la fila |
| `street` | No | Plantilla | Clubes · CALLE |
| `exterior_number` | No | Plantilla | Clubes · NUMERO EXTERIOR |
| `interior_number` | No | Plantilla | Clubes · NUMERO INTERIOR |
| `neighborhood` | No | Plantilla | Clubes · COLONIA |
| `postal_code` | No | Plantilla | Clubes · CODIGO POSTAL |
| `country_id` | No | Plantilla | Clubes · PAIS → catálogo |
| `state_id` | No | Plantilla | Clubes · ESTADO → catálogo |
| `city_id` | No | Plantilla | Clubes · CIUDAD → catálogo |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |

### `clubs.rules`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `max_active_reservations` | Sí (con valor por defecto) | Plantilla | Clubes · MAXIMO DE RESERVACIONES ACTIVAS (vacío = 0) |
| `max_days_in_advance` | Sí (con valor por defecto) | Plantilla | Clubes · MAXIMO DE DIAS DE ANTICIPACION (vacío = 0) |
| `allow_same_day` | Sí (con valor por defecto) | Plantilla | Clubes · PERMITE RESERVAR EL MISMO DIA (vacío = NO) |
| `club_id` | Sí | Lo calcula la carga | Club de la fila |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |

### `billing.club_payment_methods`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `club_id` | Sí | Plantilla | Metodos por club · CLUB |
| `payment_method_id` | Sí | Plantilla | Metodos por club · METODO DE PAGO |
| `is_active` | Sí (con valor por defecto) | Plantilla | Metodos por club · ACTIVO |
| `display_order` | Sí (con valor por defecto) | Plantilla | Metodos por club · ORDEN DE APARICION |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `internal_key` | No | Plantilla | Metodos por club · CLAVE INTERNA |
| `conekta_public_key` | No | No aplica | Credencial: se configura en el sistema |
| `conekta_secret_key` | No | No aplica | Credencial: se configura en el sistema |

### `billing.concept_club_amounts`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `concept_id` | Sí | Plantilla | Importes por club · CONCEPTO DE COBRO |
| `club_id` | Sí | Plantilla | Importes por club · CLUB |
| `amount` | No | Plantilla | Importes por club · IMPORTE |
| `is_active` | Sí (con valor por defecto) | Plantilla | Importes por club · ACTIVO |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `applies_iva` | No | Plantilla | Importes por club · APLICA IVA |

### `public.users`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `name` | Sí | Plantilla | Personal · NOMBRE + APELLIDO PATERNO + APELLIDO MATERNO |
| `email` | Sí | Plantilla | Personal · CORREO (vacío solo en cajeros que ya no laboran → correo interno no real) |
| `email_verified_at` | No | No aplica | — |
| `password` | Sí | Lo calcula la carga | Contraseña temporal; en cajeros históricos, aleatoria que nadie conoce |
| `remember_token` | No | No aplica | — |
| `current_team_id` | No | No aplica | — |
| `profile_photo_path` | No | No aplica | — |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `two_factor_secret` | No | No aplica | — |
| `two_factor_recovery_codes` | No | No aplica | — |
| `two_factor_confirmed_at` | No | No aplica | — |
| `code` | No | Plantilla | Personal · SERIE DE CAJA |

### `public.user_clubs`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `user_id` | Sí | Lo calcula la carga | Persona de la fila |
| `club_id` | Sí | Plantilla | Personal · CLUBES (uno por club) |
| `is_active` | Sí (con valor por defecto) | Sistema / valor por defecto | Por defecto: activo |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |

### `public.model_has_roles`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `role_id` | Sí | Plantilla | Personal · ROL (vacío = cajero histórico, sin rol ni acceso) |
| `model_type` | Sí | Lo calcula la carga | Tipo "usuario" |
| `model_id` | Sí | Lo calcula la carga | Persona de la fila |

### `members.members`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `first_name` | Sí | Plantilla | Usuarios · NOMBRE |
| `last_name` | Sí | Plantilla | Usuarios · APELLIDO PATERNO |
| `second_last_name` | No | Plantilla | Usuarios · APELLIDO MATERNO |
| `birthdate` | No | Plantilla | Usuarios · FECHA DE NACIMIENTO |
| `phone` | No | Plantilla | Usuarios · TELEFONO |
| `email` | No | Plantilla | Usuarios · CORREO |
| `occupation` | No | Plantilla | Usuarios · OCUPACION |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `school_name` | No | Plantilla | Usuarios · ESCUELA |
| `birth_place` | No | Lo calcula la carga | Nombre del país de nacimiento, igual que la pantalla de alta (AddFamilyMember.vue) |
| `state` | No | No aplica | Columna en desuso (texto); la reemplazan los catálogos de nacimiento |
| `city` | No | No aplica | Columna en desuso (texto); la reemplazan los catálogos de nacimiento |
| `nationality_id` | No | Plantilla | Usuarios · NACIONALIDAD → país (Mexicana/Española/Estadounidense) |
| `marital_status_id` | No | Plantilla | Usuarios · ESTADO CIVIL → catálogo |
| `birth_country_id` | No | Plantilla | Usuarios · PAIS DE NACIMIENTO |
| `birth_state_id` | No | Plantilla | Usuarios · ESTADO DE NACIMIENTO |
| `birth_city_id` | No | Plantilla | Usuarios · CIUDAD DE NACIMIENTO |
| `user_id` | No | Lo calcula la carga | Se liga al crear el acceso a la app (después de la carga) |
| `conekta_customer_id` | No | No aplica | Lo genera Conekta |
| `photo_path` | No | No aplica | La foto la sube el usuario desde la app |
| `migration_origin_id` | No | Plantilla | Usuarios · ID DE USUARIO |
| `gender` | No | Plantilla | Usuarios · SEXO |

### `members.addresses`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `member_id` | Sí | Lo calcula la carga | Usuario de la fila |
| `street` | No | Plantilla | Usuarios · CALLE Y NUMERO |
| `neighborhood` | No | Plantilla | Usuarios · COLONIA |
| `postal_code` | No | Plantilla | Usuarios · CODIGO POSTAL |
| `years_in_city` | No | Plantilla | Usuarios · AÑOS EN LA CIUDAD |
| `is_primary` | Sí (con valor por defecto) | Lo calcula la carga | Siempre SÍ (el sistema maneja un solo domicilio) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `country_id` | No | Plantilla | Usuarios · PAIS |
| `state_id` | No | Plantilla | Usuarios · ESTADO |
| `city_id` | No | Plantilla | Usuarios · CIUDAD |

### `members.employment_info`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `member_id` | Sí | Lo calcula la carga | Usuario de la fila |
| `company_name` | No | Plantilla | Usuarios · EMPRESA |
| `company_address` | No | Plantilla | Usuarios · DOMICILIO DE LA EMPRESA |
| `company_phone` | No | Plantilla | Usuarios · TELEFONO DE LA EMPRESA |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `members.clinical_histories`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `member_id` | Sí | Plantilla | Informacion medica · ID DE USUARIO y Contactos de emergencia · ID DE USUARIO (se juntan en un solo registro) |
| `blood_type` | No | Plantilla | Informacion medica · TIPO DE SANGRE |
| `blood_rh` | No | Plantilla | Informacion medica · FACTOR RH (POSITIVO→positive) |
| `has_diabetes` | Sí (con valor por defecto) | Plantilla | Informacion medica · DIABETES |
| `diabetes_type` | No | Plantilla | Informacion medica · TIPO DE DIABETES |
| `has_heart_condition` | Sí (con valor por defecto) | Plantilla | Informacion medica · CARDIOPATIA |
| `has_epilepsy` | Sí (con valor por defecto) | Plantilla | Informacion medica · EPILEPSIA |
| `has_asthma` | Sí (con valor por defecto) | Plantilla | Informacion medica · ASMA |
| `has_allergy` | Sí (con valor por defecto) | Plantilla | Informacion medica · ALERGIA |
| `takes_medication` | Sí (con valor por defecto) | Plantilla | Informacion medica · TOMA MEDICAMENTOS |
| `medication_details` | No | Plantilla | Informacion medica · MEDICAMENTOS |
| `has_allergens` | Sí (con valor por defecto) | Plantilla | Informacion medica · ALERGENOS |
| `allergen_details` | No | Plantilla | Informacion medica · DETALLE DE ALERGENOS |
| `normal_blood_pressure` | No | Plantilla | Informacion medica · PRESION ARTERIAL NORMAL |
| `has_hypertension` | Sí (con valor por defecto) | Plantilla | Informacion medica · HIPERTENSION |
| `special_conditions` | No | Plantilla | Informacion medica · CONDICIONES ESPECIALES |
| `emergency_contact_name` | No | Plantilla | Contactos de emergencia · NOMBRE DEL CONTACTO |
| `emergency_contact_phone` | No | Plantilla | Contactos de emergencia · TELEFONO DEL CONTACTO |
| `emergency_contact_mobile` | No | Plantilla | Contactos de emergencia · CELULAR DEL CONTACTO |
| `emergency_notify_name` | No | Plantilla | Contactos de emergencia · EN CASO NECESARIO INFORMAR A |
| `treating_physician` | No | Plantilla | Informacion medica · MEDICO TRATANTE |
| `treating_physician_phone` | No | Plantilla | Informacion medica · TELEFONO DEL MEDICO |
| `social_security_number` | No | Plantilla | Informacion medica · NUMERO DE SEGURIDAD SOCIAL |
| `medical_insurance` | No | Plantilla | Informacion medica · SEGURO DE GASTOS MEDICOS |
| `insurance_company` | No | Plantilla | Informacion medica · COMPAÑIA DEL SEGURO |
| `insurance_policy_number` | No | Plantilla | Informacion medica · NUMERO DE POLIZA |
| `insurance_mobile` | No | Plantilla | Informacion medica · CELULAR DEL SEGURO |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `members.documents`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `member_id` | Sí | Plantilla | Documentos · ID DE USUARIO |
| `document_type_id` | Sí | Plantilla | Documentos · TIPO DE DOCUMENTO |
| `file_path` | Sí | Lo calcula la carga | Documentos · RUTA DEL ARCHIVO → se sube el archivo y se guarda la ruta del almacenamiento |
| `is_verified` | Sí (con valor por defecto) | Plantilla | Documentos · VERIFICADO |
| `verified_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `verified_at` | No | Plantilla | Documentos · FECHA DE VERIFICACION |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `club_id` | No | Plantilla | Documentos · CLUB |

### `memberships.account_groups`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `status` | Sí (con valor por defecto) | Sistema / valor por defecto | Por defecto: active |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `memberships.accounts`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_number` | Sí | Lo calcula la carga | Lo genera el sistema (CLUB-fechahora), igual que el alta |
| `account_type` | Sí | Plantilla | Membresias · INDIVIDUAL O FAMILIAR (vacío = según el tipo) |
| `status` | Sí (con valor por defecto) | Plantilla | Membresias · ESTATUS |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `account_group_id` | No | Lo calcula la carga | Nuevo grupo por membresía; el mismo grupo si tiene CUENTA EN EL OTRO PARQUE |
| `club_id` | No | Plantilla | Membresias · CLUB |
| `cancelled_at` | No | Plantilla | Membresias · FECHA DE CANCELACION |
| `cancelled_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `cancellation_letter_path` | No | Plantilla | Membresias · ARCHIVO DE LA CARTA DE CANCELACION |
| `cancellation_type` | No | Plantilla | Membresias · TIPO DE CANCELACION |
| `origin_account_id` | No | Plantilla | Membresias · CUENTA DE ORIGEN |
| `separation_reason` | No | Plantilla | Membresias · MOTIVO DE SEPARACION |
| `internal_account_number` | No | Plantilla | Membresias · NUMERO DE CUENTA |
| `billing_backfill_floor` | No | Lo calcula la carga | Fecha de corte de la migración (una sola, en Instrucciones) |
| `cancellation_reason_id` | No | Plantilla | Membresias · MOTIVO DE CANCELACION |

### `memberships.memberships`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | Sí | Lo calcula la carga | Membresía de la fila |
| `club_id` | Sí | Plantilla | Membresias · CLUB |
| `membership_type_id` | Sí | Plantilla | Membresias · TIPO DE MEMBRESIA (+ CLUB) |
| `origin_membership_type_id` | No | Plantilla | Membresias · TIPO DE MEMBRESIA ANTERIOR |
| `is_primary` | Sí (con valor por defecto) | Lo calcula la carga | Siempre SÍ, igual que el alta |
| `monthly_fee` | Sí | Lo calcula la carga | Cuota de la regla de precios; se compara con Membresias · CUOTA MENSUAL |
| `start_date` | Sí | Plantilla | Membresias · FECHA DE INICIO |
| `end_date` | No | Plantilla | Membresias · FECHA DE TERMINO (vacío = vigencia del tipo) |
| `status` | Sí (con valor por defecto) | Plantilla | Membresias · ESTATUS |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `is_billable` | Sí (con valor por defecto) | Plantilla | Membresias · GENERA COBRO (vacío = lo decide la carga como el sistema) |
| `monthly_fee_total` | No | Lo calcula la carga | Igual a la cuota |
| `monthly_fee_share` | No | Lo calcula la carga | Igual a la cuota |
| `billing_split_mode` | No | Lo calcula la carga | single, igual que el alta |
| `pricing_rule_id` | No | Lo calcula la carga | Regla de precios: tipo + tipo anterior + edad del titular (Usuarios+Integrantes) + si tiene otro parque |
| `interclub_package_rule_id` | No | Lo calcula la carga | Paquete entre parques: tipo, estatus y antigüedad de CUENTA EN EL OTRO PARQUE |

### `memberships.membership_history`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_id` | Sí | Lo calcula la carga | Membresía de la fila |
| `old_membership_type_id` | No | Plantilla | Membresias · TIPO DE MEMBRESIA ANTERIOR |
| `new_membership_type_id` | Sí | Plantilla | Membresias · TIPO DE MEMBRESIA |
| `changed_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `effective_date` | Sí | Lo calcula la carga | FECHA DE INICIO (alta) y FECHA DE CANCELACION (baja) |
| `reason` | No | Lo calcula la carga | "Alta de membresía" y, si está cancelada, "Baja voluntaria de cuenta" |
| `previous_monthly_fee` | No | No aplica | — |
| `new_monthly_fee` | No | Lo calcula la carga | Cuota calculada |
| `metadata` | No | Lo calcula la carga | Marca de migración |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `memberships.account_members`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | Sí | Plantilla | Integrantes · NUMERO DE CUENTA |
| `member_id` | Sí | Plantilla | Integrantes · ID DE USUARIO |
| `relationship_id` | No | Plantilla | Integrantes · PARENTESCO (titular = Titular) |
| `is_primary_holder` | Sí (con valor por defecto) | Plantilla | Integrantes · ES TITULAR |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `access_code` | No | Plantilla | Integrantes · NUMERO DE TARJETA DE ACCESO (vacío = se genera) |
| `access_valid_until` | No | Lo calcula la carga | Se calcula al dar de alta el acceso |
| `access_status` | Sí (con valor por defecto) | Sistema / valor por defecto | Por defecto: active; luego lo ajusta el proceso de morosidad |

### `memberships.account_fiscal_data`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `fiscal_name` | Sí | Plantilla | Membresias · NOMBRE O RAZON SOCIAL |
| `rfc` | Sí | Plantilla | Membresias · RFC |
| `cfdi_use` | Sí | Plantilla | Membresias · USO DE CFDI |
| `fiscal_regime` | Sí | Plantilla | Membresias · REGIMEN FISCAL |
| `postal_code` | Sí | Plantilla | Membresias · CODIGO POSTAL FISCAL |
| `membership_account_id` | Sí | Lo calcula la carga | Membresía de la fila |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `memberships.absence_permits`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `account_group_id` | No | Lo calcula la carga | Grupo de la membresía |
| `membership_account_id` | No | Plantilla | Permisos de ausencia · NUMERO DE CUENTA |
| `primary_member_id` | Sí | Lo calcula la carga | Titular de la membresía (Integrantes) |
| `start_date` | Sí | Plantilla | Permisos de ausencia · INICIO DEL PERMISO |
| `end_date` | Sí | Plantilla | Permisos de ausencia · FIN DEL PERMISO |
| `charge_percentage` | Sí (con valor por defecto) | Plantilla | Permisos de ausencia · PORCENTAJE A COBRAR |
| `status` | Sí (con valor por defecto) | Lo calcula la carga | active si está vigente, approved si es futuro |
| `blocks_facility_access` | Sí (con valor por defecto) | Plantilla | Permisos de ausencia · BLOQUEA ACCESO (vacío = SÍ) |
| `blocks_reservations` | Sí (con valor por defecto) | Plantilla | Permisos de ausencia · BLOQUEA RESERVACIONES (vacío = SÍ) |
| `document_path` | No | Plantilla | Permisos de ausencia · ARCHIVO DEL PERMISO |
| `notes` | No | Plantilla | Permisos de ausencia · NOTAS DEL PERMISO |
| `approved_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `approved_at` | No | Lo calcula la carga | Fecha de la carga |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `charge_concept_id` | No | Lo calcula la carga | 25 → CUOTA_PERMISO, 75 → CUOTA_75_PERMISO |

### `billing.charges`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | No | Plantilla | Cargos · NUMERO DE CUENTA |
| `membership_id` | No | Lo calcula la carga | Membresía de la cuenta (cada cuenta es de un solo parque) |
| `member_id` | No | Lo calcula la carga | Titular de la cuenta (Integrantes); en casilleros, Casilleros · ID DE USUARIO |
| `concept_id` | Sí | Plantilla | Cargos · CONCEPTO DE COBRO |
| `description` | No | Plantilla | Cargos · DESCRIPCION |
| `amount` | Sí | Plantilla | Cargos · IMPORTE |
| `balance` | Sí | Lo calcula la carga | IMPORTE − IMPORTE PAGADO − DESCUENTO (sin contar pagos cancelados) |
| `issue_date` | No | Plantilla | Cargos · FECHA DE EMISION (vacío = fecha del pago, o de corte si no está pagado) |
| `due_date` | No | Plantilla | Cargos · FECHA DE VENCIMIENTO (vacío en mensualidades = día 10) |
| `period_year` | No | Plantilla | Cargos · AÑO DEL PERIODO |
| `period_month` | No | Plantilla | Cargos · MES DEL PERIODO |
| `allows_partial_payments` | Sí (con valor por defecto) | Plantilla | Cargos · PAGO EN PARCIALIDADES (vacío = NO) |
| `status` | Sí (con valor por defecto) | Lo calcula la carga | pending / partial / paid según lo pagado; cancelled si Cargos · CANCELADO = SÍ |
| `metadata` | No | Lo calcula la carga | Cargos · REFERENCIA DEL CARGO, Cargos · NOTAS, marca de migración; locker_id y club_id si Casilleros · REFERENCIA DEL CARGO apunta a este cargo |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `cancelled_at` | No | Plantilla | Cargos · FECHA DE CANCELACION |
| `cancelled_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `cancellation_reason` | No | Plantilla | Cargos · MOTIVO DE CANCELACION |

### `billing.payments`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | No | Plantilla | Pagos · NUMERO DE CUENTA |
| `club_id` | Sí | Plantilla | Pagos · CLUB DONDE SE COBRO |
| `payment_method_id` | Sí | Plantilla | Pagos · METODO DE PAGO |
| `amount` | Sí | Plantilla | Pagos · IMPORTE |
| `paid_at` | Sí | Plantilla | Pagos · FECHA DEL PAGO + HORA DEL PAGO |
| `reference` | No | Plantilla | Pagos · REFERENCIA |
| `bank_name` | No | Plantilla | Pagos · BANCO |
| `check_number` | No | Plantilla | Pagos · NUMERO DE CHEQUE |
| `notes` | No | Plantilla | Pagos · NOTAS |
| `received_by` | No | Plantilla | Pagos · SERIE DE CAJA → Personal · SERIE DE CAJA |
| `status` | Sí (con valor por defecto) | Plantilla | Pagos · CANCELADO (registered / cancelled) |
| `metadata` | No | Lo calcula la carga | settlement_channel, affects_cash_cut, split_payment y represents_club_id (← Pagos · PARQUE DE LA FORMA DE PAGO) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `folio` | No | Plantilla | Pagos · FOLIO DEL RECIBO (con sufijo si el recibo tiene varias formas de pago) |
| `ticket_file_identifier` | No | Sistema / valor por defecto | Lo genera el sistema al imprimir el ticket |
| `subtotal` | No | Lo calcula la carga | Según el IVA del concepto en el club (igual que la caja) |
| `iva` | No | Lo calcula la carga | Según el IVA del concepto en el club (igual que la caja) |
| `payment_group_id` | No | Lo calcula la carga | Mismo identificador para las filas con el mismo FOLIO DEL RECIBO |
| `cancelled_at` | No | Plantilla | Pagos · FECHA DE CANCELACION |
| `cancelled_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `cancellation_reason` | No | Plantilla | Pagos · MOTIVO DE CANCELACION |

### `billing.payment_applications`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `payment_id` | Sí | Plantilla | Cargos · FOLIO DEL RECIBO → formas de pago de ese folio en Pagos (reparto en cascada, igual que la caja) |
| `charge_id` | Sí | Lo calcula la carga | Cargo de la fila de Cargos |
| `applied_amount` | Sí | Plantilla | Cargos · IMPORTE PAGADO (vacío con folio = pagado completo) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `subtotal` | No | Lo calcula la carga | Se calcula |
| `iva` | No | Lo calcula la carga | Se calcula |
| `discount` | No | Plantilla | Cargos · DESCUENTO |

### `billing.credit_balances`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | Sí | No aplica | No se migra: solo lo usa el pago anual (se genera y se aplica en el mismo momento); ninguna pantalla lo muestra. Saldos a favor de Fox → Notas de cobranza |
| `amount` | Sí (con valor por defecto) | No aplica | No se migra (ver arriba) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `billing.collection_notes`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `membership_account_id` | Sí | Plantilla | Notas de cobranza · NUMERO DE CUENTA |
| `club_id` | No | Lo calcula la carga | Club de la cuenta |
| `created_by` | No | No aplica | Fox no trae autor; queda vacío |
| `body` | Sí | Plantilla | Notas de cobranza · NOTA |
| `created_at` | No | Plantilla | Notas de cobranza · FECHA DE LA NOTA (vacío = fecha de la carga) |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `members.lockers`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `club_id` | Sí | No aplica | Inventario ya cargado en el sistema (5,738 casilleros); se busca con Casilleros · CLUB |
| `number` | Sí | No aplica | Ya cargado; se busca con Casilleros · NUMERO DE CASILLERO |
| `category` | Sí | No aplica | Ya cargado; se busca con Casilleros · CATEGORIA |
| `status` | Sí (con valor por defecto) | Lo calcula la carga | "ocupado" al asignar, igual que la pantalla |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |

### `members.locker_assignments`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `locker_id` | Sí | Plantilla | Casilleros · CLUB + CATEGORIA + NUMERO DE CASILLERO |
| `member_id` | Sí | Plantilla | Casilleros · ID DE USUARIO |
| `start_date` | Sí | Plantilla | Casilleros · FECHA DE INICIO (vacío = 1 de enero del año de corte) |
| `end_date` | Sí | Plantilla | Casilleros · FECHA DE FIN (vacío = 31 de diciembre del año de corte) |
| `amount_paid` | Sí | Lo calcula la carga | 0: el sistema ya no lo usa; el pagado sale del cargo |
| `year` | Sí | Lo calcula la carga | Año de la fecha de corte (el sistema solo muestra el año actual) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `cancellation_reason` | No | No aplica | Solo bajas |
| `club_id` | Sí | Plantilla | Casilleros · CLUB |
| `file_path` | No | Plantilla | Casilleros · ARCHIVO |

### `members.locker_assignment_histories`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `locker_assignment_id` | Sí | Lo calcula la carga | Asignación creada |
| `member_id` | Sí | Lo calcula la carga | Usuario de la asignación |
| `old_locker_id` | No | Lo calcula la carga | Vacío (asignación inicial) |
| `new_locker_id` | Sí | Lo calcula la carga | Casillero asignado |
| `changed_at` | Sí | Lo calcula la carga | Fecha de inicio de la asignación |
| `changed_by` | No | Lo calcula la carga | Usuario que ejecuta la carga |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `file_path` | No | Lo calcula la carga | Archivo de la asignación |

### `classes.coaches`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `first_name` | Sí | Plantilla | Entrenadores · NOMBRE |
| `last_name` | Sí | Plantilla | Entrenadores · APELLIDO PATERNO |
| `second_last_name` | No | Plantilla | Entrenadores · APELLIDO MATERNO |
| `phone` | No | Plantilla | Entrenadores · TELEFONO |
| `email` | No | Plantilla | Entrenadores · CORREO |
| `photo` | No | No aplica | La foto se sube en el sistema |
| `club_id` | Sí | Plantilla | Entrenadores · CLUB |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `amenity_id` | No | Plantilla | Entrenadores · AMENIDAD (+ CLUB) |

### `classes.coach_availabilities`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `coach_id` | Sí | Lo calcula la carga | Entrenador de la fila (filas con mismo CLUB + nombre completo = mismo entrenador) |
| `day_of_week` | Sí | Plantilla | Entrenadores · DIA (DOMINGO=0 … SABADO=6) |
| `start_time` | Sí | Plantilla | Entrenadores · HORA DE INICIO |
| `end_time` | Sí | Plantilla | Entrenadores · HORA DE FIN |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |

### `reservations.reservations`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `start_datetime` | Sí | Plantilla | Reservaciones · FECHA + HORA DE INICIO |
| `end_datetime` | Sí | Plantilla | Reservaciones · FECHA + HORA DE FIN |
| `cancelled_at` | No | Plantilla | Reservaciones · FECHA DE CANCELACION |
| `reservation_date` | No | Lo calcula la carga | FECHA, solo en amenidades Por dia (igual que la app) |
| `club_id` | Sí | Plantilla | Reservaciones · CLUB |
| `amenity_id` | Sí | Plantilla | Reservaciones · AMENIDAD (+ CLUB) |
| `member_id` | Sí | Plantilla | Reservaciones · ID DE USUARIO |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `amenity_resource_id` | No | Plantilla | Reservaciones · RECURSO |
| `reservation_status_id` | No | Plantilla | Reservaciones · ESTATUS |
| `requires_tent` | No | Plantilla | Reservaciones · REQUIERE CARPA |
| `tables_count` | No | Plantilla | Reservaciones · MESAS |
| `chairs_count` | No | Plantilla | Reservaciones · SILLAS |
| `notes` | No | Plantilla | Reservaciones · NOTAS |
| `linked_reservation_id` | No | Lo calcula la carga | Si hay Reservaciones · ASADOR: se crea la reservación del asador y se ligan las dos |
| `is_class` | Sí (con valor por defecto) | Lo calcula la carga | SÍ si Reservaciones · ENTRENADOR tiene valor |
| `coach_id` | No | Plantilla | Reservaciones · ENTRENADOR |

### `guest_lists.guest_lists`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `status` | Sí | Plantilla | Listas de invitados · ESTATUS |
| `total_guests` | Sí | Lo calcula la carga | Número de invitados de la lista |
| `total_adults` | Sí | Lo calcula la carga | Invitados de 7 años o más |
| `total_children` | Sí | Lo calcula la carga | Invitados menores de 7 |
| `billable_subtotal` | No | Lo calcula la carga | Suma de precios de invitados que se cobran al socio |
| `discount` | No | Plantilla | Listas de invitados · DESCUENTO |
| `total` | No | Lo calcula la carga | billable_subtotal − DESCUENTO |
| `approved_at` | No | Plantilla | Listas de invitados · FECHA DE APROBACION |
| `comments` | No | Plantilla | Listas de invitados · COMENTARIOS |
| `reservation_id` | No | Plantilla | Listas de invitados · REFERENCIA DE LA RESERVACION |
| `club_id` | Sí | Plantilla | Listas de invitados · CLUB |
| `user_id` | Sí | Lo calcula la carga | Acceso a la app del socio si existe; si no, usuario que ejecuta la carga |
| `approved_by` | No | Lo calcula la carga | Usuario que ejecuta la carga (si está aceptada) |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `title` | No | Plantilla | Listas de invitados · TITULO |
| `description` | No | Plantilla | Listas de invitados · DESCRIPCION |
| `date` | No | Plantilla | Listas de invitados · FECHA |
| `time` | No | Plantilla | Listas de invitados · HORA |
| `total_billable_guests` | No | Lo calcula la carga | Invitados con SE COBRA AL SOCIO = SÍ |
| `non_billable_subtotal` | No | Lo calcula la carga | Suma de precios de los que no |
| `member_id` | No | Plantilla | Listas de invitados · ID DE USUARIO |

### `guest_lists.guest_list_items`

| Columna | Obligatoria en BD | Origen | Detalle |
| --- | --- | --- | --- |
| `id` | Sí (con valor por defecto) | Sistema / valor por defecto | Identificador interno |
| `name` | Sí | Plantilla | Listas de invitados · NOMBRE DEL INVITADO |
| `last_name` | No | Plantilla | Listas de invitados · APELLIDO DEL INVITADO |
| `email` | Sí | Plantilla | Listas de invitados · CORREO DEL INVITADO (si falta, se resuelve en la carga) |
| `phone` | No | Plantilla | Listas de invitados · TELEFONO DEL INVITADO |
| `age` | Sí | Plantilla | Listas de invitados · EDAD |
| `guest_list_id` | Sí | Lo calcula la carga | Lista de Listas de invitados · REFERENCIA DE LA LISTA |
| `created_at` | No | Sistema / valor por defecto | Fecha de creación |
| `updated_at` | No | Sistema / valor por defecto | Fecha de actualización |
| `deleted_at` | No | No aplica | Solo para registros borrados |
| `is_billable_to_member` | No | Plantilla | Listas de invitados · SE COBRA AL SOCIO |
| `is_paid` | Sí (con valor por defecto) | Lo calcula la carga | SÍ si el cargo GUEST_LIST de la lista está pagado |
| `price` | No | Lo calcula la carga | Tarifa normal (7+ años) o especial del club (guest_lists.variables) |
| `is_comped` | Sí (con valor por defecto) | Plantilla | Listas de invitados · CORTESIA |
