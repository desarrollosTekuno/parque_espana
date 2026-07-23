# API Reference — App Móvil Parque España

Referencia de la API consumida por la app móvil. Generada a partir del código en `routes/api.php` y `app/Http/Controllers/Api/V1/*` (commit actual de la rama `antonioMembresias`). Si el backend cambia, este documento puede quedar desactualizado — ante duda, el código es la fuente de verdad.

## 1. Convenciones generales

- **Base URL**: todas las rutas van bajo el prefijo `/api/v1` (ej. `https://tu-dominio.com/api/v1/login`).
- **Autenticación**: Laravel Sanctum, token tipo *personal access token*. Se obtiene en `POST /login` y se envía en cada request protegido como:
  ```
  Authorization: Bearer {token}
  Accept: application/json
  ```
  Los tokens **no expiran** (`config/sanctum.php` → `expiration: null`) — solo se invalidan con `POST /logout` o al resetear contraseña (ver más abajo). No hay CSRF/cookies involucradas; es 100% bearer token, apto para stateless mobile.
- **Scoping por club**: casi todas las rutas relevantes están anidadas bajo `/clubs/{club}/...` o reciben `club_id` en el body/query. **Ninguno de los 24 controladores de la API usa `session('club_id')`** (confirmado por grep completo) — el patrón `session()` solo existe en el panel web/admin (Inertia), no en esta API. Es seguro asumir que el club siempre se resuelve por parámetro de ruta o campo explícito, nunca por sesión.
- **Formato de error estándar del framework** (`bootstrap/app.php`, aplica a cualquier ruta bajo `/api/*` que no sea manejada explícitamente por el controlador):
  - `401` sin token o token inválido: `{"success": false, "message": "Unauthenticated"}`
  - `403` sin permiso (Spatie): `{"success": false, "message": "Forbidden"}`
  - `404` ruta inexistente: `{"success": false, "message": "Endpoint not found"}`
- **⚠️ Inconsistencia de formato entre controladores** — no hay un envoltorio único para todas las respuestas:
  - La mayoría envuelve así: `{"success": bool, "message": string, "data"|"...": ...}`.
  - `SurveyController` (index/show) responde solo `{"data": ...}` o `{"message": ...}` sin `success`.
  - Algunos usan `Validator::make()` manual (errores 422 con forma `{"success": false, "message": "...", "errors": {...}}`), otros usan `$request->validate()` o un `FormRequest` (errores 422 con el formato **default de Laravel**: `{"message": "...", "errors": {...}}`, sin `success`).
  - Conclusión práctica: el cliente móvil debe verificar la presencia de `success`/`errors` de forma defensiva por endpoint, no asumir un único parser global de errores.
- **Paginación**: la mayoría de los listados usan `simplePaginate` (sin `total`/`last_page`), expuesto como `meta: {current_page, per_page, has_more_pages}` (a veces con nombres ligeramente distintos por endpoint — revisar cada uno). Diseñar la UI para scroll infinito, no para "página X de Y".
- **Moneda**: montos en pesos mexicanos, campo `currency: "MXN"` cuando aplica. Los precios de Conekta se convierten a centavos internamente en el backend; la API siempre expone montos en unidades (no centavos) al cliente.

---

## 2. Autenticación

### POST /login
- Auth: no
- Body: `email` (string, required), `password` (string, required)
- Éxito (200):
  ```json
  {
    "success": true,
    "message": "Login successful",
    "token": "string (Sanctum plain text token)",
    "member": {
      "id": 1, "full_name": "string", "email": "string", "phone": "string",
      "clubs": [
        {
          "club_id": 1, "club_name": "string", "club_code": "PE1|PE2",
          "membership_account_id": 1, "membership_number": "string",
          "membership_type": "string|null", "status": "active|suspended",
          "is_primary_holder": true,
          "permissions": ["mobile.reservations.index", "..."]
        }
      ]
    }
  }
  ```
  `member` es `null` si el usuario no tiene un `Member` vinculado.
- Errores: `422` validación (`{"success": false, "message": "Validation failed", "errors": {...}}`); `401` `{"success": false, "message": "Unauthorized"}` si las credenciales no coinciden.
- Notas:
  - Los permisos vienen agrupados por club (`PE1`/`PE2`) según el contexto Spatie `mobile_club_1`/`mobile_club_2` — úsalos para mostrar/ocultar funciones en la app por club.
  - `clubs` puede tener más de un elemento si el socio pertenece a ambos parques.

### POST /logout
- Auth: sí
- Body opcional: `fcm_token` (string) — si se envía, desactiva ese token de dispositivo (deja de recibir push) antes de revocar el token de sesión.
- Éxito (200): `{"success": true, "message": "Logout successful"}`
- Error: `500` `{"success": false, "message": "Logout failed", "error": "..."}`
- Nota: revoca únicamente el token actual (`currentAccessToken()->delete()`), no todos los dispositivos del usuario.

### POST /forgot-password
- Auth: no
- Body: `email` (string, required, email)
- Éxito (200) — **misma respuesta exista o no la cuenta** (anti-enumeración): `{"success": true, "message": "Si el correo está registrado, recibirás un código en breve."}`
- Errores: `429` si ya se generó un OTP para ese correo hace menos de 2 minutos: `{"success": false, "message": "Ya enviamos un código recientemente. Espera un momento antes de solicitar otro."}`; `422` validación default de Laravel.
- Notas: OTP de 6 dígitos, se guarda hasheado, expira en `config('auth.otp_expiry_minutes', 1440)` minutos (24h por defecto).

### POST /reset-password
- Auth: no
- Body: `email` (required, email), `otp` (required, string, exactamente 6 caracteres), `password` (required, min 8). `password_confirmation` no se valida en backend (puedes enviarlo pero no se compara).
- Éxito (200): `{"success": true, "message": "Contraseña actualizada correctamente. Inicia sesión con tu nueva contraseña."}`
- Errores:
  - `422` `{"success": false, "message": "El código es inválido o ha expirado. Solicita uno nuevo."}`
  - `422` `{"success": false, "message": "El código ingresado es incorrecto."}`
  - `404` `{"success": false, "message": "No se encontró una cuenta con ese correo."}`
- **Importante**: al cambiar la contraseña se revocan **todos** los tokens Sanctum del usuario — la app debe forzar login de nuevo después de un reset exitoso, el token actual queda inválido.

---

## 3. Perfil del socio

### GET /my-profile
- Auth: sí
- Query: `club_id` (integer, opcional, min 1) — si se envía y el socio tiene membresía primaria activa/suspendida en ese club, agrega `club_membership` a la respuesta.
- Éxito (200):
  ```json
  {
    "success": true,
    "data": {
      "id": 1, "full_name": "string", "first_name": "string", "last_name": "string",
      "second_last_name": "string", "email": "string", "phone": "string",
      "birthdate": "date", "age": 0, "photo_url": "string|null (URL firmada, expira en 30 min)",
      "address": { "street": "...", "neighborhood": "...", "postal_code": "...", "city": {}, "state": {}, "country": {} } ,
      "club_membership": {
        "club_id": 1, "club_name": "string|null", "club_code": "string|null",
        "membership_account_id": 1, "membership_number": "string",
        "account_type": "string", "account_status": "string",
        "membership_type": "string|null", "membership_status": "string",
        "is_primary_holder": true, "start_date": "date", "end_date": "date"
      }
    }
  }
  ```
  `club_membership` solo aparece si se envió `club_id`; puede ser `null` si no hay membresía primaria activa/suspendida en ese club (no confundir "ausente" con "null").
- Error: `404` `{"success": false, "message": "No se encontró un perfil de socio asociado a este usuario."}` si el usuario autenticado no tiene `Member` vinculado.

### POST /device-token
- Auth: sí
- Body: `token` (string, required, token FCM), `platform` (required, uno de `android`/`ios`/`web`), `device_name` (string, opcional, max 100).
- Éxito (200): `{"success": true}`
- Notas:
  - Upsert por `token` solamente: si el mismo token físico ya estaba asignado a **otro** usuario, se **reasigna** al usuario actual. Llamar esto en cada login/foreground para mantener la propiedad correcta.
  - Suscribe el token a los topics de Firebase de todos los clubes del usuario.

### DELETE /device-token
- Auth: sí
- Body: `token` (string, required)
- Éxito (200): `{"success": true}` (incluso si no había ningún registro que coincidiera)
- Nota: desactiva (`is_active = false`) el token, scoped por `token` + `user_id` — no puedes desactivar el token de otro usuario. Llamar en logout.

### GET /clubs/{club}/family-members
- Auth: sí
- Ruta: `{club}` — binding directo por id, 404 automático si no existe.
- Éxito (200, **sin envoltorio `success`**):
  ```json
  {
    "membership_number": "string", "membership_type": "string",
    "active_members": 0, "max_members": null,
    "members": [
      {
        "id": 1, "full_name": "string", "photo_url": "string|null (URL pública, no firmada)",
        "birthdate": "date|null", "age": 0, "relationship": "string (ej. 'Cónyuge', default 'Titular')",
        "is_primary_holder": true, "member_number": null
      }
    ]
  }
  ```
- Errores:
  - `403` `{"message": "No tienes acceso a esta sección. Solo los socios titulares pueden ver los integrantes."}` — solo el titular puede llamar este endpoint; dependientes reciben 403.
  - `403` `{"message": "Esta sección solo está disponible para membresías familiares."}` — el tipo de membresía no permite múltiples integrantes.
- Notas:
  - `max_members` y `member_number` están **hardcodeados a `null`** (no implementados aún).
  - `photo_url` usa una URL pública (`Storage::url()`), inconsistente con el resto de la API que usa URLs firmadas temporales — verificar que el disco sea accesible.

### GET /my-documents
- Auth: sí
- Éxito (200):
  ```json
  {
    "success": true,
    "data": [
      {
        "member_id": 1, "full_name": "string", "is_primary_holder": true, "is_self": true,
        "documents": [
          {
            "id": 1, "document_type_id": 1, "document_type": "string",
            "is_verified": true, "verified_at": "datetime|null",
            "uploaded_at": "datetime", "url": "string|null (firmada, 30 min)", "url_expires_at": "ISO8601|null"
          }
        ]
      }
    ]
  }
  ```
- Error: `404` si el usuario no tiene `Member` vinculado.
- **Importante**: devuelve los documentos de **toda la cuenta** (titular + todos los integrantes), no solo los del usuario que llama — usa `is_self` para identificar cuáles son propios. Todas las URLs firmadas de una misma respuesta expiran en el mismo instante (~30 min después del request). `url` es `null` si no se pudo generar (manejar UI sin botón de descarga en ese caso).

### POST /clinical-history-list
- Auth: sí
- Body: `member_id` — **sin ninguna validación** (no hay `$request->validate()`); en la práctica es requerido, pero enviar algo inválido no da 422 limpio, solo un 404.
- Éxito (200): objeto completo del modelo `ClinicalHistory` (tipo sangre, condiciones médicas, contacto de emergencia, seguro médico, etc. — ver todos los campos en el código si necesitas el detalle exacto).
- Error: `404` `{"success": false, "message": "No existe información clínica para este miembro.", "data": null}`
- **⚠️ Sin verificación de propiedad**: cualquier usuario autenticado puede pasar cualquier `member_id` y obtener la historia clínica completa de ese miembro (tipo de sangre, condiciones, contacto de emergencia, NSS, seguro) sin que el backend valide que pertenece a su cuenta/familia. La app móvil debe autolimitarse a solo pedir `member_id` de la propia familia (obtenidos de `/family-members` o `/my-profile`), pero el backend actualmente no lo exige.

### POST /clinical-history
- Auth: sí (via `ClinicalHistoryRequest`, `authorize()` siempre `true`)
- Body: `member_id` (sin regla de validación, igual que arriba) + todos los campos de la historia clínica, todos opcionales salvo los que llevan `in:`/`digits:`/regex. Los más relevantes:
  - `blood_type` (max 5), `blood_rh` (`in:positive,negative`)
  - `has_diabetes`, `has_heart_condition`, `has_epilepsy`, `has_asthma`, `has_allergy`, `takes_medication`, `has_allergens`, `has_hypertension` — booleanos, se coaccionan a `false` si se omiten (ver nota abajo)
  - `diabetes_type` (`in:I,II`)
  - `emergency_contact_phone`, `emergency_contact_mobile`, `treating_physician_phone`, `insurance_mobile` — exactamente 10 dígitos (NO formato E.164, sin `+` ni espacios)
  - `social_security_number` — exactamente 11 dígitos (NSS mexicano)
  - Varios campos de texto libre (`medication_details`, `special_conditions`, `medical_insurance`, etc.) con límites de caracteres — revisar `ClinicalHistoryRequest::rules()` para el detalle completo si se necesita.
- Éxito (200): `{"success": true, "message": "Historia clínica guardada correctamente.", "data": {...mismo shape que GET...}}`
- Errores: `422` validación; `404` `{"success": false, "message": "El miembro especificado no existe.", "data": null}`.
- **Importantísimo para la app**:
  - Mismo problema de autorización que el GET — no hay verificación de que `member_id` pertenezca al usuario.
  - Es un **upsert total, no parcial**: los campos booleanos que no se envíen se guardan como `false` (no se preserva el valor anterior). **La app debe enviar siempre el estado completo del formulario** (todos los booleanos) en cada guardado, o corre el riesgo de borrar silenciosamente datos ya capturados.

---

## 4. Pagos y facturación

Patrón común: el socio debe ser **titular** (`is_primary_holder`) de una cuenta con membresía activa/suspendida en el club — de lo contrario, `403`. Si el usuario no tiene `Member`, `404`. Estos dos checks se repiten en casi todos los endpoints de este bloque.

### GET /clubs/{club}/account-statement
- Auth: sí. `{club}` con binding directo.
- Query: `period` (`month`|`quarter`|`year`, default `year`), `year` (2020..año+1, default actual), `month` (requerido si `period=month`), `quarter` (requerido si `period=quarter`), `per_page` (1-50, default 15), `filter` (`pending`|`paid`, opcional — solo filtra la lista de cargos, **no** el resumen/semáforo).
- Éxito (200):
  ```json
  {
    "success": true,
    "data": {
      "period": {"type": "...", "year": 2026, "label": "string"},
      "filter": "pending|paid|null",
      "semaforo": "red|yellow|green",
      "total_owed": 0.0,
      "summary": [ {"type": "code", "type_label": "string", "total_charges": 0.0, "total_paid": 0.0, "total_pending": 0.0, "total_overdue": 0.0} ],
      "charges": { "data": [ {"id":1,"type":"code","type_label":"string","description":"string","period_year":2026,"period_month":7,"issue_date":"date","due_date":"date","amount":0.0,"balance":0.0,"display_status":"paid|overdue|pending"} ], "meta": {"current_page":1,"per_page":15,"has_more_pages":false} }
    }
  }
  ```
- Errores: `404` sin `Member`; `403` sin membresía activa; `403` `{"message": "Solo el socio titular puede consultar el estado de cuenta."}` si no es titular; `422` validación.
- `semaforo`: `red` si hay algo vencido, si no `yellow` si hay algo pendiente, si no `green`.

### GET /clubs/{club}/payments/pending
- Auth: sí. Query: `per_page` (1-50, default 15).
- Éxito (200): `{"success": true, "data": {"currency":"MXN","total":0.0,"count":N,"items":[...cargos con status pending/partial...],"meta":{...}}}`.
- `count` es el número de items **en la página actual**, no el total global — usar `total`/`meta.has_more_pages` para eso.
- `status` en cada item puede venir como `overdue` (calculado: pending/partial + vencido), distinto de los estados crudos de BD.

### GET /clubs/{club}/payments/history
- Auth: sí. Query: `search` (max 100, busca en referencia o método de pago), `date_from`, `date_to` (`after_or_equal:date_from`), `per_page` (1-50, default 15).
- Éxito (200): `{"success": true, "data": {"currency":"MXN","count":N,"items":[{"id":1,"type":"payment","reference":"...","payment_method":"...","amount":0.0,"paid_at":"datetime","receipt_available":false}],"filters":{...},"meta":{...}}}`.
- `receipt_available` siempre `false` (ver endpoint de receipt, no implementado aún).

### GET /clubs/{club}/payments/monthly-fees
- Auth: sí. Query: `year` (2020..año+1, default actual), `per_page` (1-50, default 15).
- Éxito (200): mismo shape de items que `pending`, filtrado a concepto `MONTHLY_FEE` del año dado, sin filtrar por estatus (incluye pagados, pendientes, parciales, todos juntos), ordenado por mes.

### GET /clubs/{club}/payments/{payment}
- Auth: sí. `{payment}` **no** es binding de modelo — el controlador intenta resolverlo primero como `Charge.id`, luego como `Payment.id` (ambos scoped a la cuenta del socio).
- Éxito (200): la respuesta trae `"type": "charge"` o `"type": "payment"` — **úsalo para saber qué shape llegó**, no hay forma de saberlo de antemano por el id. Si es `payment`, incluye `applications: [{charge_id, concept, amount}]`.
- Error: `404` `{"success": false, "message": "Pago o cargo no encontrado."}` si no coincide con ninguno de los dos.

### GET /clubs/{club}/payments/{payment}/receipt
- Auth: sí. `{payment}` con binding directo, ownership verificado en código (cuenta + club).
- **No implementado todavía** — siempre responde:
  ```json
  {"success": true, "data": {"payment_id": 1, "receipt_available": false, "url": null, "message": "El comprobante descargable aun no esta disponible."}}
  ```
  No construir un flujo de "descargar PDF" esperando datos reales todavía.

### GET /clubs/{club}/payment-sources
- Auth: sí. `{club}` **sin binding de modelo** (entero plano, sin validar que exista).
- Éxito (200): `{"success": true, "data": [{"id":1,"brand":"...","last4":"...","exp_month":0,"exp_year":0,"cardholder":"...","is_default":true,"created_at":"YYYY-MM-DD"}]}`.
- Cada club es una cuenta Conekta independiente — las tarjetas están scoped por club, una tarjeta guardada en un club no sirve para cobrar en otro.

### POST /clubs/{club}/payment-sources
- Auth: sí. Body: `token_id` (string, required — token de Conekta tipo `tok_xxx` generado por el SDK de Conekta en el cliente, **nunca enviar el número de tarjeta crudo a esta API**), `set_default` (boolean, opcional, default `false`).
- Éxito (201): `{"success": true, "message": "Tarjeta agregada correctamente.", "data": {...misma forma que index...}}`.
- Error `422` genérico si Conekta rechaza el token — el campo `error` expone el mensaje crudo de la excepción (útil para debug, no mostrar directo al usuario final, usar `message`).
- El token de Conekta es de un solo uso y de vida corta — generarlo y enviarlo inmediatamente, no cachear.

### DELETE /clubs/{club}/payment-sources/{source}
- Auth: sí. `{source}` con binding de modelo; ownership verificado (`member_id` + `club_id`).
- Éxito (200): `{"success": true, "message": "Tarjeta eliminada correctamente."}`.
- Error `403` `{"success": false, "message": "No autorizado."}` si la tarjeta no pertenece al socio/club.
- Si la tarjeta era la default, no se reasigna automáticamente otra — la app debe pedir elegir una nueva default si aplica.

### PATCH /clubs/{club}/payment-sources/{source}/set-default
- Auth: sí. Mismo ownership check que `destroy`. Éxito (200): `{"success": true, "message": "Tarjeta predeterminada actualizada.", "data": {...}}`.

### POST /clubs/{club}/spei-payment
- Auth: sí. Body: `applications` (array, min 1, cada uno `{charge_id, amount (>0)}`), `notes` (opcional, max 500).
- Éxito (201): `{"success": true, "message": "...", "data": {"spei_order_id":1,"clabe":"18 dígitos","bank":"string","amount":0.0,"expires_at":"ISO8601","description":"string"}}`.
- Errores: `404` sin Member; `422` `{"message": "El pago por transferencia SPEI no está habilitado para este club. Contacta a administración."}`; `404` sin membresía activa; `422` `{"message": "Uno o más cargos no son válidos o ya fueron pagados."}` (todo-o-nada, si un solo `charge_id` es inválido falla el lote completo); `500` si Conekta falla.
- **SPEI es asíncrono**: esta respuesta solo entrega la CLABE para transferir — NO confirma el pago. La confirmación llega después vía el webhook de Conekta (server-to-server, la app no lo llama). La app debe:
  1. Hacer polling a `GET .../spei-payment/{speiOrder}` hasta que `status` sea `paid` o `expired`, y/o
  2. Escuchar la notificación push que se dispara cuando el webhook confirma el pago (título "¡Pago recibido!", `data.type: 'spei_paid'`).
- Un socio puede tener varias órdenes SPEI pendientes simultáneamente.

### GET /clubs/{club}/spei-payment/{speiOrder}
- Auth: sí. `{speiOrder}` con binding de modelo, ownership verificado contra la cuenta del socio.
- Éxito (200): `{"success": true, "data": {"spei_order_id":1,"clabe":"...","bank":"...","amount":0.0,"expires_at":"ISO8601","status":"pending|paid|expired","payment_id":null|1}}`.
- Error: `404` `{"success": false, "message": "Orden no encontrada."}`.
- Nota: si la orden sigue `pending` y ya pasó `expires_at`, este GET la marca `expired` en el momento de la consulta (efecto secundario en un endpoint de lectura). Una vez marcada `expired`, una confirmación tardía del webhook será ignorada (no se reactiva a `paid`).

### POST /charge-payment
- Auth: sí. **No** va bajo `/clubs/{club}` — el club se manda en el body.
- Body: `payment_source_id` (int, required — id de una tarjeta ya guardada vía `payment-sources`), `club_id` (int, required), `applications` (array, min 1, `{charge_id, amount>0}`), `notes` (opcional, max 500).
- Éxito (201): `{"success": true, "message": "Pago procesado correctamente.", "data": {"payment_id":1,"amount":0.0,"paid_at":"date","conekta_order":"ord_xxx","conekta_charge":"chr_xxx"}}`.
- Errores: `404` sin Member; `404` `{"message": "La tarjeta seleccionada no está disponible."}` (tarjeta de otro club/socio); `422` método no habilitado para el club; `404` **requiere membresía `active`, NO acepta `suspended`** (inconsistente con SPEI, que sí acepta `suspended`); `422` `{"message": "El pago fue rechazado por el procesador...", "conekta_status": "..."}` si Conekta no marca el cargo como pagado; `422` de `PaymentRegistrationService` si la validación de cargos falla — **esto puede pasar incluso después de que Conekta ya cobró la tarjeta**, es decir, un 422 aquí no garantiza que no se haya cobrado dinero; `500` genérico.
- **⚠️ Sin idempotencia**: no hay idempotency key hacia Conekta ni de parte del cliente. Si la app reintenta este POST tras un timeout sin deduplicar, puede **cobrar dos veces** la misma tarjeta. La app debe deshabilitar el botón de pago tras el primer tap y/o verificar `payments/history` antes de reintentar — el backend no protege contra esto.

### POST /webhooks/conekta (no lo llama la app — informativo)
- Auth: no (público, endpoint server-to-server de Conekta). Sin verificación de firma criptográfica en el código — la mitigación es a nivel de red (IP allow-list en el panel de Conekta), no una validación HMAC en la app.
- Solo procesa el evento `charge.paid` para confirmar órdenes SPEI; cualquier otro tipo se ignora silenciosamente y responde `200 OK` (Conekta reintenta hasta 13 veces si no recibe 200, por eso siempre responde 200 incluso ante error interno).
- Efecto relevante para la app: al confirmar un pago SPEI, dispara la notificación push "¡Pago recibido!" (`data.type: 'spei_paid'`) que la app debe escuchar.

---

## 5. Reservaciones y amenidades

### POST /reservations
- Auth: sí. Body: `start_datetime`/`end_datetime` (`Y-m-d H:i`, `end` después de `start`), `club_id` (debe existir), `amenity_resource_id` (debe existir).
- Éxito (201): `{"success": true, "message": "Reservación creada correctamente", "reservation": {...ver shape abajo...}}`.
- Errores: `422` validación; `422` `{"error": "Error de validación", "error_details": "No se encontró un socio asociado a este usuario"}`; `422` `{"error": "Error de regla", "error_details": "<mensaje>"}` por cualquiera de estas reglas de negocio, en este orden:
  1. **Días de anticipación** (`system_variables.dias_para_crear_reserva`, global no por club): "Solo puedes reservar hasta {N} dias a partir de hoy".
  2. **Penalización por inasistencias**: 2+ inasistencias consecutivas recientes bloquean nuevas reservas por `horas_suspension_reserva` horas desde la última inasistencia: "No puedes reservar debido a inasistencias recientes...".
  3. **Traslape del mismo socio**: "No puedes reservar en el mismo horario".
  4. **Reservas consecutivas** en el mismo recurso: "No puedes hacer reservaciones consecutivas".
  5. **Capacidad**: para amenidades `reservation_type = 'daily'` la capacidad es 1 (binaria); para las demás, usa `amenityResource.capacity`. "Ya no hay capacidad disponible para esta amenidad en este horario".
- Nota: existe una regla de "máximo de reservas por día" (`ReservationsPerDayRule`) en el código pero **no está conectada a este endpoint de la API** (solo se usa en el panel admin web) — la app no debe asumir ese límite.

### PUT/PATCH /reservations/{reservation}
- Auth: sí. A pesar del verbo, la acción real es **cancelar**, no editar campos (no lee ningún campo del body).
- Éxito (200): `{"success": true, "message": "Reservación cancelada correctamente"}`.
- Error `422`: **regla de días para cancelar** (`dias_para_cancelar_reserva`, global): no se puede cancelar el mismo día o después de que ya pasó, ni con menos de N días de anticipación.
- **⚠️ Sin verificación de dueño**: el controlador no valida que la reservación pertenezca al usuario autenticado — cualquiera que conozca/adivine un `{reservation}` id puede cancelarla. Diseñar la app para nunca exponer ids de reservaciones ajenas.

### DELETE /reservations/{reservation}
- Auth: sí. Éxito (200): `{"success": true, "message": "Reservación eliminada correctamente"}`.
- **⚠️ Sin ninguna validación de negocio ni de dueño** — borra físicamente el registro sin pasar por las reglas de cancelación. Confirmar con el equipo de backend si este endpoint debería estar expuesto a la app móvil o es legado/solo-admin antes de usarlo.

### GET /my-reservations
- Auth: sí. Query: `club_id`, `status_id` (lista separada por comas, ej. `1,2`), `amenity_id`, `date_from`/`date_to` (`Y-m-d`), `sort` (`asc`/`desc`, default `asc`), `per_page` (1-50, default 15).
- Éxito (200): `{"success": true, "message": "...", "reservations": [{"label":"Hoy|Mañana|...","date":"Y-m-d","items":[...ReservationResource]}], "pagination": {"current_page":1,"per_page":15,"total":0,"last_page":1,"has_more_pages":false}}`.
  Si el usuario no tiene `Member`, responde `{"success": true, "message": "No hay reservaciones para este usuario", "reservations": []}` **sin la clave `pagination`** — manejar su ausencia.
- Nota: la paginación aplica sobre la lista plana antes de agrupar por fecha — un grupo de fecha puede no reflejar el total real de esa fecha si hay más páginas.

**Shape de `ReservationResource`** (usado en `store` y `my-reservations`):
```json
{
  "id": 1, "start_time": "datetime", "end_time": "datetime", "date": "Y-m-d|null", "time": "H:i|null",
  "date_label": "string|null", "duration_minutes": 0, "cancelled_at": "datetime|null",
  "club": {"id":1,"name":"string"}, "amenityResource": {"id":1,"name":"string"},
  "amenity": {"id":1,"name":"string","reservation_type":"daily|hourly|...","background_image_url":"string|null"},
  "status": {"id":1,"name":"string","color":"string|null"}, "created_at": "datetime"
}
```

### POST /guests-list
- Auth: sí. Body: `title` (max 100), `description`, `date` (opcional, `d-m-Y`), `time` (opcional, `H:i`), `club_id` (debe existir), `reservation_id` (opcional, debe existir), `guests[]` (array, min 1) — cada uno: `name`, `last_name`, `email`, `phone` (regex `^\+?[0-9]{8,15}$`), `age` (3-100), `is_billable_to_member` (boolean).
- Éxito (201): `{"success": true, "message": "Lista de invitados creada correctamente"}` — **no** devuelve el id de la lista creada.
- Errores `422` de regla: sin precios configurados para el club (`NORMAL_PRICE`/`SPECIAL_PRICE`); sin máximo de invitados configurado; excede el máximo permitido.
- Precio por invitado: edad `>= 7` → `NORMAL_PRICE`, edad `< 7` → `SPECIAL_PRICE` (variables por club en `GuestListVariable`). Solo los invitados con `is_billable_to_member = true` se suman al cargo del socio.

### GET /amenities/{amenityResource}/available-slots
- Auth: sí. Query: `date` (required, `Y-m-d`).
- Éxito (200): `{"success": true, "message": "...", "available_slots": [{"start":"Y-m-d H:i:s","end":"Y-m-d H:i:s","capacity":0,"reserved":0,"available_spots":0,"status":"available|partial|full|blocked"}]}`. Array vacío (con 200 igual) si el recurso está inactivo o no hay horario configurado ese día.
- **⚠️ Los errores de validación de `date` faltante/malformada se reportan como `500`**, no `422` (el catch genérico atrapa la `ValidationException` antes de que el framework la formatee) — la app debe validar el formato de fecha del lado del cliente antes de llamar.
- Para amenidades `daily`/`hourly`, la capacidad es efectivamente binaria (1 slot = 1 reservación), ignorando `amenityResource.capacity` — solo los demás tipos usan la capacidad real configurada.

### GET /clubs/{club}/amenities
- Auth: sí. Éxito (200): `{"success": true, "message": "...", "amenities": [...AmenityResource[]...], "rules": {"dias_para_crear_reserva":N,"dias_para_cancelar_reserva":N,"horas_suspension_reserva":N,"available_dates":[{"date":"Y-m-d","label":"string","day":"dd","month":"string"}]}}`.
- **Inconsistencia a tener en cuenta**: los valores en `rules` sí están filtrados por `club_id`, pero las reglas que realmente se **aplican** al crear/cancelar reservas (ver arriba) consultan la variable global sin filtrar por club — los números mostrados aquí podrían no coincidir exactamente con lo que se aplica si distintos clubes configuran valores distintos.

**Shape `AmenityResource`**:
```json
{ "id":1,"name":"string","description":"string|null","reservation_type":"daily|hourly|...","icon_url":"string|null","background_image_url":"string|null","regulation_file_url":"string|null","club_id":1,"resources":[{"id":1,"name":"string","capacity":0,"slot_duration_minutes":0,"is_active":true}] }
```

### POST /check-in/resource/{resource}
- Auth: sí. Body: `latitude` (-90..90), `longitude` (-180..180), `member_id` (int, required — **no se toma del token, se manda explícito**).
- Éxito (200): `{"success": true, "message": "¡Asistencia registrada correctamente!", "checked_in_at": "ISO8601", "resource": "string", "amenity": "string", "reservation_id": 1}`.
- Errores: `404` `{"success": false, "message": "No se encontró una reservación activa para hoy en este recurso."}`; `422` sin ubicaciones configuradas para el recurso; `422` `{"success": false, "message": "No estás dentro del área del recurso...", "distance": N}` si el GPS no coincide.
- **No es un check-in por QR** — es puramente geofence por GPS. Radio de tolerancia muy estrecho: **5 metros**, considerar reintentos por imprecisión de GPS. `member_id` no se valida contra el token del usuario autenticado — revisar si esto es intencional para flujos de kiosco/tablet compartida.

---

## 6. Casilleros (Lockers)

### GET /lockers/index
- Auth: sí. Query: `account_id` (required), `category` (required, string), `club_id` (required, sin validación de existencia real en BD).
- Éxito (200): **array crudo** (sin envoltorio `success`) de casilleros con `status = 'disponible'` para esa cuenta/categoría/club.
- Errores: `422` validación (mensajes en español); `403` `{"message": "No perteneces a este club"}` si la cuenta no tiene membresía en ese club.

### GET /lockers/members
- Auth: sí. Query: `account_id`, `club_id` (ambos required).
- Éxito (200): array crudo `[{"label":"nombre apellido","value":1}]` — solo integrantes que **no** tienen ya un casillero asignado este año.
- Nota: `label` no incluye el segundo apellido aunque se consulta.

### POST /lockers/assign
- Auth: sí. Body: `locker_id`, `member_id`, `membership_account_id`, `club_id`, `category` (todos required; `category` se valida pero no se usa realmente en la lógica).
- Éxito (200): `{"message": "Casillero asignado correctamente", "amount": 0.0}` (costo prorrateado por meses restantes del año, base anual $1,100).
- Error `409` `{"message": "El casillero ya no está disponible"}` (protegido con `lockForUpdate` contra condiciones de carrera).
- **⚠️ La validación de "un socio, un casillero por año" está deshabilitada en el código** (comentada) — actualmente un mismo integrante **puede** terminar con más de un casillero asignado en el mismo año si se llama este endpoint más de una vez, aunque `/lockers/members` ya no lo muestre como candidato. Confirmar con backend antes de confiar en esta restricción desde la app.

---

## 7. Publicidad de negocios

### POST /business-ads
- Auth: sí, pero **el controlador nunca lee el usuario autenticado** — `member_id` y `club_id` se toman directo del body sin verificar que pertenezcan al dueño del token. Trátalo con cuidado: la app solo debe enviar el `member_id` del propio usuario logueado.
- Body (multipart, para poder subir imagen): `member_id`, `club_id`, `name` (max 255), `category_id` (debe ser una categoría activa del club), `image` (opcional, imagen, max 2MB), `description`, `address` (max 255), `phone` (max 20), `email`, `website` (max 255) — todos opcionales salvo los primeros cuatro.
- Éxito (201): objeto crudo del modelo `BusinessAd` (incluye `status_id`, timestamps, etc., no un DTO curado) + `category`.
- Errores `422`: usuario/club/categoría no existen; `{"message": "Ya existe un anuncio con este nombre para este usuario en este club"}` (duplicado).
- Todo anuncio nuevo entra como `status_id = 1` (Pendiente). Ciclo completo: `1 Pendiente → 2 Rechazado | 3 Aprobado → 4 Pagado → 5 Publicado → 6 Expirado`. Solo status `5` y no expirado aparece en los endpoints públicos de abajo.

### GET /clubs/{club}/business-categories
- Auth: sí. Éxito (200): `{"success": true, "message": "...", "data": [{"id":1,"name":"string","image_url":"string|null"}]}` — solo categorías activas.

### GET /clubs/{club}/business-ads
- Auth: sí. Query: `category_id` (opcional, sin validación estricta).
- Éxito (200): `{"success": true, "message": "...", "data": [{"id":1,"name":"string","category":{"id":1,"name":"string"}|null,"image_url":"string|null","description":"...","address":"...","phone":"...","email":"...","website":"...","published_at":"datetime|null","expires_at":"datetime|null"}]}`.
- Solo anuncios `status_id = 5` y (`expires_at` nulo o futuro). Sin verificación de membresía — cualquier token puede listar anuncios de cualquier club.

### GET /clubs/{club}/business-ads/{businessAd}
- Auth: sí. Mismo shape que un elemento de `index`. Errores `404` si el anuncio no pertenece al club, no está publicado, o ya expiró (mensajes distintos pero trátalos igual en la UI).

---

## 8. Encuestas

### GET /clubs/{club}/surveys
- Auth: sí (requiere `Member` vinculado al club). Éxito (200, **sin envoltorio `success`**): `{"data": [{"id":1,"title":"string","description":"string|null","questions_count":0}]}`.
- Solo encuestas `status = 'active'` que el socio **aún no ha respondido**. Errores: `404` `{"message": "Socio no encontrado."}`; `403` `{"message": "No tienes acceso a este club."}`.

### GET /clubs/{club}/surveys/{survey}
- Auth: sí. Éxito (200): `{"data": {"id":1,"title":"string","description":"string|null","questions":[{"id":1,"text":"string","type":"string","is_required":true,"config":{...},"options":[{"id":1,"text":"string"}]}]}}`.
- `type`/`config` son libres (definidos del lado admin, no enumerados aquí) — la app debe soportar dinámicamente los tipos que existan. `options` vacío para preguntas de texto libre.
- Errores: `404` socio/encuesta no encontrados; `403` encuesta no activa o sin acceso al club; **`409`** `{"message": "Ya respondiste esta encuesta."}`.

### POST /clubs/{club}/surveys/{survey}/responses
- Auth: sí. Body: `answers[]` — cada uno `{question_id (debe existir), answer_text (opcional), answer_options (array opcional de ints)}`.
- Éxito (201): `{"message": "¡Gracias! Tus respuestas fueron registradas correctamente."}`.
- Es un envío **único y total** por encuesta — no hay guardado parcial ni endpoint de actualización. `409` si ya se respondió (se revisa antes de validar el body).

---

## 9. Quejas y sugerencias (Feedback)

### GET /clubs/{club}/feedback/tickets
- Auth: sí. Query opcional: `type`, `status` (códigos reales sembrados: `SUBMITTED, UNDER_REVIEW, IN_PROGRESS, RESOLVED, REJECTED, CLOSED, CANCELLED` — el comentario en el código menciona menos estados de los que realmente existen, usar esta lista).
- Éxito (200): `{"success": true, "message": "...", "tickets": [{"id":1,"ticket_number":"FB-PE1-26-0001","title":"string","submitted_at":"...","resolved_at":"...","closed_at":"...","is_anonymous":true,"ticket_type":{...},"category":{...},"status":{"id":1,"name":"...","code":"...","color":"#hex"},"priority":{...}}]}`.
- Incluye tickets reportados directamente por el usuario **o** ligados a su `Member` (cubre tickets anónimos donde el `Member` sigue siendo el mismo).

### POST /clubs/{club}/feedback/tickets
- Auth: sí. Body (multipart si hay adjuntos): `title` (max 85), `description` (max 350), `is_anonymous` (opcional), `attachments[]` (opcional, max 5 archivos, imágenes, max 2MB c/u), `ticket_type_id`, `category_id`, `priority_id` (todos required).
- Éxito (201): objeto crudo del `Ticket` recién creado — **sin** relaciones cargadas (`type`/`category`/`status`/`priority` no vienen como en `index`/`show`). Si la app necesita esos nombres, debe llamar `GET .../feedback/tickets/{id}` después.
- Notas: `ticket_number` con formato `FB-{CÓDIGO_CLUB}-{año 2 dígitos}-{folio 4 dígitos}`. Si `is_anonymous = true`, `reported_by_user_id` se guarda `null` (el ticket solo queda ligado por `member_id`). Envía notificaciones por correo en segundo plano (no bloquean la respuesta si fallan).

### GET /clubs/{club}/feedback/tickets/{ticket}
- Auth: sí. Éxito (200): incluye `attachments[]` (con `url` lista para usar) y `comments[]` — **solo comentarios públicos** (`is_internal = false`), nunca se exponen notas internas del equipo de soporte.
- Error `404` `{"success": false, "message": "Ticket no encontrado"}` si no pertenece al club/usuario.

### PATCH /clubs/{club}/feedback/tickets/{ticket}/cancel
- Auth: sí. Éxito (200): `{"success": true, "message": "Ticket cancelado correctamente"}`.
- **Solo se puede cancelar mientras el status sea `SUBMITTED`** — cualquier otro estado (ya en revisión, resuelto, etc.) responde `422` `{"message": "Solo se puede cancelar cuando el ticket esta ENVIADO"}`. La app debe mostrar la opción de cancelar únicamente cuando `status.code === 'SUBMITTED'`.

---

## 10. Utilidades de desarrollo (no usar en producción)

Estos dos endpoints existen para pruebas internas del backend, no forman parte del flujo real de la app:

- `POST /email/test` — envía un correo de prueba vía la configuración SMTP dinámica de un club (`entity_id`, `to`, `subject`/`message` opcionales).
- `POST /firebase/test` — envía un push de prueba a un token FCM específico (`token`, `title`, `body`, `data` opcional).
- `GET /firebase/ping` — verifica que la conexión con Firebase esté viva.

---

## 11. Notas de seguridad y pendientes conocidos

Resumen de hallazgos durante la revisión del código, para que el equipo de backend los priorice y para que la app móvil tome precauciones mientras tanto:

1. **Historia clínica sin verificación de propiedad** (`POST/GET /clinical-history*`) — cualquier token puede leer/sobrescribir la historia clínica de cualquier `member_id`, incluyendo NSS, contacto de emergencia y condiciones médicas. La app debe autolimitarse a los `member_id` de la propia familia.
2. **Cancelar/eliminar reservación sin verificar dueño** (`PUT/PATCH` y `DELETE /reservations/{reservation}`) — cualquiera puede cancelar o borrar la reservación de otro socio si conoce el id. `DELETE` además no aplica ninguna regla de negocio (borrado físico directo).
3. **`POST /business-ads` confía en `member_id`/`club_id` del body** sin verificar contra el token autenticado.
4. **`POST /charge-payment` sin idempotencia** — riesgo real de doble cobro en reintentos de red; la app debe implementar su propia protección (deshabilitar botón, verificar historial antes de reintentar).
5. **Webhook de Conekta sin verificación de firma criptográfica** — la mitigación actual es solo a nivel de red (IP allow-list). No afecta directamente a la app móvil, pero es relevante si se audita la cadena de confirmación de pagos.
6. **Validación de asignación duplicada de casillero deshabilitada** (`POST /lockers/assign`) — un integrante puede terminar con más de un casillero en el mismo año.
7. **Bug de mensajes de validación** en `POST /lockers/assign`: las claves de mensaje personalizado usan `account_id.*` pero el campo real es `membership_account_id` — los errores de ese campo salen en inglés/default en vez del mensaje en español esperado.
8. **`GET /amenities/{amenityResource}/available-slots`**: errores de validación de `date` se reportan como `500`, no `422` — validar el formato de fecha del lado del cliente antes de llamar.
9. **Reglas de reservación leídas de variables globales, no por club** (`dias_para_crear_reserva`, `dias_para_cancelar_reserva`, `horas_suspension_reserva`) mientras que `GET /clubs/{club}/amenities` sí muestra los valores filtrados por club en `rules` — los números mostrados podrían no coincidir con lo realmente aplicado si los clubes tienen configuraciones distintas.
10. **Formato de respuesta inconsistente** entre controladores (ver sección 1) — no asumir un único parser de errores/envoltorio para toda la API.
