# Postman — MOD-03 Gestión de ingresos

> Hay **cuatro colecciones** en esta carpeta. Esta primera parte documenta la de
> MOD-03; las de MOD-04 (gastos), RF-017 (TRM) y MOD-05 (seguridad social) están
> al final del archivo.

Verificación de los RF-020 a RF-024 por HTTP. **33 peticiones**, cada una con
aserciones referenciadas al criterio de aceptación o la regla de negocio que
comprueba.

| Archivo | Qué es |
| :---- | :---- |
| `SCFP-MOD-03.postman_collection.json` | La colección de MOD-03 (ingresos) |
| `SCFP-MOD-04.postman_collection.json` | La colección de MOD-04 (gastos) |
| `SCFP-TRM.postman_collection.json` | La colección de RF-017 (TRM y conversión COP ⇄ USD) |
| `SCFP-MOD-05.postman_collection.json` | La colección de MOD-05 (seguridad social) |
| `SCFP-Local.postman_environment.json` | Entorno local (URL y credenciales) de MOD-03; las demás colecciones traen sus propias variables |

> **Sesión de una corrida anterior (las cuatro colecciones).** La app de Postman
> guarda las cookies entre corridas, y `/login` está bajo el middleware `guest`:
> si quedó abierta la sesión de otra cuenta (por ejemplo, la cuenta sin perfil
> con la que termina MOD-05, o el Administrador con el que termina TRM), el login
> de la carpeta 00 redirigía al dashboard sin cambiar de cuenta y la colección
> corría con la cuenta equivocada (en MOD-05, 403 desde la 10.1). Desde el
> 28/10/2026 la 00.1 de cada colección trae un script previo que cierra esa sesión
> antes del login (solo si la página es autenticada, es decir, trae el formulario
> de "Cerrar sesión") y no sigue redirecciones: si no logra cerrarla, falla ahí
> con "Formulario de login disponible". Ya no hace falta borrar las cookies a
> mano. Newman cuenta como peticiones las que hace ese script (una o dos más que
> las de la colección); las aserciones no cambian.

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
| 20 · RF-021 Registro de ingreso | CA-021.1, CA-021.2, CA-021.3, RN-021.3, RN-021.4, CA-017.1 (20.7: USD sin tasa usa la TRM) |
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

- El JSON está bien formado: 7 carpetas, 33 peticiones. La `20.7` (CA-017.1) se
  agregó con RF-017 y se ejecutó contra la app corriendo: el ingreso de USD 1.200
  del 20/04/2026 quedó con la TRM oficial de esa fecha (3.593,14) y su valor en
  COP exacto.
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

Verificación de los RF-025 a RF-029 por HTTP. **68 peticiones en 10 carpetas**,
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
| 20 · RF-025 Registro de gasto | 8 | RN-025.1, CA-025.1, RNG-09, CA-017.1 (20.8: USD sin tasa usa la TRM) |
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

- El JSON está bien formado: 10 carpetas, 68 peticiones. La `20.8` (CA-017.1) se
  agregó con RF-017 y se ejecutó contra la app corriendo: el gasto de USD 120 del
  06/03/2026 quedó con la TRM oficial de esa fecha (3.767,94). El resto del
  recorrido de abajo es el de la versión de 67 peticiones.
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

# Postman — RF-017 TRM y conversión COP ⇄ USD

Verificación de RF-017 por HTTP: consulta de la TRM, conversión, administración
manual y sincronización con la fuente oficial. **25 peticiones en 7 carpetas.**
El registro de ingresos y gastos en USD sin tasa (CA-017.1) se verifica en las
colecciones de MOD-03 (`20.7`) y MOD-04 (`20.8`).

La TRM oficial sale de los datos abiertos de la Superintendencia Financiera
(`https://www.datos.gov.co/resource/32sa-8pi3.json`). Es pública y no necesita
clave: no hay que tocar el `.env`.

## Antes de importar

**1. Crear la tabla `tasas_cambio`, una sola vez.** Va con `--path` a propósito:
la base no tiene tabla `migrations` (la crea esta misma orden), así que un
`php artisan migrate` a secas intentaría correr también las migraciones base de
Laravel.

```bash
php artisan migrate --path=database/migrations/2026_10_27_000001_create_tasas_cambio_table.php
```

**2. Crear las cuentas de demostración.**

```bash
php artisan db:seed --class="Database\Seeders\Test\TrmDemoSeeder"
```

Crea `trm@scfp.test` (Usuario) y `trm.admin@scfp.test` (Administrador), las dos
ACTIVA, sin MFA y con la política vigente aceptada. No siembra ninguna TRM: la
colección la obtiene de la fuente oficial o la registra a mano.

## Ejecutar

**Con el Collection Runner, en orden.** La colección usa sus propias variables
(`correo_trm`, `correo_trm_admin`, `password_trm`), así que convive con el
entorno `SCFP · Local` sin tocarlo.

La carpeta `40` **cierra la sesión del Usuario y entra como Administrador**,
porque RN-017.3 es del Administrador. Las carpetas `50` y `60` dependen de ella.

## Qué verifica cada carpeta

| Carpeta | Peticiones | Cubre |
| :---- | :---- | :---- |
| 00 · Autenticación (Usuario) | 3 | Login por el formulario real, token CSRF |
| 10 · RF-017 Consulta de la TRM | 3 | RF-017 (valor, vigencia, fuente, última actualización), RN-017.2, RNG-05 |
| 20 · RF-017 Conversión COP ⇄ USD | 5 | CA-017.1, RN-017.4, RNG-02, RNG-09 |
| 30 · CA-003.2 Acceso a la administración | 2 | CA-003.2 (403 para el Usuario) |
| 40 · Cambio a la sesión del Administrador | 4 | RF-006 (logout), login del Administrador |
| 50 · RN-017.3 Registro y corrección manual | 7 | RN-017.3, CA-017.2, RNG-10 |
| 60 · RN-017.1 Sincronización | 1 | RN-017.1 |

## Forma de las respuestas

| Petición | Qué hay dentro de `data` |
| :---- | :---- |
| `GET /tasas-cambio/trm?fecha=` | `moneda`, `valor`, `fecha_consultada`, `vigencia_desde`, `vigencia_hasta`, `es_tasa_anterior`, `fuente`, `ultima_actualizacion` |
| `GET /tasas-cambio/convertir?monto=&de=&a=&fecha=` | `monto`, `de`, `a`, `resultado` y la `trm` usada, con la forma de arriba |
| `GET /admin/trm` | `tasas`, `meta` |
| `POST /admin/trm` | la TRM registrada (201) o corregida (200) |
| `POST /admin/trm/sincronizar` | la TRM vigente hoy, con la forma de `GET /tasas-cambio/trm` |

`es_tasa_anterior` en `true` significa que la fecha no tiene TRM propia y se usó
la más reciente anterior (RN-017.2). Cuando no hay TRM en ninguna parte (fuente
caída y base vacía), las consultas responden **503** con un `mensaje`.

## Datos y conexión

- `10.x` y `20.x` consultan la fuente oficial si la base no tiene la TRM de la
  fecha, y la guardan: la segunda corrida ya no sale a internet.
- `50.x` registra y corrige una TRM manual del **02/01/2001**, una fecha lejana a
  propósito para no alterar la TRM de fechas en uso. En una segunda corrida,
  `50.4` responde 200 en vez de 201 porque esa TRM ya existe; la prueba admite las
  dos respuestas.
- `60.1` sale siempre a internet. Sin conexión, o si la fuente aún no publica la
  TRM de hoy, responde 503 y el intento queda en bitácora como
  `FALLO_SINCRONIZACION_TRM`; la prueba admite las dos respuestas.

## Estado de verificación

Comprobado:

- **Las 25 peticiones se ejecutaron contra la aplicación corriendo**
  (`http://localhost:8000`), en orden y en una sola sesión, reproduciendo la
  cookie de sesión, el token CSRF por cabecera, el cambio de sesión de la carpeta
  40 y el encadenamiento de variables: **28 comprobaciones, 0 fallos**. Con la
  fuente oficial real: TRM del 27/10/2026 = 3.157,94, y el domingo 15/03/2026
  resuelto con el rango del 14 al 16 de marzo (3.685,53). USD 100 → $ 368.553,00.
- 27 pruebas automatizadas con 123 aserciones en `tests/Feature/Dominio/Trm/`,
  con la fuente simulada (`Http::fake`) y sin salir a internet.
- **28/10/2026: la colección completa se ejecutó con Newman** (el ejecutor de
  Postman por línea de comandos, que corre los scripts `test` igual que el
  Collection Runner) contra `http://localhost:8000`: **25 peticiones, 40
  aserciones, 0 fallos**.

# Postman — MOD-05 Seguridad social

Verificación de RF-033 a RF-036 por HTTP: liquidación del independiente,
aportes del asalariado, pagos por PILA con su gasto y fechas límite de pago.
**39 peticiones en 9 carpetas.**

## Antes de importar

**1. Crear las tablas de MOD-05, una sola vez.** Va con `--path` por la misma
razón que la TRM: el esquema de dominio no tiene migraciones.

```bash
php artisan migrate --path=database/migrations/2026_10_28_000001_create_seguridad_social_tables.php
```

**2. Preparar las cuentas de demostración, antes de cada corrida.**

```bash
php artisan db:seed --class="Database\Seeders\Test\Mod05DemoSeeder"
```

Crea tres cuentas ACTIVA, sin MFA, con la política vigente aceptada y la
contraseña `Scfp#Demo2026`:

| Cuenta | Perfil | Datos |
| :---- | :---- | :---- |
| `mod05@scfp.test` | Independiente, dígitos 37, ARL clase I | Ingreso de honorarios de $ 8.000.000 en 2025-03 |
| `mod05.asalariado@scfp.test` | Asalariado | Salario de $ 6.000.000 en 2025-06 (UUID fijo `…0005-000000000004`) |
| `mod05.sinperfil@scfp.test` | Sin perfil tributario | (CA-008.1) |

Cada ejecución deja las cuentas sin pagos ni liquidaciones, y los aportes del
salario en cero: así la colección se puede volver a correr.

Si la base no tiene calendario de seguridad social (`SEGURIDAD_SOCIAL_INDEP`)
para el año en curso, el seeder crea uno de ejemplo (día 10, todos los dígitos).
Si ya existe, no lo toca.

## Ejecutar

**Con el Collection Runner, en orden.** La colección usa sus propias variables
(`correo_mod05`, `password_mod05`, …), así que no necesita el entorno.

Las carpetas 50 y 60 cierran la sesión anterior y entran con otra cuenta: la 55
es del Asalariado y la 65 de la cuenta sin perfil.

## Qué verifica cada carpeta

| Carpeta | Peticiones | Cubre |
| :---- | :---- | :---- |
| 00 · Autenticación (Independiente) | 3 | Login por el formulario real, token CSRF |
| 10 · RF-033 Liquidación del independiente | 10 | RN-033.1 a RN-033.5, RN-033.7, CA-033.2, CA-033.3, CA-033.4, RNG-04, RF-034 (ocupación) |
| 20 · RF-035 Pagos por PILA | 10 | CA-035.1, CA-035.2, RN-035.1 a RN-035.5 |
| 30 · RF-036 Fechas límite y alertas | 1 | RF-036, RN-036.1, RN-036.2 |
| 40 · RN-035.3 Eliminación del pago y su gasto | 3 | RN-035.3, RNG-07 |
| 50 · Cambio a la sesión del Asalariado | 4 | RF-006, login |
| 55 · RF-034 Aportes del asalariado | 3 | CA-034.1, RN-034.5, RF-033 (ocupación) |
| 60 · Cambio a la cuenta sin perfil | 4 | RF-006, login |
| 65 · CA-008.1 Módulo sin perfil financiero | 1 | CA-008.1 |

## Forma de las respuestas

| Petición | Qué hay dentro de `data` |
| :---- | :---- |
| `POST /seguridad-social/liquidaciones` y `…/simular` | `ingreso_base`, `ibc`, `salud`, `pension`, `fsp`, `arl`, `total`, `avisos`, `parametros_usados`, `aviso_legal` (la guardada trae también `id`, `periodo`, `origen_ingreso`) |
| `POST /seguridad-social/asalariado/calcular` | `ibc`, `salud`, `pension`, `fsp`, `total_aportes`, `salario_neto_sin_retencion`, `parametros_usados`, `aviso_legal` |
| `POST /seguridad-social/pagos` y `GET/PUT …/{id}` | el pago con su `total`, el `gasto`, la `comparacion` con la liquidación (RN-035.5) y el `aviso_legal` |
| `GET /seguridad-social/resumen-anual` | `salud`, `pension`, `ingreso_no_constitutivo_pila` (solo pagos PILA; los aportes del salario los consolida RF-043), `alcance`, `cantidad_pagos` |
| `GET /seguridad-social/alertas` | `periodos` (fecha límite y estado de cada uno) y `alertas` (por vencer o vencidos) |

## Datos y conexión

- La carpeta 30 usa el calendario de la base, así que no fija fechas: comprueba
  que cada periodo tenga fecha límite y estado, que el periodo sea el mes
  anterior al de la fecha límite (mes vencido) y que los vencidos traigan la
  advertencia de mora.
- El correo de las alertas lo envía el comando `scfp:alertas-seguridad-social`
  (diario a las 07:00), no la colección. Lo cubren las pruebas automatizadas.

## Estado de verificación

Comprobado el 28/10/2026:

- **La colección completa se ejecutó con Newman** contra `http://localhost:8000`:
  **39 peticiones, 54 aserciones, 0 fallos**, y otra vez después de volver a
  correr el seeder, con el mismo resultado. Con el calendario real de la base,
  los dígitos 37 vencen el día 8 de cada mes.
- **Otra vez el 28/10/2026, después de la revisión del módulo** (aportes del
  asalariado solo a fuentes "Salario", peticiones simultáneas, alertas por
  rangos y planilla de corrección como diferencia que exige el pago original):
  **39 peticiones, 54 aserciones, 0 fallos**. La colección no necesitó cambios.
- **Otra vez el 28/10/2026, después de la revisión frente a los criterios de
  aceptación** (aviso legal en las respuestas de pagos; `resumen-anual` con
  `ingreso_no_constitutivo_pila` y `alcance`, ajustados en la 20.9): **39
  peticiones, 54 aserciones, 0 fallos**.
- **Otra vez el 28/10/2026, con el cierre de la sesión anterior en la 00.1:**
  sin cookies, **54 aserciones, 0 fallos**; y reutilizando las cookies de una
  corrida que dejó abierta la sesión de `mod05.sinperfil@scfp.test` (el caso que
  daba 403 en la 10.1), también **54 aserciones, 0 fallos**. La carpeta 00 de
  MOD-03, MOD-04 y TRM se comprobó igual: sin cookies, con la sesión de otra
  cuenta y, en TRM, con la del Administrador (5 de 5 en cada caso).
- 56 pruebas automatizadas en `tests/Feature/Dominio/Mod05/`.
