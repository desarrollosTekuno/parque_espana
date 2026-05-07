# Plan de trabajo para alta de modulos

## Objetivo

Definir un flujo estandar para registrar modulos nuevos en ParquesEspana, asegurando consistencia tecnica entre backend, frontend, permisos, datos iniciales y navegacion.

## Alcance

Aplica a modulos web (Inertia + Vue) y API (V1), en contextos de `admin` (club) y `superadmin`.

## Fases del plan

1. Definicion funcional
- Delimitar funcionalidades CRUD y reglas de negocio.
- Definir contexto (`AdminClub`, `Administrator`, etc.).
- Enumerar permisos requeridos por accion.

2. Diseno de datos
- Diseñar tabla(s), llaves foraneas e indices.
- Definir uso de `softDeletes` y convenciones de nombres.
- Si aplica schema SQL (ej. `feedback`), planear su creacion.

3. Implementacion de base de datos
- Crear migraciones en `database/migrations`.
- Incluir `CREATE SCHEMA IF NOT EXISTS ...` cuando aplique.
- Ejecutar y validar migraciones en ambiente local.

4. Modelado Eloquent
- Crear modelo(s) en `app/Models/<Dominio>/...`.
- Configurar `$table`, `fillable/guarded`, `casts` y relaciones.
- Incluir traits requeridos (ej. `SoftDeletes`).

5. Implementacion de controlador(es)
- Web: `app/Http/Controllers/Web/<Contexto>/<Modulo>Controller.php`.
- API: `app/Http/Controllers/Api/V1/<Modulo>Controller.php`.
- Definir middlewares de permisos, validaciones y manejo de errores.
- Estandarizar respuestas (`back()->with(...)` o JSON consistente).

6. Registro de rutas
- Web base en `routes/web.php` (si requiere entrypoint).
- Modulo club en `routes/adminclubs.php`.
- Modulo superadmin en `routes/administrator.php`.
- API en `routes/api.php`.
- Nombrar rutas para consumo Ziggy (`route('modulo.index')`).

7. Construccion de vista Inertia (Vue 3 + Vuetify)
- Crear `resources/js/Pages/<Contexto>/<Modulo>/Index.vue`.
- Basarse en `resources/js/Pages/Template.vue`.
- Implementar:
  - `Head` + `AppLayout`
  - `v-data-table-server` con busqueda, paginacion, orden
  - modal de alta/edicion
  - `useForm` con `post/put/delete`
  - toasts y confirmaciones (`customToastSwal`, `customConfirmSwal`)
  - permisos UI con `can.includes('permiso')`
  - acciones de tabla con iconos sin texto (`BaseButton action="edit|delete"`)

8. Permisos y datos iniciales
- Agregar permisos en `database/seeders/PermissionSeeder.php`.
- Asignarlos a roles en seeders de roles/contexto.
- Crear seeders de catalogos del modulo si son necesarios.
- Registrar nuevos seeders en `database/seeders/DatabaseSeeder.php`.

9. Integracion de navegacion
- Agregar acceso en menu/layout correspondiente.
- Mostrar opcion solo si el usuario tiene permiso.

10. Pruebas y validacion end-to-end
- Ejecutar `php artisan migrate`.
- Ejecutar `php artisan db:seed`.
- Validar flujo completo web/API: listar, crear, editar, eliminar.
- Validar control de acceso y manejo de errores.

11. Documentacion final
- Registrar rutas, permisos, payloads y reglas especiales.
- Documentar casos borde y decisiones tecnicas del modulo.

## Criterios de aceptacion

- El modulo aparece en la navegacion correcta segun permisos.
- El CRUD funciona con paginacion y filtros server-side.
- Permisos bloquean adecuadamente backend y frontend.
- Migraciones y seeders corren sin errores.
- Existe documentacion minima para mantenimiento.

## Entregables

- Migraciones y modelos del modulo.
- Controlador(es) web/API.
- Rutas registradas y nombradas.
- Vista `Index.vue` funcional basada en template del proyecto.
- Permisos y seeders configurados.
- Documentacion funcional actualizada.
