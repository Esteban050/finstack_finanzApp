# Postman — MOD-03 Gestión de ingresos

> Hay **dos colecciones** en esta carpeta. Esta primera parte documenta la de
> MOD-03; la de MOD-04 (gastos) está al final del archivo.

Verificación de los RF-020 a RF-024 por HTTP. **32 peticiones**, cada una con
aserciones referenciadas al criterio de aceptación o la regla de negocio que
comprueba.

| Archivo | Qué es |
| :---- | :---- |
| `SCFP-MOD-03.postman_collection.json` | La colección de MOD-03 (ingresos) |
| `SCFP-MOD-04.postman_collection.json` | La colección de MOD-04 (gastos) |
| `SCFP-Local.postman_environment.json` | Entorno local (URL y credenciales), común a las dos |

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
vuelve a sembrar con `php artisan db:seed --class="Database\Seeders\Test\Mod03DemoSeeder"`
y borra las fuentes sobrantes.

# Postman — MOD-04 Gestión de gastos

Verificación de los RF-025 a RF-029 por HTTP. **67 peticiones en 10 carpetas**,
cada una con aserciones referenciadas al criterio de aceptación o la regla de
negocio que comprueba.

⚠️ La numeración es la de los campos `ID`, no la de los títulos del documento de
requisitos. Ver `docs/spec/NUMERACION.md`.

## Antes de importar

**1. Crear la cuenta de demostración.** Es una cuenta distinta de la de MOD-03.

```bash
php artisan db:seed --class="Database\Seeders\Test\Mod04DemoSeeder"
```

Crea `mod04@scfp.test` en estado ACTIVA, con MFA desactivado y la política
vigente aceptada (RN-009.2), más 11 gastos repartidos en meses, medios de pago y
dimensiones, dos categorías propias de UUID fijo y un segundo usuario con un
gasto ajeno para las pruebas de aislamiento.

**2. Levantar el servidor**, igual que para MOD-03.

## Importar

Mismo procedimiento: **Import** → la colección y el entorno `SCFP · Local`.

**El entorno es el mismo para las dos colecciones.** La de MOD-04 usa las
variables `correo_mod04` y `password_mod04`, definidas en la propia colección, y
no `correo`/`password`: en Postman una variable de entorno tapa a la de la
colección, y el entorno fija `correo = mod03@scfp.test`. Con nombres propios las
dos colecciones conviven sin tocar el entorno.

## Ejecutar

**Usá el Collection Runner, en orden.** Las peticiones se pasan ids entre sí
(`cat_vivienda_id`, `sub_arriendo_id`, `gasto_id`, `plantilla_id`) y la carpeta
`10 · RF-027` es la que resuelve los ids del catálogo a partir de los nombres de
las categorías: si no corre primero, las carpetas 20 a 80 se quedan sin
`categoria_id`.

## Qué verifica cada carpeta

| Carpeta | Peticiones | Cubre |
| :---- | :---- | :---- |
| 00 · Autenticación | 3 | Login por el formulario real, token CSRF |
| 10 · RF-027 Categorías y subcategorías | 12 | RN-027.1, RN-027.2, RN-027.3, RN-027.4, RN-026.2, CA-027.2 |
| 20 · RF-025 Registro de gasto | 7 | RN-025.1, CA-025.1, RNG-09 |
| 30 · RF-026 Clasificación multidimensional | 12 | RN-026.1, RN-026.2, CA-026.1, CA-026.2, CA-026.3 |
| 40 · RN-025.2 Indicadores anuales | 3 | RN-025.2, CA-025.2, RN-026.3, RNG-08 |
| 50 · RF-029 Consulta y filtrado | 6 | RN-029.1, CA-029.1, RNG-10 |
| 60 · RF-029 Edición, eliminación y masiva | 8 | RN-029.1, RN-029.3, RNG-07 |
| 70 · RF-028 Gastos recurrentes | 8 | RN-028.1, RN-028.2, RN-028.3, CA-028.1, CA-022.2 |
| 80 · RF-027 Fusión de categorías | 4 | RN-027.2, CA-027.1 |
| 90 · RNG-01 Aislamiento de datos | 4 | RNG-01 |

**CA-028.1** (la suscripción mensual del día 5 genera un gasto cada mes) no se
puede comprobar por HTTP: la generación la hace el comando programado. La
colección verifica que la plantilla quede anclada al día 5 con la clasificación
configurada; la generación se verifica por consola:

```bash
php artisan scfp:generar-recurrencias
```

Ese comando genera ahora las ocurrencias de **ingresos y de gastos** (RN-022.2 y
RN-028.1) y reporta las dos cuentas por separado.

## Forma de las respuestas

Igual que en MOD-03: `mensaje` y, si hay algo que devolver, `data`. En los
listados `data` agrupa la colección y sus metadatos:

| Petición | Qué hay dentro de `data` |
| :---- | :---- |
| `GET /gastos` | `gastos`, `meta`, `totales`, `pendientes` |
| `GET /gastos/categorias` | `categorias` (árbol de dos niveles), `paleta`, `iconos`, `limites` |
| `GET /gastos/crear` | `categorias`, `dimensiones`, `etiquetasUsadas` |
| `GET /gastos/recurrentes` | `plantillas`, `pendientes`, `recordatorios` |
| `GET /gastos/indicadores` | los dos acumulados de RN-025.2, más `potencialmente_deducible` y `pendientes` |
| `GET /gastos/etiquetas` | la lista de etiquetas ya usadas |

`GET /gastos/indicadores` trae además un campo `aviso` al lado de `data`, con el
texto que exige RNG-08.

## Fechas: dos tipos de petición

Las fechas **pasadas** van fijas en el cuerpo (`2026-03-05` y similares): un gasto
no puede tener fecha futura (RN-025.1), así que una fecha de 2026 sirve para
siempre.

Las fechas **futuras** las calcula un script de pre-petición, para que la
colección no caduque:

| Variable | Qué es | Para qué |
| :---- | :---- | :---- |
| `fecha_manana` | hoy + 1 día | `20.4`, que comprueba el rechazo de una fecha futura |
| `fecha_dia_5` | el día 5 del mes siguiente | `70.1` y `70.5`, el anclaje al día 5 de CA-028.1 |
| `fecha_en_2_dias` | hoy + 2 días | `70.3`, para que la ocurrencia caiga en la ventana de recordatorio de RN-028.3 |

## Si algo falla

Valen las mismas causas que en MOD-03 (419, 302, 500 por vista faltante, 404 en
la carpeta de aislamiento). Además:

| Síntoma | Causa probable |
| :---- | :---- |
| **422** en toda la carpeta 20 o 30 | La carpeta `10` no corrió, así que `cat_vivienda_id` y compañía están vacías. Corré la colección completa desde el Runner. |
| **`80.1` dice que «Café» tiene 0 gastos** | Una corrida anterior ya fusionó la subcategoría. Volvé a sembrar con el seeder: repone «Café» y sus dos gastos. |
| **`40.3` no ve el delta de $ 200.000** | `40.1` no guardó la línea base. Las tres peticiones de la carpeta 40 van juntas y en orden. |
| **`50.5` falla el total del conjunto filtrado** | Necesita más de 10 gastos en 2026. El seeder deja 11; si la base se limpió, volvé a sembrar. |

## Ojo con los datos de la base

La colección **modifica datos** a propósito, porque varios criterios lo exigen:
la carpeta 10 crea y borra una categoría propia, la 60 elimina un gasto
(RNG-07) y la 80 hace desaparecer «Café» (CA-027.1). Es idempotente salvo en la
80. Para dejar la base como estaba:

```bash
php artisan db:seed --class="Database\Seeders\Test\Mod04DemoSeeder"
```

## Estado de verificación

Comprobado:

- El JSON está bien formado: 10 carpetas, 67 peticiones.
- Las 21 rutas existen (`php artisan route:list --path=gastos`).
- **Las 67 peticiones se ejecutaron contra la aplicación corriendo**, en orden y
  en una sola sesión, reproduciendo la cookie de sesión, el token CSRF por
  cabecera y el encadenamiento de ids: 86 comprobaciones, 0 fallos. Se verificó
  el código de estado que afirma cada `pm.test` y las aserciones numéricas de los
  criterios (los dos acumulados de CA-025.2 suben exactamente $ 200.000, la
  fusión de CA-027.1 reclasifica 2 gastos, «Arriendo» precarga FIJO/ESENCIAL en
  CA-026.1, los totales de CA-029.1 exceden la página).
- 120 pruebas automatizadas con 385 aserciones sobre los mismos endpoints
  (`composer test:dominio`, que corre MOD-03 y MOD-04: 215 pruebas en total).

Lo que **no** está comprobado es la corrida desde el Collection Runner de
Postman, que es lo único que ejecuta el JavaScript de los scripts `test` tal como
está escrito. El recorrido HTTP sí se verificó por completo.
