# AGENTS - Guia para registrar un modulo nuevo

Este proyecto usa Laravel + Inertia + Vue 3 + Vuetify. La forma correcta de dar de alta un modulo nuevo es seguir una estructura consistente para backend, frontend, permisos y datos iniciales.

## 1) Estructura general del proyecto

- Controladores web: `app/Http/Controllers/Web/...`
- Controladores API: `app/Http/Controllers/Api/V1/...`
- Modelos: `app/Models/...` (por dominio)
- Rutas:
  - Web principal: `routes/web.php`
  - Modulos admin club: `routes/adminclubs.php`
  - Modulos superadmin: `routes/administrator.php`
  - API: `routes/api.php`
- Migraciones: `database/migrations/*.php`
- Seeders: `database/seeders/*.php`
- Vistas Inertia (Vue): `resources/js/Pages/...`
- Layout base: `resources/js/Layouts/AppLayout.vue`
- Template de referencia: `resources/js/Pages/Template.vue`

## 2) Como registrar un modulo nuevo (checklist)

1. Definir dominio y namespace
- Elegir contexto: `AdminClub`, `Administrator`, `Feedback`, `Billing`, etc.
- Mantener nombres consistentes entre tabla, modelo, controlador, rutas y vista.

2. Crear migracion(es)
- Crear archivo con timestamp en `database/migrations`.
- Si se usan esquemas (ej. `feedback.categories`), asegurar `CREATE SCHEMA IF NOT EXISTS ...` antes de `Schema::create`.
- Incluir: llaves foraneas, indices, `timestamps`, y `softDeletes` cuando aplique.
- Ejemplo real: `database/migrations/2026_04_23_232227_create_feedback_categories_table.php`.

3. Crear modelo
- Ubicarlo por dominio, por ejemplo: `app/Models/Feedback/Category.php`.
- Definir `protected $table` cuando la tabla no sea convencional o tenga schema.
- Definir `guarded`/`fillable`, `casts`, relaciones y traits (`SoftDeletes`, etc.).

4. Crear controlador
- Web: `app/Http/Controllers/Web/<Contexto>/<Modulo>Controller.php`
- API: `app/Http/Controllers/Api/V1/<Modulo>Controller.php`
- Registrar middleware de permisos en `__construct()`.
- Implementar validaciones, manejo de excepciones, respuestas consistentes y paginacion.
- Para vistas web, retornar `Inertia::render('Ruta/De/Vista', [...props])`.

5. Registrar rutas
- Si es modulo de club: editar `routes/adminclubs.php`.
- Si es superadmin: editar `routes/administrator.php`.
- Si es API: editar `routes/api.php`.
- Usar `Route::resource` cuando sea CRUD estandar y nombrar rutas con `->names('modulo')`.
- En frontend se consumen con Ziggy via `route('nombre.ruta')`.

6. Crear vista Vue (Inertia)
- Ubicar segun contexto, por ejemplo:
  - `resources/js/Pages/AdminClubs/<Modulo>/Index.vue`
  - `resources/js/Pages/Administrator/<Modulo>/Index.vue`
- Basarse en `resources/js/Pages/Template.vue` para:
  - `Head` + `AppLayout`
  - Tabla server-side (`v-data-table-server`)
  - Busqueda, paginacion, ordenamiento
  - Modal de alta/edicion
  - `useForm` con `form.post/put/delete`
  - Alertas con `customToastSwal` y confirmaciones con `customConfirmSwal`
- Control de permisos en UI con `can.includes('permiso')`.
- En tablas, mantener acciones con iconos (sin texto) para `edit/delete`, siguiendo el patron existente con `BaseButton action="edit|delete"`.

7. Crear permisos y seeders
- Definir permisos del modulo (index, store, update, destroy, extras) en `PermissionSeeder.php`.
- Asignar permisos a roles donde corresponda (`RoleSeeder` o seeders relacionados).
- Si hay catalogos iniciales del modulo, crear seeder dedicado y enlazar en `DatabaseSeeder.php`.

8. Integrar en navegacion (si aplica)
- Agregar opcion en el menu/layout correspondiente para llegar a la vista del modulo.
- Validar que el item solo aparezca cuando el usuario tenga permisos.

9. Probar punta a punta
- Migraciones: `php artisan migrate`
- Seeders: `php artisan db:seed`
- Revisar flujo web/API completo: listar, crear, editar, eliminar, permisos, errores.

## 3) Estructura recomendada de controlador

Para modulos CRUD web, se recomienda:

- `index(Request $request)`
  - filtros (`search`), orden y paginacion
  - retorna `Inertia::render(..., ['items' => $items])`
- `store(Request $request)`
  - valida
  - crea
  - `return back()->with('success', '...')`
- `update(Request $request, $id)`
  - valida
  - actualiza
  - `return back()->with('success', '...')`
- `destroy($id)`
  - elimina (soft delete si aplica)
  - `return back()->with('success', '...')`

Siempre envolver operaciones de riesgo en `try/catch`, usar `report($e)` y retornar errores controlados.

## 4) Estructura recomendada de vista Template (Inertia + Vue)

Basada en `resources/js/Pages/Template.vue`:

- `<script setup lang="ts">`
  - imports: `Head`, `router`, `useForm`, `usePage`, `AppLayout`
  - interfaces `Props` y `Form`
  - `const can = usePage().props.auth.permissions`
  - estado reactivo (`items`, `total`, `loading`, `search`, `options`, `showModal`)
  - metodos `fetchItems`, `create`, `edit`, `save`, `destroy`, `close`
  - `watch([options, search], debounce(fetchItems, 400), { deep: true })`

- `<template>`
  - `<Head title="..." />`
  - `<AppLayout>` con slots `header` y `options`
  - `v-data-table-server` para listado
  - `v-dialog` + `v-form` para crear/editar
  - botones condicionados por permisos
  - en columnas de acciones usar botones de icono sin texto

## 5) Convenciones de nombres

- Modelo: singular en PascalCase (`FeedbackCategory` o `Category` dentro de namespace `Feedback`).
- Controlador: `<Modulo>Controller`.
- Vista: `Index.vue` para listado CRUD inicial.
- Ruta nombre: kebab-case (`feedback-categories.index`).
- Tabla: plural y consistente; si hay schema, usar `schema.tabla`.

## 6) Comandos utiles

- Crear modelo + migracion:
  - `php artisan make:model Feedback/Category -m`
- Crear controlador resource web:
  - `php artisan make:controller Web/AdminClub/FeedbackCategoryController --resource`
- Crear controlador API:
  - `php artisan make:controller Api/V1/FeedbackCategoryController`

## 7) Plan de trabajo recomendado para un modulo nuevo

1. Definir alcance funcional y permisos requeridos.
2. Diseñar tablas/relaciones y crear migraciones.
3. Crear modelos con relaciones y casts.
4. Implementar controlador(es) web/API con validaciones.
5. Registrar rutas y nombres de ruta.
6. Construir vista `Index.vue` basada en Template.
7. Configurar permisos y seeders.
8. Integrar menu y reglas de visibilidad por permisos.
9. Ejecutar migracion/seeding y pruebas funcionales.
10. Documentar modulo (rutas, permisos, payloads y casos borde).

## 8) Documento para incorporar en PDF

Se genero una version lista para pegar en el documento funcional en:

- `Docs/Plan_trabajo_ParqueEspaña.md`

Ese contenido esta preparado para insertarse en `Docs/ParqueEspaña.pdf` como seccion "Plan de trabajo para alta de modulos".
