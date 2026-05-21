# Notificaciones en Tiempo Real (Bazvic)

Este documento explica, de punta a punta, como funciona el sistema de notificaciones en tiempo real en este proyecto para que otro agente de IA pueda replicarlo sin contexto adicional.

## 1) Resumen de arquitectura

Actualmente hay **dos mecanismos** de notificacion en paralelo:

1. **Socket.IO via servicio externo (principal en frontend actual)**
   - El backend dispara `NotificacionesSocketEvent`.
   - Ese evento hace un `POST` al servidor WebSocket externo (`WEBSOCKET_URL/emitir`).
   - El frontend escucha canales Socket.IO con prefijo de sistema: `bazvic.notification.*`.

2. **Broadcast nativo Laravel (Reverb/Pusher style)**
   - El backend dispara `NotificationEvent` que implementa `ShouldBroadcast`.
   - Emite en canales `notification.global` y `notification.{userId}`.
   - Este flujo convive con el anterior, pero no esta totalmente alineado con el listener Socket.IO actual.

## 2) Flujo completo (Socket.IO externo)

### 2.1 Inicializacion en frontend

- Archivo: `resources/js/app.js`
- En el `setup` de Inertia/Vue se ejecuta:
  - `WebsocketNotifications(app)`

Esto abre el cliente Socket.IO para toda la aplicacion una vez montada.

Codigo real (resumen de `resources/js/app.js`):

```js
createInertiaApp({
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(NotiflixPlugin)
            .use(GlobalComponents)
            .use(FormValidatePlugin)
            .use(ToastifyPlugin)

        app.provide('$loading', isLoading)

        app.mount(el)

        WebsocketNotifications(app)
    },
})
```

Punto importante de orden:

- `WebsocketNotifications(app)` se llama **despues** de `app.mount(el)`, cuando ya existen plugins/globalProperties (incluyendo `$toast`).

### 2.2 Cliente WebSocket en frontend

- Archivo: `resources/js/Utils/websocket.js`
- Funcion principal: `WebsocketNotifications(app, opts = {})`

Comportamiento:

- Lee `VITE_SOCKET_IO_URL` desde `import.meta.env`.
- Si no existe, no conecta y muestra warning.
- Define:
  - `sistema` (default: `'bazvic'`)
  - `userId` desde `opts.userId` o `window.userId`
- Conecta con `io(socketUrl)`.
- Escucha eventos:
  - `${sistema}.notification.global`
  - `${sistema}.notification.${userId}`
- Muestra toast usando `app.config.globalProperties.$toast`.

Tipos esperados en payload:

- `type = global` -> toast warning
- `type = individual` -> toast info

### 2.3 Origen del `window.userId`

- Archivo: `resources/views/app.blade.php`
- Se inyecta en layout global:

```html
<script>
    window.userId = @json(auth()->id());
</script>
```

Esto permite construir el canal individual en frontend.

### 2.4 Emision desde backend al servidor WebSocket externo

- Archivo: `app/Events/NotificacionesSocketEvent.php`

Funcionamiento interno:

1. Constructor recibe:
   - `message: string`
   - `userId: ?int`
   - `type: string` (`individual` por defecto)
2. Si es `individual` y trae `userId`, persiste en tabla `notificaciones`.
3. Llama `EmitirViaSocket()` inmediatamente.
4. `EmitirViaSocket()` hace `POST` a:
   - `config('services.websocket.url') . '/emitir'`
5. Payload enviado:
   - `message`
   - `user_id`
   - `type`
   - `sistema` (hardcode: `'bazvic'`)

Configuracion usada:

- Archivo: `config/services.php`
- Clave: `services.websocket.url` (lee `WEBSOCKET_URL`)

## 3) Persistencia de notificaciones

### 3.1 Tabla

- Migracion: `database/migrations/2024_12_04_103803_create_notificaciones_table.php`
- Tabla: `notificaciones`

Columnas relevantes:

- `id`
- `mensaje` (string)
- `visto` (boolean, default `false`)
- `user_id` (FK a `users.id`, cascade delete)
- `created_at`, `updated_at`, `deleted_at` (soft delete)

Codigo real de migracion (`database/migrations/2024_12_04_103803_create_notificaciones_table.php`):

```php
Schema::create('notificaciones', function (Blueprint $table) {
    $table->id();
    $table->string('mensaje');
    $table->boolean('visto')->default(false);

    $table->unsignedBigInteger('user_id');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

    $table->timestamps();
    $table->softDeletes();
});
```

Estructura SQL equivalente (aproximada):

```sql
CREATE TABLE notificaciones (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  mensaje VARCHAR(255) NOT NULL,
  visto BOOLEAN NOT NULL DEFAULT FALSE,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  CONSTRAINT notificaciones_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
);
```

### 3.2 Modelo

- Archivo: `app/Models/Notificaciones.php`
- Modelo: `Notificaciones`
- Traits:
  - `SoftDeletes`
  - `Auditable` (OwenIt)

Codigo real del modelo (`app/Models/Notificaciones.php`):

```php
class Notificaciones extends Model implements AuditableContract {
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
}
```

Campos funcionales usados por el flujo:

- Escritura: `user_id`, `mensaje`.
- Lectura pendientes: `user_id`, `visto`.
- Marcado leido: `visto = true`.

### 3.3 Escritura desde eventos (persistencia)

Fragmento real en `app/Events/NotificacionesSocketEvent.php`:

```php
if ($type === 'individual' && $userId !== null) {
    Notificaciones::create([
        'user_id' => $userId,
        'mensaje' => $message,
    ]);
}
```

Fragmento real en `app/Events/NotificationEvent.php`:

```php
if ($this->type == 'individual') {
    Notificaciones::create([
        'user_id' => $userId,
        'mensaje' => $message,
    ]);
}
```

## 4) Consulta y marcado de notificaciones (campana UI)

### 4.1 Endpoints

- Archivo: `routes/web.php`
- Rutas protegidas por `auth`:
  - `GET /Notificaciones/Pendientes` -> `NotificacionesController@ObtieneNotificacionesPendientes`
  - `POST /Notificaciones/MarcarLeida` -> `NotificacionesController@ActualizarEstatusNotificacion`

### 4.2 Controlador

- Archivo: `app/Http/Controllers/Administrador/NotificacionesController.php`

Metodos:

- `ObtieneNotificacionesPendientes()`
  - Usa `Auth::id()`.
  - Filtra `notificaciones` por `user_id` y `visto = false`.
  - Regresa JSON.
- `ActualizarEstatusNotificacion(Request)`
  - Recibe `notification_id`.
  - Marca `visto = true`.

### 4.3 Componente TopBar

- Archivo: `resources/js/Components/TopBar.vue`

Flujo:

1. Obtiene `userId` desde `usePage().props.auth.user.id`.
2. En `onMounted`, llama `ObtenerNotificaciones()`.
3. `ObtenerNotificaciones()` hace GET a `Notificaciones.Pendientes` y pinta lista local.
4. `MarcarComoVisto(notificationId)` hace POST a `Notificaciones.MarcarLeida` y elimina item localmente.

Nota importante:

- La campana **no agrega automaticamente** al arreglo local cuando llega un evento WebSocket; solo muestra toast en tiempo real y la lista se refresca al recargar o volver a consultar.

### 4.4 Dropdown visual

- Archivo: `resources/js/Components/Notificaciones.vue`
- Renderiza lista pendiente y emite `mark-read` al padre.

## 5) Envio manual desde panel de Administrador

### 5.1 Rutas

- Archivo: `routes/Administrador.php`
- `Route::resource('/Notificaciones', NotificacionesWebSocketController::class)`

En runtime queda bajo prefijo:

- `/Administrador/Notificaciones`

(definido en `bootstrap/app.php` por `Route::middleware(['web','auth'])->prefix('Administrador')`)

### 5.2 Controlador de envio

- Archivo: `app/Http/Controllers/Administrador/NotificacionesWebSocketController.php`

`store(Request)` valida:

- `CanalWebSocket` (boolean)
- `MensajeNotificacion` (string <= 255)
- `IdUsuarioDestino` (nullable integer)

Luego:

- Si `CanalWebSocket = true` -> envia global
- Si `false` -> envia individual

Ambos casos usan `broadcast(new NotificacionesSocketEvent(...))`.

### 5.3 Vista admin

- Archivo: `resources/js/Pages/Administrador/Notificaciones.vue`
- Formulario Inertia para seleccionar global/individual y texto.

## 6) Segundo mecanismo: `NotificationEvent` (Laravel Broadcast)

- Archivo: `app/Events/NotificationEvent.php`
- Implementa `ShouldBroadcast`.

Comportamiento:

- Si `type = global` -> canal `notification.global`
- Si `type = individual` -> canal `notification.{userId}`
- `broadcastWith()` envia `message`, `type`, `user_id`.
- `broadcastAs()` devuelve `notification.event`.
- Tambien persiste en `notificaciones` cuando es individual.

Este evento se usa en varios procesos de RH y comandos (por ejemplo vacaciones/licencias), pero su convención de canal no coincide exactamente con `${sistema}.notification.*`.

## 7) Variables de entorno clave

Archivo: `.env`

- `BROADCAST_CONNECTION=reverb`
- `VITE_SOCKET_IO_URL=wss://websocket.tekuno.mx`
- `WEBSOCKET_URL=https://websocket.tekuno.mx`
- `VITE_SISTEMA=BazvicLocal` (actualmente no se utiliza en `websocket.js`)

Relacion con configuraciones:

- `WEBSOCKET_URL` -> `config/services.php` -> `services.websocket.url`
- `BROADCAST_CONNECTION` -> `config/broadcasting.php`

## 8) Eventos y puntos de emision detectados

### 8.1 `NotificacionesSocketEvent`

Usado, entre otros, en:

- `app/Http/Controllers/Administrador/NotificacionesWebSocketController.php`
- `app/Http/Controllers/RecursosHumanos/PrestamosController.php`
- `app/Http/Controllers/RecursosHumanos/VacantesAspirantesController.php`

### 8.2 `NotificationEvent`

Usado, entre otros, en:

- `app/Http/Controllers/RecursosHumanos/VacacionesController.php`
- `app/Http/Controllers/RecursosHumanos/AutorizaVacacionesController.php`
- `app/Http/Controllers/RecursosHumanos/AutorizaVacacionesRHController.php`
- `app/Console/Commands/VerificaLicenciasSofware.php`

## 9) Logging y observabilidad

Archivo: `config/logging.php`

Canales utiles:

- `Notificaciones` -> `storage/logs/NotificacionesLogs.log`
- `Vacaciones` -> `storage/logs/VacacionesLogs.log`
- `Tasks` -> `storage/logs/ScheduleLogs.log`

`NotificacionesSocketEvent` registra:

- nueva notificacion,
- URL de emision,
- respuesta del servidor,
- errores de emision.

## 10) Riesgos / detalles tecnicos a considerar al replicar

1. Hay dualidad de mecanismos (Socket externo vs Broadcast Laravel).
2. Los nombres de canal no son equivalentes entre ambos flujos.
3. `websocket.js` usa sistema hardcode `'bazvic'`, no `VITE_SISTEMA`.
4. En algunos lugares se envia `empleado_id` como `userId`; validar que corresponda a `users.id`.
5. Si solo quieres tiempo real visible en campana, faltaria sincronizar lista local al recibir socket.

## 11) Guia de replicacion para otro agente IA

Si el objetivo es clonar el comportamiento actual sin rediseñar:

1. Crear tabla `notificaciones` y modelo con `visto` + `user_id`.
2. Inyectar `window.userId` en layout global autenticado.
3. Inicializar Socket.IO al arrancar app y escuchar:
   - `{sistema}.notification.global`
   - `{sistema}.notification.{userId}`
4. Crear evento backend equivalente a `NotificacionesSocketEvent` que:
   - persista individuales,
   - haga POST a `/emitir` del servidor websocket externo con `sistema`.
5. Exponer endpoints para:
   - listar pendientes por usuario,
   - marcar notificacion como vista.
6. Crear UI de campana con contador + marcar visto.
7. (Opcional) Mantener `NotificationEvent` para broadcast Laravel heredado.

## 12) Archivos clave (indice rapido)

- Front principal: `resources/js/app.js`
- Cliente socket: `resources/js/Utils/websocket.js`
- Inyeccion userId: `resources/views/app.blade.php`
- Evento Socket externo: `app/Events/NotificacionesSocketEvent.php`
- Evento Broadcast Laravel: `app/Events/NotificationEvent.php`
- Modelo: `app/Models/Notificaciones.php`
- Migracion: `database/migrations/2024_12_04_103803_create_notificaciones_table.php`
- API pendientes/marcado: `app/Http/Controllers/Administrador/NotificacionesController.php`
- Vista campana: `resources/js/Components/TopBar.vue`
- Dropdown campana: `resources/js/Components/Notificaciones.vue`
- Envio admin: `app/Http/Controllers/Administrador/NotificacionesWebSocketController.php`
- Rutas web: `routes/web.php`
- Rutas admin: `routes/Administrador.php`
- Prefijos globales rutas: `bootstrap/app.php`
- Config websocket URL: `config/services.php`
- Config broadcast: `config/broadcasting.php`
- Config logs: `config/logging.php`

## 13) Secuencia end-to-end (runbook para IA)

### 13.1 Notificacion individual via Socket.IO externo

```text
[Backend - modulo de negocio]
  broadcast(new NotificacionesSocketEvent(message, userId, 'individual'))
        |
        |-- (A) Guarda en DB: tabla notificaciones
        |       campos: user_id, mensaje, visto=false
        |
        |-- (B) POST https://<WEBSOCKET_URL>/emitir
                payload: { message, user_id, type:'individual', sistema:'bazvic' }
                        |
                        v
             [Servidor websocket externo]
               emite evento: bazvic.notification.{userId}
                        |
                        v
             [Frontend - WebsocketNotifications]
               socket.on('bazvic.notification.{userId}')
               -> muestra toast info
                        |
                        v
             [Campana UI]
               no se sincroniza en vivo por socket
               (se actualiza al consultar pendientes)
```

### 13.2 Notificacion global via Socket.IO externo

```text
[Backend admin]
  broadcast(new NotificacionesSocketEvent(message, null, 'global'))
        |
        |-- (A) NO guarda en DB (global no persiste en este evento)
        |
        |-- (B) POST https://<WEBSOCKET_URL>/emitir
                payload: { message, user_id:null, type:'global', sistema:'bazvic' }
                        |
                        v
             [Servidor websocket externo]
               emite evento: bazvic.notification.global
                        |
                        v
             [Frontend - WebsocketNotifications]
               socket.on('bazvic.notification.global')
               -> muestra toast warning
```

### 13.3 Carga de campana (pendientes en BD)

```text
[TopBar.vue onMounted]
  GET route('Notificaciones.Pendientes')
        |
        v
[NotificacionesController@ObtieneNotificacionesPendientes]
  SELECT * FROM notificaciones
  WHERE user_id = Auth::id() AND visto = false
        |
        v
[Frontend]
  pinta lista + contador en campana
```

### 13.4 Marcar notificacion como vista

```text
[Usuario en campana]
  click "Marcar como visto"
        |
        v
POST route('Notificaciones.MarcarLeida')
  body: { notification_id }
        |
        v
[NotificacionesController@ActualizarEstatusNotificacion]
  UPDATE notificaciones SET visto = true WHERE id = notification_id
        |
        v
[Frontend]
  remueve item local del arreglo
```

## 14) Checklist de replicacion tecnica

1. Crear la migracion `notificaciones` con `mensaje`, `visto`, `user_id`, soft deletes.
2. Crear modelo `Notificaciones` con `SoftDeletes` y guardado masivo habilitado.
3. Configurar `.env`:
   - `VITE_SOCKET_IO_URL`
   - `WEBSOCKET_URL`
   - `BROADCAST_CONNECTION` (si se usara flujo Laravel broadcast tambien)
4. En layout global, inyectar `window.userId = auth()->id()`.
5. En arranque de app (`app.js`), llamar `WebsocketNotifications(app)`.
6. En listener socket, suscribirse a:
   - `{sistema}.notification.global`
   - `{sistema}.notification.{userId}`
7. Implementar evento backend tipo `NotificacionesSocketEvent`:
   - persistir individuales,
   - emitir HTTP a `/emitir` con `sistema`.
8. Crear endpoints:
   - obtener pendientes por usuario autenticado,
   - marcar como vista por `notification_id`.
9. Implementar campana UI con contador + lista + accion marcar visto.
10. Verificar logs de emision en canal `Notificaciones`.

## 15) Pruebas minimas de validacion

### Caso A: individual

1. Enviar notificacion individual desde panel admin.
2. Verificar:
   - existe registro en `notificaciones` con `visto = false`.
   - usuario destino recibe toast en tiempo real.
   - campana lista la notificacion al consultar pendientes.

### Caso B: global

1. Enviar notificacion global desde panel admin.
2. Verificar:
   - no se inserta fila en `notificaciones` (segun implementacion actual).
   - usuarios conectados reciben toast global.

### Caso C: marcar visto

1. En campana, marcar una notificacion como vista.
2. Verificar:
   - `visto = true` en BD.
   - desaparece de la lista local.

---

Si se va a unificar en una sola estrategia, este documento sirve como baseline para identificar que piezas dependen del flujo `Socket.IO externo` y cuales del flujo `ShouldBroadcast`.
