# API Reference — App Móvil Parque España

Referencia de la API consumida por la app móvil. Generada a partir del código en `routes/api.php` y `app/Http/Controllers/Api/V1/*`. Si el backend cambia, este documento puede quedar desactualizado — ante duda, el código es la fuente de verdad.

## 1. Convenciones generales

- **Base URL**: todas las rutas van bajo el prefijo `/api/v1` (ej. `https://tu-dominio.com/api/v1/login`).
- **Autenticación**: Laravel Sanctum, token tipo *personal access token*. Se obtiene en `POST /login` y se envía en cada request protegido como:
  ```
  Authorization: Bearer {token}
  Accept: application/json
  ```
  Los tokens **no expiran** (`config/sanctum.php` → `expiration: null`) — solo se invalidan con `POST /logout` o al resetear contraseña. No hay CSRF/cookies involucradas; es 100% bearer token, apto para stateless mobile.
- **Scoping por club**: casi todas las rutas relevantes están anidadas bajo `/clubs/{club}/...` o reciben `club_id` en el body/query. El club siempre se resuelve por parámetro de ruta o campo explícito, nunca por sesión.
- **Formato estándar de respuestas** — todos los controladores usan el trait `ApiResponder`. **No existe campo `success`** en ninguna respuesta. Hay cuatro shapes posibles:
  - **Solo datos** (`ok()`): `{ "data": { ... } }` o `{ "data": [ ... ] }` — GETs. HTTP 200.
  - **Solo mensaje** (`success()` / `created()` sin data): `{ "message": "..." }` — acciones sin retorno. HTTP 200 o 201.
  - **Mensaje + datos** (`success()` / `created()` con data): `{ "message": "...", "data": { ... } }` — cuando la acción retorna el recurso. HTTP 200 o 201.
  - **Error**: `{ "message": "..." }` — HTTP 403, 404, 409, 422, 500 según el caso.
- **Formato de error del framework** (`bootstrap/app.php`, aplica a rutas no manejadas por un controlador):
  - `401` sin token o token inválido: `{"message": "Unauthenticated"}`
  - `403` sin permiso (Spatie): `{"message": "Forbidden"}`
  - `404` ruta inexistente: `{"message": "Endpoint not found"}`
- **Errores de validación** (`$request->validate()`): HTTP `422` formato default de Laravel: `{"message": "...", "errors": { "campo": ["mensaje"] }}`.
- **Paginación**: la mayoría de los listados usan `simplePaginate` (sin `total`/`last_page`), expuesto como `meta: {current_page, per_page, has_more_pages}`. Diseñar la UI para scroll infinito, no para "página X de Y".
- **Moneda**: montos en pesos mexicanos. Los precios de Conekta se convierten a centavos internamente; la API siempre expone montos en unidades (no centavos) al cliente.

---

## 2. Autenticación

### POST /login
- Auth: no
- Body: `email` (string, required), `password` (string, required)
- Éxito (200):
  ```json
  {
    "message": "Inicio de sesión exitoso.",
    "data": {
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
  }
  ```
  `member` es `null` si el usuario no tiene un `Member` vinculado.
- Errores: `422` validación (formato Laravel default); `401` `{"message": "Credenciales incorrectas."}`.
- Notas:
  - Los permisos vienen agrupados por club (`PE1`/`PE2`) según el contexto Spatie `mobile_club_1`/`mobile_club_2`.
  - `clubs` puede tener más de un elemento si el socio pertenece a ambos parques.

### POST /logout
- Auth: sí
- Body opcional: `fcm_token` (string) — si se envía, desactiva ese token de dispositivo antes de revocar el token de sesión.
- Éxito (200): `{"message": "Sesión cerrada correctamente."}`
- Error: `500` `{"message": "No se pudo cerrar la sesión."}`
- Nota: revoca únicamente el token actual (`currentAccessToken()->delete()`), no todos los dispositivos del usuario.

### POST /forgot-password
- Auth: no
- Body: `email` (string, required, email)
- Éxito (200) — **misma respuesta exista o no la cuenta** (anti-enumeración): `{"message": "Si el correo está registrado, recibirás un código en breve."}`
- Errores: `429` `{"message": "Ya enviamos un código recientemente. Espera un momento antes de solicitar otro."}`; `422` validación.
- Notas: OTP de 6 dígitos, hasheado, expira en `config('auth.otp_expiry_minutes', 1440)` minutos (24h por defecto).

### POST /reset-password
- Auth: no
- Body: `email` (required, email), `otp` (required, string, exactamente 6 caracteres), `password` (required, min 8). `password_confirmation` no se valida en backend.
- Éxito (200): `{"message": "Contraseña actualizada correctamente. Inicia sesión con tu nueva contraseña."}`
- Errores:
  - `422` `{"message": "El código es inválido o ha expirado. Solicita uno nuevo."}`
  - `422` `{"message": "El código ingresado es incorrecto."}`
  - `404` `{"message": "No se encontró una cuenta con ese correo."}`
- **Importante**: al cambiar la contraseña se revocan **todos** los tokens Sanctum del usuario — la app debe forzar login de nuevo después de un reset exitoso.

---

## 3. Perfil del socio

### GET /my-profile
- Auth: sí
- Query: `club_id` (integer, opcional) — si se envía y el socio tiene membresía primaria activa/suspendida en ese club, agrega `club_membership` a la respuesta.
- Éxito (200):
  ```json
  {
    "data": {
      "id": 1, "full_name": "string", "first_name": "string", "last_name": "string",
      "second_last_name": "string", "email": "string", "phone": "string",
      "birthdate": "date", "age": 0, "photo_url": "string|null (URL firmada, expira en 30 min)",
      "address": { "street": "...", "neighborhood": "...", "postal_code": "...", "city": {}, "state": {}, "country": {} },
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
  `club_membership` solo aparece si se envió `club_id`; puede ser `null` si no hay membresía primaria activa/suspendida en ese club.
- Error: `404` `{"message": "No se encontró un perfil de socio asociado a este usuario."}`

### POST /device-token
- Auth: sí
- Body: `token` (string, required, token FCM), `platform` (required, `android`|`ios`|`web`), `device_name` (string, opcional, max 100).
- Éxito (200): `{"message": "Token de dispositivo registrado."}`
- Notas: upsert por `token` — si el mismo token ya estaba asignado a otro usuario, se reasigna al actual. Suscribe el token a los topics de Firebase de todos los clubes del usuario.

### DELETE /device-token
- Auth: sí
- Body: `token` (string, required)
- Éxito (200): `{"message": "Token de dispositivo desactivado."}` (incluso si no había ningún registro que coincidiera)
- Nota: desactiva (`is_active = false`) el token, scoped por `token` + `user_id`. Llamar en logout.

### GET /clubs/{club}/family-members
- Auth: sí
- Éxito (200):
  ```json
  {
    "data": {
      "membership_number": "string", "membership_type": "string",
      "active_members": 0, "max_members": null,
      "members": [
        {
          "id": 1, "full_name": "string", "photo_url": "string|null",
          "birthdate": "date|null", "age": 0, "relationship": "string",
          "is_primary_holder": true, "member_number": null
        }
      ]
    }
  }
  ```
- Errores:
  - `403` `{"message": "No tienes acceso a esta sección. Solo los socios titulares pueden ver los integrantes."}`
  - `403` `{"message": "Esta sección solo está disponible para membresías familiares."}`
- Notas: `max_members` y `member_number` están hardcodeados a `null` (no implementados). `photo_url` usa URL pública (`Storage::url()`).

### GET /my-documents
- Auth: sí
- Éxito (200):
  ```json
  {
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
- **Importante**: devuelve los documentos de **toda la cuenta** (titular + integrantes). Usar `is_self` para identificar los propios. `url` es `null` si no se pudo generar.

### POST /clinical-history-list
- Auth: sí
- Body: `member_id` (requerido en la práctica, sin `validate()` formal — enviar algo inválido da 404, no 422 limpio).
- Éxito (200): `{"data": { ...objeto completo de ClinicalHistory... }}`
- Error: `404` `{"message": "No existe información clínica para este miembro."}`
- **⚠️ Sin verificación de propiedad**: cualquier token puede consultar la historia de cualquier `member_id`. La app debe autolimitarse a `member_id` de la propia familia.

### POST /clinical-history
- Auth: sí
- Body: `member_id` + todos los campos de la historia clínica (todos opcionales): `blood_type`, `blood_rh` (`positive|negative`), booleanos de condiciones (`has_diabetes`, `has_heart_condition`, etc.), teléfonos de exactamente 10 dígitos, `social_security_number` de 11 dígitos, campos de texto libre — ver `ClinicalHistoryRequest::rules()` para el detalle completo.
- Éxito (200): `{"message": "Historia clínica guardada correctamente.", "data": { ...mismo shape que GET... }}`
- Errores: `422` validación; `404` `{"message": "El miembro especificado no existe."}`.
- **Importantísimo**: es un **upsert total** — booleanos omitidos se guardan como `false`. La app debe enviar siempre el estado completo del formulario, nunca solo los campos modificados.

---

## 4. Pagos y facturación

Patrón común: el socio debe ser **titular** (`is_primary_holder`) de una cuenta con membresía activa/suspendida en el club — de lo contrario `403`. Si el usuario no tiene `Member`, `404`.

### GET /clubs/{club}/account-statement
- Auth: sí. Query: `period` (`month`|`quarter`|`year`, default `year`), `year`, `month`, `quarter`, `per_page` (1-50, default 15), `filter` (`pending`|`paid`, opcional).
- Éxito (200):
  ```json
  {
    "data": {
      "period": {"type": "...", "year": 2026, "label": "string"},
      "filter": "pending|paid|null",
      "semaforo": "red|yellow|green",
      "total_owed": 0.0,
      "summary": [ {"type": "code", "type_label": "string", "total_charges": 0.0, "total_paid": 0.0, "total_pending": 0.0, "total_overdue": 0.0} ],
      "charges": {
        "data": [ {"id":1,"type":"code","type_label":"string","description":"string","period_year":2026,"period_month":7,"issue_date":"date","due_date":"date","amount":0.0,"balance":0.0,"display_status":"paid|overdue|pending"} ],
        "meta": {"current_page":1,"per_page":15,"has_more_pages":false}
      }
    }
  }
  ```
- Errores: `404` sin `Member`; `403` sin membresía activa; `403` `{"message": "Solo el socio titular puede consultar el estado de cuenta."}`.
- `semaforo`: `red` si hay algo vencido, `yellow` si hay algo pendiente, `green` si todo está al corriente.

### GET /clubs/{club}/payments/pending
- Auth: sí. Query: `per_page` (1-50, default 15).
- Éxito (200): `{"data": {"currency":"MXN","total":0.0,"count":N,"items":[...cargos pending/partial...],"meta":{...}}}`.
- `count` es el número de items en la página actual, no el total global.

### GET /clubs/{club}/payments/history
- Auth: sí. Query: `search`, `date_from`, `date_to`, `per_page` (1-50, default 15).
- Éxito (200): `{"data": {"currency":"MXN","count":N,"items":[{"id":1,"type":"payment","reference":"...","payment_method":"...","amount":0.0,"paid_at":"datetime","receipt_available":false}],"filters":{...},"meta":{...}}}`.

### GET /clubs/{club}/payments/monthly-fees
- Auth: sí. Query: `year`, `per_page` (1-50, default 15).
- Éxito (200): mismo shape que `pending`, filtrado a `MONTHLY_FEE` del año dado, incluye todos los estados (pagados, pendientes, parciales), ordenado por mes.

### GET /clubs/{club}/payments/{payment}
- Auth: sí. `{payment}` se resuelve primero como `Charge.id`, luego como `Payment.id` (scoped a la cuenta del socio).
- Éxito (200): `{"data": { ...objeto con campo "type": "charge"|"payment"... }}` — usar `type` para saber el shape. Si es `payment`, incluye `applications: [{charge_id, concept, amount}]`.
- Error: `404` `{"message": "Pago o cargo no encontrado."}`.

### GET /clubs/{club}/payments/{payment}/receipt
- Auth: sí. **No implementado** — siempre responde:
  ```json
  {"data": {"payment_id": 1, "receipt_available": false, "url": null, "message": "El comprobante descargable aún no está disponible."}}
  ```

### GET /clubs/{club}/conekta-public-key
- Auth: sí.
- Éxito (200): `{"data": {"public_key": "key_xxx"}}` — llave pública de Conekta de la cuenta comercial de **ese** club, para tokenizar tarjetas del lado del cliente antes de llamar a `POST /clubs/{club}/payment-sources`.
- Error `422` `{"message": "El pago con tarjeta no está configurado para este club."}` si el club no tiene el método Conekta activo ni hay llave global de respaldo.
- **Importante**: cada parque es una cuenta Conekta independiente (llaves distintas de verdad, confirmado en producción) — un token generado con la llave pública de un club **no se puede usar** para tokenizar en el otro. Pedir esta llave siempre justo antes de tokenizar (no cachearla más allá de la sesión de captura de tarjeta): como el token de sesión no expira, si la llave rota en Conekta un valor guardado de más tiempo atrás podría quedar obsoleto.
- **Flujo recomendado para socios titulares en ambos parques**: al agregar una tarjeta, pedir esta llave para cada club donde el socio sea titular activo, tokenizar la misma tarjeta una vez por cada llave (antes de descartar los datos de la tarjeta), y llamar `POST /clubs/{club}/payment-sources` una vez por club con su propio token. Son dos llamadas independientes — si una falla, la otra puede seguir adelante sin revertirse.
- **Caso sin solución automática**: si el socio agrega su tarjeta siendo titular de un solo parque y **después** se inscribe al segundo, no hay forma de recuperar el número de tarjeta para tokenizarlo en el nuevo club — el dato nunca se guarda en ningún lado (ni debe guardarse). No hay aviso automático del backend para este caso; la app debe ofrecerle al socio, en algún punto de su flujo (ej. al ver que `GET .../payment-sources` viene vacío para ese club), la opción de agregar su tarjeta ahí también.

### GET /clubs/{club}/payment-sources
- Auth: sí.
- Éxito (200): `{"data": [{"id":1,"brand":"...","last4":"...","exp_month":0,"exp_year":0,"cardholder":"...","is_default":true,"created_at":"YYYY-MM-DD"}]}`.
- Las tarjetas están scoped por club — una tarjeta de un club no sirve para cobrar en otro.

### POST /clubs/{club}/payment-sources
- Auth: sí. Body: `token_id` (string, required — token Conekta `tok_xxx`, tokenizado con la llave pública de **este mismo club**), `set_default` (boolean, opcional).
- Éxito (201): `{"message": "Tarjeta agregada correctamente.", "data": { ...mismo shape que index... }}`.
- Error `422` si Conekta rechaza el token.

### DELETE /clubs/{club}/payment-sources/{source}
- Auth: sí. Ownership verificado (`member_id` + `club_id`).
- Éxito (200): `{"message": "Tarjeta eliminada correctamente."}`.
- Error `403` `{"message": "No autorizado."}` si la tarjeta no pertenece al socio/club.

### PATCH /clubs/{club}/payment-sources/{source}/set-default
- Auth: sí. Éxito (200): `{"message": "Tarjeta predeterminada actualizada.", "data": { ...shape de la tarjeta... }}`.

### POST /clubs/{club}/spei-payment
- Auth: sí. Body: `applications` (array, min 1, cada uno `{charge_id, amount (>0)}`), `notes` (opcional, max 500).
- Éxito (201): `{"message": "...", "data": {"spei_order_id":1,"clabe":"18 dígitos","bank":"string","amount":0.0,"expires_at":"ISO8601","description":"string"}}`.
- Errores: `404` sin Member; `422` SPEI no habilitado; `404` sin membresía activa; `422` cargos inválidos o ya pagados; `500` Conekta falla.
- **SPEI es asíncrono**: esta respuesta solo entrega la CLABE. Hacer polling a `GET .../spei-payment/{speiOrder}` o escuchar push `data.type: 'spei_paid'`.

### GET /clubs/{club}/spei-payment/{speiOrder}
- Auth: sí. Ownership verificado.
- Éxito (200): `{"data": {"spei_order_id":1,"clabe":"...","bank":"...","amount":0.0,"expires_at":"ISO8601","status":"pending|paid|expired","payment_id":null|1}}`.
- Error: `404` `{"message": "Orden no encontrada."}`.
- Nota: si la orden sigue `pending` y ya pasó `expires_at`, este GET la marca `expired` como efecto secundario.

### POST /charge-payment
- Auth: sí. **No** va bajo `/clubs/{club}` — el club se manda en el body.
- Body: `payment_source_id` (int), `club_id` (int), `applications` (array, min 1, `{charge_id, amount>0}`), `notes` (opcional).
- Éxito (201): `{"message": "Pago procesado correctamente.", "data": {"payment_id":1,"amount":0.0,"paid_at":"date","conekta_order":"ord_xxx","conekta_charge":"chr_xxx"}}`.
- Errores: `404` sin Member; `404` tarjeta no disponible; `422` método no habilitado; `404` sin membresía **activa** (no acepta `suspended`, distinto de SPEI); `422` pago rechazado por Conekta; `500` genérico.
- **⚠️ Sin idempotencia**: riesgo de doble cobro en reintentos. Deshabilitar botón tras el primer tap.

### POST /webhooks/conekta (no lo llama la app — informativo)
- Auth: no. Solo procesa `charge.paid` para confirmar SPEI. Dispara push `data.type: 'spei_paid'` al confirmar.

---

## 5. Flujo típico: socio titular en ambos parques

Un socio puede ser titular activo en Parque España I **y** Parque España II a la vez (membresía interclub). Como cada parque es una cuenta de comercio Conekta independiente, todo lo relacionado a tarjetas y cobros pasa **por duplicado, una vez por club**, aunque para el socio se sienta como una sola tarjeta y una sola mensualidad. Esto no es evidente leyendo cada endpoint por separado, así que se documenta aparte.

### A) Agregar una tarjeta (una sola vez, flujo de alta)

1. Al hacer login (o `GET /my-profile`), la app ya sabe en qué clubes el socio es titular activo — es el arreglo `clubs[]` de la respuesta de `POST /login`, filtrado a `is_primary_holder: true` y `status: "active"`.
2. El socio llena el formulario de tarjeta (número, vigencia, CVV) **una sola vez**, en una sola pantalla.
3. Al confirmar, la app hace lo siguiente en segundo plano, sin pantallas adicionales para el usuario:
   - Por cada club de esa lista, pide `GET /clubs/{club}/conekta-public-key`.
   - Tokeniza los mismos datos de la tarjeta una vez por cada llave pública obtenida (ej. si es titular en PE1 y PE2, se generan dos tokens: `tok_AAA` con la llave de PE1, `tok_BBB` con la llave de PE2). Esto debe pasar **antes** de que la app descarte los datos de la tarjeta de memoria — el número de tarjeta nunca se envía a nuestro backend.
   - Llama `POST /clubs/{club}/payment-sources` una vez por club, con el token que le corresponde a ese club.
4. Resultado: una fuente de pago guardada por cada club (misma tarjeta física, registros independientes). Las dos llamadas son independientes — si una falla (ej. el banco la rechaza en una de las dos cuentas), la otra puede seguir adelante; no hay rollback conjunto.
5. **Caso sin solución automática**: si el socio agrega su tarjeta siendo titular de un solo parque y *después* se inscribe al segundo, no existe forma de recuperar el número de tarjeta para tokenizarlo en el club nuevo — ese dato nunca se guarda en ningún lado (ni debe guardarse), y el backend no manda ningún aviso automático para este caso. La app debe ofrecerle al socio, en algún punto de su flujo (por ejemplo, al notar que `GET /clubs/{club}/payment-sources` viene vacío para un club donde sí es titular), la opción de agregar su tarjeta también ahí.

### B) Pagar una mensualidad que involucra ambos parques

La mensualidad dividida 50/50 entre parques **no es un solo cargo combinado** — son dos cargos independientes, uno en `billing.charges` de cada club (cada uno ligado a la membresía de ese club en particular). Para cobrarla:

1. Traer los cargos pendientes de cada club por separado: `GET /clubs/{club_1}/payments/pending` y `GET /clubs/{club_2}/payments/pending` (se puede combinar en una sola pantalla de "tu mensualidad" del lado de la UI, pero son dos llamadas).
2. Llamar `POST /charge-payment` **dos veces**: una con `club_id` del primer parque, el `payment_source_id` de la tarjeta guardada ahí, y los `charge_id` de ese parque; y otra igual con los datos del segundo parque.
3. Cada llamada golpea una cuenta de Conekta distinta — son dos resultados independientes (una puede aprobarse y la otra no). La app debe manejarlos como dos operaciones separadas, no como una sola transacción.

---

## 6. Reservaciones y amenidades

### POST /reservations
- Auth: sí. Body: `start_datetime`/`end_datetime` (`Y-m-d H:i`), `club_id`, `amenity_resource_id`.
- Éxito (201): `{"message": "Reservación creada correctamente.", "data": { ...ReservationResource... }}`.
- Errores: `422` validación; `404` sin Member; `422` reglas de negocio en este orden:
  1. Días de anticipación (`dias_para_crear_reserva`): "Solo puedes reservar hasta {N} dias a partir de hoy".
  2. Penalización por inasistencias: "No puedes reservar debido a inasistencias recientes...".
  3. Traslape del mismo socio: "No puedes reservar en el mismo horario".
  4. Reservas consecutivas: "No puedes hacer reservaciones consecutivas".
  5. Capacidad: "Ya no hay capacidad disponible para esta amenidad en este horario".

### PUT/PATCH /reservations/{reservation}
- Auth: sí. La acción real es **cancelar** — no lee ningún campo del body.
- Éxito (200): `{"message": "Reservación cancelada correctamente."}`.
- Error `422`: regla de días para cancelar (`dias_para_cancelar_reserva`).
- **⚠️ Sin verificación de dueño**: cualquiera con el `{id}` puede cancelar la reservación de otro socio.

### DELETE /reservations/{reservation}
- Auth: sí. Éxito (200): `{"message": "Reservación eliminada correctamente."}`.
- **⚠️ Sin validación de negocio ni de dueño** — borra físicamente el registro. Confirmar si debe estar expuesto en la app.

### GET /my-reservations
- Auth: sí. Query: `club_id`, `status_id` (lista separada por comas), `amenity_id`, `date_from`/`date_to`, `sort` (`asc`/`desc`), `per_page` (1-50, default 15).
- Éxito (200): `{"data": {"groups": [{"label":"Hoy|Mañana|...","date":"Y-m-d","items":[...ReservationResource]}], "pagination": {"current_page":1,"per_page":15,"total":0,"last_page":1,"has_more_pages":false}}}`.
  Si el usuario no tiene `Member`: `{"data": {"groups": [], "pagination": null}}` — manejar `pagination` como nullable.

**Shape de `ReservationResource`**:
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
- Auth: sí. Body (multipart si hay adjuntos): `title`, `description`, `date` (`d-m-Y`), `time` (`H:i`), `club_id`, `reservation_id` (opcional), `guests[]` — cada uno: `name`, `last_name`, `email`, `phone` (regex `^\+?[0-9]{8,15}$`), `age` (3-100), `is_billable_to_member`.
- Éxito (201): `{"message": "Lista de invitados creada correctamente."}` — **no** devuelve el id de la lista.
- Errores `422`: sin precios configurados; sin máximo configurado; excede el máximo permitido.

### GET /amenities/{amenityResource}/available-slots
- Auth: sí. Query: `date` (required, `Y-m-d`).
- Éxito (200): `{"data": [{"start":"Y-m-d H:i:s","end":"Y-m-d H:i:s","capacity":0,"reserved":0,"available_spots":0,"status":"available|partial|full|blocked"}]}`. Array vacío si el recurso está inactivo o no hay horario ese día.
- **⚠️ Errores de validación de `date` se reportan como `500`** — validar el formato del lado del cliente.

### GET /clubs/{club}/amenities
- Auth: sí.
- Éxito (200): `{"data": {"amenities": [...AmenityResource...], "rules": {"dias_para_crear_reserva":N,"dias_para_cancelar_reserva":N,"horas_suspension_reserva":N,"available_dates":[{"date":"Y-m-d","label":"string","day":"dd","month":"string"}]}}}`.
- Nota: los valores en `rules` están filtrados por club, pero las reglas realmente aplicadas al crear/cancelar leen la variable global — pueden no coincidir si los clubes tienen configuraciones distintas.

**Shape `AmenityResource`**:
```json
{ "id":1,"name":"string","description":"string|null","reservation_type":"daily|hourly|...","icon_url":"string|null","background_image_url":"string|null","regulation_file_url":"string|null","club_id":1,"resources":[{"id":1,"name":"string","capacity":0,"slot_duration_minutes":0,"is_active":true}] }
```

### POST /check-in/resource/{resource}
- Auth: sí. Body: `latitude` (-90..90), `longitude` (-180..180), `member_id` (int, required — **no se toma del token**).
- Éxito (200): `{"message": "¡Asistencia registrada correctamente!", "data": {"checked_in_at": "ISO8601", "resource": "string", "amenity": "string", "reservation_id": 1}}`.
- Errores: `404` sin reservación activa; `422` sin ubicaciones configuradas; `422` `{"message": "No estás dentro del área del recurso. Acércate e intenta de nuevo.", "distance": N}`.
- Radio de tolerancia: **5 metros**. `member_id` no se valida contra el token — diseñado para kioscos/tablets compartidas.

---

## 7. Casilleros (Lockers)

### GET /lockers/index
- Auth: sí. Query: `account_id`, `category`, `club_id` (todos required).
- Éxito (200): `{"data": [...casilleros disponibles...]}`.
- Errores: `422` validación; `403` `{"message": "No perteneces a este club"}`.

### GET /lockers/members
- Auth: sí. Query: `account_id`, `club_id` (ambos required).
- Éxito (200): `{"data": [{"label":"nombre apellido","value":1}]}` — solo integrantes sin casillero asignado este año.

### POST /lockers/assign
- Auth: sí. Body: `locker_id`, `member_id`, `membership_account_id`, `club_id`, `category` (todos required).
- Éxito (200): `{"message": "Casillero asignado correctamente.", "data": {"amount": 0.0}}`.
- Error `409` `{"message": "El casillero ya no está disponible"}`.
- **⚠️ Validación de "un socio, un casillero por año" está deshabilitada en el código** — un integrante puede terminar con más de un casillero.

---

## 8. Publicidad de negocios

### POST /business-ads
- Auth: sí, pero **`member_id` y `club_id` se toman del body sin verificar contra el token**. La app solo debe enviar el `member_id` del usuario logueado.
- Body (multipart): `member_id`, `club_id`, `name` (max 255), `category_id` (categoría activa del club), `image` (opcional, imagen, max 2MB), `description`, `address` (max 255), `phone` (max 20), `email`, `website` (max 255).
- La imagen se almacena en **Digital Ocean Spaces** (disco `spaces`). La `image_url` devuelta apunta directamente al CDN público.
- Éxito (201): `{"message": "Anuncio creado correctamente."}`.
- Errores `422`: usuario/club/categoría no existen; `409` `{"message": "Ya existe un anuncio con este nombre para este usuario en este club."}`.
- Todo anuncio nuevo entra como `status_id = 1` (Pendiente). Ciclo: `1 Pendiente → 2 Rechazado | 3 Aprobado → 4 Pagado → 5 Publicado → 6 Expirado`. Solo `status_id = 5` y no expirado aparece en los GETs.

### GET /clubs/{club}/business-categories
- Auth: sí. Éxito (200): `{"data": [{"id":1,"name":"string","image_url":"string|null"}]}` — solo categorías activas.

### GET /clubs/{club}/business-ads
- Auth: sí. Query: `category_id` (opcional).
- Éxito (200): `{"data": [{"id":1,"name":"string","category":{"id":1,"name":"string"}|null,"image_url":"string|null","description":"...","address":"...","phone":"...","email":"...","website":"...","published_at":"datetime|null","expires_at":"datetime|null"}]}`.

### GET /clubs/{club}/business-ads/{businessAd}
- Auth: sí. Éxito (200): `{"data": { ...mismo shape que un elemento del index... }}`.
- Error `404` si el anuncio no pertenece al club, no está publicado, o ya expiró.

---

## 9. Encuestas

### GET /clubs/{club}/surveys
- Auth: sí (requiere `Member` vinculado al club).
- Éxito (200): `{"data": [{"id":1,"title":"string","description":"string|null","questions_count":0}]}`.
- Solo encuestas `status = 'active'` que el socio **aún no ha respondido**.
- Errores: `404` `{"message": "Socio no encontrado."}`; `403` `{"message": "No tienes acceso a este club."}`.

### GET /clubs/{club}/surveys/{survey}
- Auth: sí. Éxito (200): `{"data": {"id":1,"title":"string","description":"string|null","questions":[{"id":1,"text":"string","type":"string","is_required":true,"config":{...},"options":[{"id":1,"text":"string"}]}]}}`.
- `type`/`config` son libres (definidos en el admin). `options` vacío para preguntas de texto libre.
- Errores: `404` socio/encuesta no encontrados; `403` encuesta no activa o sin acceso; `409` `{"message": "Ya respondiste esta encuesta."}`.

### POST /clubs/{club}/surveys/{survey}/responses
- Auth: sí. Body: `answers[]` — cada uno `{question_id, answer_text (opcional), answer_options (array opcional de ints)}`.
- Éxito (201): `{"message": "¡Gracias! Tus respuestas fueron registradas correctamente."}`.
- Es un envío **único y total** — no hay guardado parcial. `409` si ya se respondió.

---

## 10. Quejas y sugerencias (Feedback)

### GET /clubs/{club}/feedback/tickets
- Auth: sí. Query: `type`, `status` (códigos: `SUBMITTED`, `UNDER_REVIEW`, `IN_PROGRESS`, `RESOLVED`, `REJECTED`, `CLOSED`, `CANCELLED`).
- Éxito (200): `{"data": [{"id":1,"ticket_number":"FB-PE1-26-0001","title":"string","submitted_at":"...","resolved_at":"...","closed_at":"...","is_anonymous":true,"ticket_type":{...},"category":{...},"status":{"id":1,"name":"...","code":"...","color":"#hex"},"priority":{...}}]}`.

### POST /clubs/{club}/feedback/tickets
- Auth: sí. Body (multipart si hay adjuntos): `title` (max 85), `description` (max 350), `is_anonymous` (opcional), `attachments[]` (max 5 archivos, imágenes, max 2MB c/u), `ticket_type_id`, `category_id`, `priority_id`.
- Éxito (201): `{"message": "Ticket creado correctamente.", "data": { ...objeto Ticket sin relaciones... }}`.
- Nota: si la app necesita los nombres de tipo/categoría/status/prioridad, llamar `GET .../tickets/{id}` después.

### GET /clubs/{club}/feedback/tickets/{ticket}
- Auth: sí. Éxito (200): `{"data": { ...ticket con attachments[] y comments[] (solo públicos)... }}`.
- Error `404` `{"message": "Ticket no encontrado."}`.

### PATCH /clubs/{club}/feedback/tickets/{ticket}/cancel
- Auth: sí. Éxito (200): `{"message": "Ticket cancelado correctamente."}`.
- Error `422` `{"message": "Solo se puede cancelar cuando el ticket está ENVIADO."}` — mostrar opción de cancelar solo si `status.code === 'SUBMITTED'`.

---

## 11. Utilidades de desarrollo (no usar en producción)

- `POST /email/test` — envía un correo de prueba.
- `POST /firebase/test` — envía un push de prueba a un token FCM.
- `GET /firebase/ping` — verifica la conexión con Firebase.

---

## 12. Notas de seguridad y pendientes conocidos

1. **Historia clínica sin verificación de propiedad** — cualquier token puede leer/sobrescribir la historia de cualquier `member_id`. La app debe autolimitarse a `member_id` de la propia familia.
2. **Cancelar/eliminar reservación sin verificar dueño** — cualquiera con el `id` puede cancelar o borrar la reservación de otro socio. `DELETE` además es borrado físico sin reglas.
3. **`POST /business-ads` confía en `member_id`/`club_id` del body** sin verificar contra el token autenticado.
4. **`POST /charge-payment` sin idempotencia** — riesgo real de doble cobro en reintentos. La app debe deshabilitar el botón y/o verificar historial antes de reintentar.
5. **Webhook de Conekta sin verificación de firma criptográfica** — la mitigación es solo a nivel de red (IP allow-list en el panel de Conekta).
6. **Validación de casillero duplicado deshabilitada** — un integrante puede terminar con más de un casillero en el mismo año.
7. **Bug de validación en `POST /lockers/assign`**: mensajes personalizados usan `account_id.*` pero el campo real es `membership_account_id` — errores de ese campo salen en inglés.
8. **`GET /amenities/available-slots`**: errores de `date` inválida se reportan como `500` en vez de `422` — validar el formato del lado del cliente.
9. **Reglas de reservación leídas de variables globales**, no por club — los números en `rules` del `GET /amenities` pueden no coincidir con lo realmente aplicado.
