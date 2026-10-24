# Postman — MOD-03 Gestión de ingresos

Verificación de los RF-020 a RF-024 por HTTP. **32 peticiones**, cada una con
aserciones referenciadas al criterio de aceptación o la regla de negocio que
comprueba.

| Archivo | Qué es |
| :---- | :---- |
| `SCFP-MOD-03.postman_collection.json` | La colección |
| `SCFP-Local.postman_environment.json` | Entorno local (URL y credenciales) |

## Antes de importar

**1. Crear la cuenta de demostración.** Sin esto el login falla: los usuarios que
hay en la base están en estado `PENDIENTE_VERIFICACION` y RN-003.1 impide que
inicien sesión.

```bash
php artisan db:seed --class="Database\Seeders\Test\Mod03DemoSeeder"
```

Crea `mod03@scfp.test` en estado ACTIVA, con MFA desactivado y la política vigente
aceptada (RN-009.2), más un segundo usuario con un ingreso de UUID fijo para las
pruebas de aislamiento.

**2. Levantar el servidor.**

```bash
php artisan serve
```

Si usás Herd, el proyecto responde en su dominio `.test` y hay que ajustar
`base_url` en el entorno.

## Importar

1. En Postman: **Import** → arrastrá los **dos** archivos `.json` de esta carpeta.
2. Arriba a la derecha, seleccioná el entorno **SCFP · Local**. Si queda en "No
   Environment", las peticiones apuntarán a una URL vacía y fallarán.
3. Verificá que `base_url` coincida con donde está corriendo la app.

## Ejecutar

**Usá el Collection Runner, no las peticiones una por una.** El orden importa: las
peticiones se pasan ids entre sí (`fuente_id`, `ingreso_id`, `plantilla_id`) y la
sesión se establece en la primera carpeta.

1. Clic derecho en la colección → **Run collection**.
2. Dejá todas las peticiones marcadas y en el orden en que aparecen.
3. **Run**.

Al terminar, el panel de resultados muestra cada aserción con el código del
criterio que verifica.

### Si preferís ir carpeta por carpeta

Ejecutá siempre **`00 · Autenticación` primero** en la sesión de Postman. Las
demás carpetas dependen de la cookie de sesión y del token CSRF que esa deja
guardados. `10 · RF-020` debe correr antes de `20`, `30` y `40`, porque es la que
resuelve `fuente_id`.

## Qué verifica cada carpeta

| Carpeta | Cubre |
| :---- | :---- |
| 00 · Autenticación | Login por el formulario real, token CSRF |
| 10 · RF-020 Fuentes de ingreso | CA-020.1, CA-020.2, RN-020.1, RN-020.3 |
| 20 · RF-021 Registro de ingreso | CA-021.1, CA-021.2, CA-021.3, RN-021.3, RN-021.4 |
| 30 · RF-024 Consulta y filtrado | CA-024.1, CA-024.2, RN-024.1, RN-024.3, RNG-10 |
| 40 · RF-023 Edición y eliminación | RN-023.1, RN-023.2, RNG-07 |
| 50 · RF-022 Ingresos recurrentes | RN-022.1, RN-022.3, CA-022.2 |
| 60 · RNG-01 Aislamiento de datos | RNG-01, y de paso CA-018.2 |

**CA-022.1** (mensual desde el 30/01/2026 genera la ocurrencia de febrero el
28/02/2026) no se puede comprobar por HTTP: la generación la hace el comando
programado. Se verifica por consola:

```bash
php artisan scfp:generar-recurrencias
```

## Cómo funciona la autenticación

El SCFP usa sesión y CSRF (grupo `web`), no tokens de API: no tiene
`laravel/sanctum` ni rutas en `routes/api.php`. Las mismas rutas sirven al
navegador y a Postman, porque los controladores **negocian el formato**: devuelven
JSON cuando la petición trae `Accept: application/json`, y vista Blade si no.

La carpeta `00` resuelve la sesión sola, en tres pasos:

1. `GET /login` — lee el token del `<input name="_token">` del formulario.
2. `POST /login` — autentica con `correo` y `password`.
3. `GET /dashboard` — **vuelve a leer** el token, ahora del
   `<meta name="csrf-token">`. Hace falta porque RN-003.3 regenera la sesión al
   autenticar, y eso rota el token: el de la primera petición ya no sirve.

Las demás peticiones envían el token en la cabecera `X-CSRF-TOKEN`. Postman
administra la cookie de sesión por su cuenta.

## Forma de las respuestas

Una respuesta correcta trae `mensaje` y, si hay algo que devolver, `data`. El
código de estado viaja en el HTTP, no en el cuerpo:

```json
{ "mensaje": "Fuente de ingreso creada exitosamente.", "data": { "id": "…" } }
```

En los listados `data` es la colección, con dos excepciones donde agrupa la
colección y sus metadatos:

| Petición | Qué hay dentro de `data` |
| :---- | :---- |
| `GET /ingresos/fuentes` | la colección de fuentes |
| `GET /ingresos` | `ingresos`, `meta`, `totales`, `pendientes` |
| `GET /ingresos/recurrentes` | `plantillas`, `pendientes` |

Los **errores conservan la forma de Laravel**, `message` y `errors`, porque los
arma el manejador de excepciones del framework y no los controladores. Por eso
las peticiones que esperan 422 leen `errors.<campo>[0]` y no `data`.

Los mensajes de validación están escritos en el código, en los arrays
`mensajes()` de cada controlador: el proyecto no tiene archivos de traducción
(`lang/` no existe) y `APP_LOCALE=es` con `APP_FALLBACK_LOCALE=es`, así que una
regla sin mensaje propio saldría como la clave cruda `validation.required`.

## Si algo falla

| Síntoma | Causa probable |
| :---- | :---- |
| **419** Page Expired | El token CSRF no llegó. Corré `00 · Autenticación` otra vez; con el Runner esto no debería pasar. |
| **302** hacia `/politica-datos` | La cuenta no tiene aceptada la versión vigente de la política (RN-009.2). Volvé a correr el seeder. |
| **302** hacia `/login` | Se perdió la sesión. Revisá que Postman tenga las cookies habilitadas para `base_url`. |
| **500** en una petición `GET` | Falta la vista Blade. Las rutas web de MOD-03 todavía no tienen frontend: todas las peticiones de la colección mandan `Accept: application/json` justamente para evitarlo. Si una falla así, le falta esa cabecera. |
| **404** en la carpeta 60 | Eso es lo correcto. RNG-01 exige responder "recurso no encontrado" ante un registro ajeno. |
| **422** en varias peticiones | Varias están diseñadas para recibir 422: son las que comprueban que una regla rechaza un dato inválido (CA-020.1, CA-021.2, CA-021.3). Mirá la aserción, no el código. |
| Todo falla con URL inválida | No hay entorno seleccionado, o `base_url` apunta a otro puerto. |

## Estado de verificación

Comprobado:

- El JSON está bien formado: 7 carpetas, 32 peticiones.
- Las 17 rutas existen (`php artisan route:list --path=ingresos`).
- Los endpoints responden los códigos y payloads que la colección afirma: 92
  pruebas automatizadas con 250 aserciones, todas en verde
  (`composer test:dominio`).
- El recorrido por HTTP real —cookie de sesión, token CSRF por cabecera y
  encadenamiento de ids— se ejercitó con `curl` reproduciendo las carpetas 00,
  10, 20 y 50, más `40.2`/`40.3` y `50.4`/`50.5`. Las dos expresiones regulares
  que extraen el token calzan con el HTML real: `csrf_field()` produce
  `<input type="hidden" name="_token" value="…" autocomplete="off">` y el layout
  `layouts/app.blade.php` produce `<meta name="csrf-token" content="…">`.
- RF-020 completo: 17 comprobaciones sobre fuentes de ingreso, incluidas las de
  aislamiento (RNG-01 responde 404 y lo registra en bitácora) y el acceso sin
  sesión (401 con `Accept: application/json`, 302 a `/login` desde navegador).

Lo que **no** está comprobado es la corrida completa desde el Collection Runner
de Postman, con las 32 peticiones en orden y las variables encadenándose entre
carpetas. Eso es justo lo que resuelve correr la colección.

### Ojo con los datos de la base

Las peticiones `10.2` y `10.3` crean y luego comprueban el duplicado de
`Local comercial Cra 7 - arriendo`. Si una corrida anterior quedó a medias y esa
fuente sobrevivió, `10.2` devolverá 422 en vez de 201 y `10.3` dejará de probar
lo que dice probar: la unicidad es por usuario e ignora mayúsculas
(`uq_fuentes_ingreso_usuario_nombre` es sobre `lower(nombre)`). Ante dudas,
vuelve a sembrar con `php artisan db:seed --class=Mod03DemoSeeder` y borra las
fuentes sobrantes.
