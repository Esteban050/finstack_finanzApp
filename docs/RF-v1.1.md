**CORPORACIÓN UNIVERSITARIA EMPRESARIAL ALEXANDER VON HUMBOLDT**

Programa de Ingeniería de Software

Proyecto del Seminario Nuclear — VIII semestre — Periodo 2026-II

Asignatura: Gestión de Proyectos de Software

**ESPECIFICACIÓN DE REQUISITOS FUNCIONALES**

Sistema Web de Control Financiero Personal con Análisis de Rentabilidad y Proyección Patrimonial (SCFP)

Código: SCFP-ERS-RF  |  Versión: 1.0  |  Estado: En revisión

Elaborado por: Derly Elena Quejada  — Ingeniera de Requisitos / Analista de Negocio

Docente: César Augusto Granada Muñoz

Octubre de 2026

**Tabla de contenido**

# Control del documento

| **Campo** | **Valor** |
| --- | --- |
| Código del documento | SCFP-ERS-RF |
| Versión | 1.0 (borrador para revisión) |
| Fecha | 20/10/2026 |
| Estado | En revisión — pendiente de aprobación del cliente (Product Owner) |
| Elaborado por | Derly Q — Ingeniera de Requisitos / Analista de Negocio |
| Revisado por | Derly Q — Aseguramiento de Calidad |
| Aprobado por | Esteban S — Cliente / Product Owner |

## Historial de versiones

| **Versión** | **Fecha** | **Autor** | **Descripción del cambio** |
| --- | --- | --- | --- |
| 1.0 | 20/10/2026 | Derly Q | Versión inicial de los requisitos funcionales a partir de la guía del proyecto nuclear (características A–E). |
| 1.1 | 20/10/2026 | Derly Q | Versión corregida con requisitos ajustados al alcance real  (características A–E). |

# 1. Introducción

## 1.1 Propósito

Este documento especifica los requisitos funcionales (RF) del SCFP. Es la línea base para el Product Backlog, el diseño, las pruebas de aceptación y la auditoría de calidad.

## 1.2 Alcance

El SCFP es una aplicación web para personas naturales residentes fiscales en Colombia. Permite gestionar ingresos de varias fuentes, clasificar gastos en varias dimensiones, calcular aportes a seguridad social, analizar inversiones y su rentabilidad, evaluar la obligación de declarar renta, gestionar y simular créditos, controlar presupuestos con alertas, proyectar el patrimonio y consultar reportes y dashboards.

**Fuera de alcance (versión 1.0):**

Presentar declaraciones o pagos ante la DIAN, la PILA u otras entidades. El sistema solo informa y estima.

Conexión directa con cuentas bancarias (open banking).

Personas jurídicas, no residentes fiscales y régimen SIMPLE de tributación.

Liquidar las cédulas de dividendos y de ganancias ocasionales (solo se registran como información).

Asesoría financiera o de inversión personalizada.

## 1.3 Estándares y referencias aplicados

| **Referencia** | **Uso en este documento** |
| --- | --- |
| ISO/IEC/IEEE 29148:2018 — Ingeniería de requisitos | Estructura y características de calidad de cada requisito: necesario, no ambiguo, verificable, singular, factible, trazable. Uso de "debe" como verbo obligatorio. |
| ISO/IEC 25010:2023 — Modelo de calidad del producto | Referencia para los requisitos no funcionales (documento aparte) y para la adecuación funcional. |
| Scrum Guide 2020 | Cada RF se descompone en historias de usuario del Product Backlog. Los criterios de aceptación forman parte de la Definition of Done. |
| PMBOK® Guide / ISO 21500:2021 | Gestión del alcance, matriz de trazabilidad de requisitos y control de cambios. |
| Técnica MoSCoW | Priorización (ver 1.5). |
| Ley 1581 de 2012 y Decreto 1377 de 2013 (compilado en el Decreto 1074 de 2015) | Protección de datos personales (habeas data). |
| Estatuto Tributario (ET), arts. 241, 330, 336, 592–594-3, 577 | Cédulas, tarifa marginal de renta, topes para declarar y redondeo. |
| Ley 100 de 1993, Ley 1955 de 2019 (art. 244), Decreto 1990 de 2016, Decreto 1072 de 2015 | Aportes a seguridad social: IBC, tarifas, Fondo de Solidaridad Pensional, ARL y redondeo. |
| Ley 1555 de 2012 | Pago anticipado de créditos sin penalidad. |
| Resoluciones DIAN de UVT; decretos de SMMLV; certificaciones de tasa de usura de la Superintendencia Financiera. | Parámetros normativos que se actualizan por vigencia. |

## 1.4 Actores

| **Actor** | **Descripción** |
| --- | --- |
| Visitante | Persona sin cuenta o sin sesión iniciada. |
| Usuario | Persona natural registrada y autenticada que gestiona sus propias finanzas. |
| Administrador | Responsable de mantener los parámetros normativos, los catálogos y las cuentas. No ve los datos financieros detallados de los usuarios. |
| Sistema | Procesos automáticos programados: recurrencias, alertas, cálculos y notificaciones. |

## 1.5 Convención de prioridad (MoSCoW)

| **Prioridad** | **Significado** | **Compromiso** |
| --- | --- | --- |
| Alta (Must) | Imprescindible para el MVP. | Entra al MVP final (Corte 3). |
| Media (Should) | Aporta mucho valor, pero el MVP funciona sin ella. | Se planifica si la capacidad del equipo lo permite. |
| Baja (Could) | Deseable. | Solo si sobra capacidad. |
| Won't | Acordado que no entra en esta versión. | Va a la sección 1.2 (fuera de alcance). |

## 1.6 Módulos

| **Código** | **Módulo** | **Característica de la guía** |
| --- | --- | --- |
| MOD-01 | Gestión de usuarios y seguridad | Transversal |
| MOD-02 | Administración y parámetros normativos | Transversal / I |
| MOD-03 | Gestión de ingresos | A |
| MOD-04 | Gestión de gastos | B |

## 1.7 Convención de identificadores

**RF-NNN**: requisito funcional.

**RN-NNN.x**: regla de negocio x del requisito NNN.

**CA-NNN.x**: criterio de aceptación x del requisito NNN. Cada CA se puede verificar con al menos un caso de prueba.

**RNG-xx**: regla de negocio general. Aplica a todos los RF aunque no se repita en cada uno.

## 1.8 Glosario

| **Término** | **Definición** |
| --- | --- |
| UVT | Unidad de Valor Tributario. La fija la DIAN cada año por resolución. |
| SMMLV | Salario Mínimo Mensual Legal Vigente. |
| IBC | Ingreso Base de Cotización a seguridad social. |
| FSP | Fondo de Solidaridad Pensional. |
| ARL | Administradora de Riesgos Laborales. |
| PILA | Planilla Integrada de Liquidación de Aportes. |
| EA / NMV | Tasa Efectiva Anual / Tasa Nominal Mensual Vencida. |
| Cédula general | Agrupa las rentas de trabajo, de capital y no laborales (art. 330 ET). |
| Patrimonio bruto | Suma de los activos poseídos al 31 de diciembre. |
| Patrimonio neto | Patrimonio bruto menos pasivos. |
| XIRR / TIR no periódica | Tasa interna de retorno de flujos con fechas irregulares. |
| Vigencia | Año calendario o rango de fechas en el que aplica un parámetro normativo. |

## 1.9 Supuestos y dependencias

SUP-01. Los valores normativos (UVT, SMMLV, tarifas, topes, tasa de usura, calendario tributario) no se programan fijos en el código. El Administrador los registra por vigencia (RF-013, RF-014, RF-016). Los valores de referencia de este documento deben validarse contra la fuente oficial antes de aprobar la línea base.

SUP-02. Los datos financieros los ingresa el usuario. El sistema no garantiza que sean veraces.

SUP-03. Los cálculos tributarios y de seguridad social son estimaciones informativas. No reemplazan la asesoría de un contador público.

SUP-04. El envío de correos depende de un proveedor externo de correo (SMTP o API).

# 2. Reglas de negocio generales (aplican a todos los RF)

**RNG-01 Aislamiento de datos.** Un Usuario solo puede crear, consultar, modificar o eliminar sus propios registros. Si intenta acceder a un recurso de otro usuario, el sistema responde "recurso no encontrado" (HTTP 404) y registra el intento en la bitácora (RF-019).

**RNG-02 Moneda y formato.** La moneda base es el peso colombiano (COP). Los montos se guardan con precisión decimal de 2 posiciones (tipo DECIMAL(18,2); no se permite punto flotante). En pantalla se muestran como $ 1.234.567 (separador de miles ".", decimales "," cuando aplique).

**RNG-03 Fechas y zona horaria.**

**RNG-04 Vigencia normativa.** Cada cálculo usa los parámetros de la vigencia en la que ocurre el hecho económico (fecha del ingreso, del aporte o del año gravable), no los de la fecha de consulta. Si no hay parámetros para esa vigencia, el sistema no calcula y muestra: "No existen parámetros normativos para la vigencia AAAA. Contacte al administrador."

**RNG-05 Validación.** Todas las validaciones se hacen en el servidor; también pueden hacerse en el cliente. Los campos obligatorios se marcan con "*". Cada error de validación se muestra junto al campo y nombra la regla que no se cumple.

**RNG-06 Trazabilidad de registros.** Todo registro guarda fecha y hora de creación, fecha y hora de última modificación y el usuario responsable.

**RNG-07 Eliminación.** Antes de eliminar, el sistema pide confirmación explícita. La eliminación de registros financieros es lógica: el registro deja de aparecer en consultas y cálculos, pero se conserva para auditoría hasta que se suprima la cuenta (RF-010).

**RNG-08 Aviso legal de cálculos.** Toda pantalla con cálculos tributarios, de seguridad social o de proyección financiera muestra: "Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera."

**RNG-09 Rangos de montos.** Un monto debe ser mayor que 0 y menor o igual a 999.999.999.999,99, salvo que el RF indique otra cosa.

**RNG-10 Paginación.** Los listados se paginan de 20 registros por defecto (opciones: 10, 20, 50, 100). Por defecto se ordenan por fecha, del más reciente al más antiguo.

# 3. Requisitos funcionales

## MOD-01 — Gestión de usuarios y seguridad

### RF-001 — Registro de usuario

| **ID** | RF-001 |
| --- | --- |
| **Nombre** | **Registro de usuario** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Visitante |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que un Visitante cree una cuenta de Usuario. Para ello ingresa sus datos de identificación y credenciales y acepta la política de tratamiento de datos personales y los términos de uso.

**Criterios de aceptación y/o reglas de negocio**

RN-001.1 Campos obligatorios: nombres (2–60 caracteres, solo letras y espacios), apellidos (2–60 caracteres), tipo de documento (CC, CE, PA, PPT), número de documento (5–15 caracteres alfanuméricos), correo electrónico (formato RFC 5322, máximo 254 caracteres), contraseña y confirmación de contraseña.

RN-001.2 El correo electrónico es único en el sistema y no distingue mayúsculas de minúsculas. La combinación tipo de documento + número de documento también es única.

RN-001.3 Política de contraseña: entre 10 y 64 caracteres, con al menos una mayúscula, una minúscula, un dígito y un carácter especial. No puede contener el correo ni el número de documento. La contraseña se guarda solo como hash con sal, usando Argon2id o bcrypt. Nunca se guarda en texto plano.

RN-001.4 La aceptación de la política de tratamiento de datos (Ley 1581 de 2012) y de los términos de uso es obligatoria y queda registrada con fecha, hora, IP y versión del documento aceptado (ver RF-009).

RN-001.5 La cuenta se crea en estado "Pendiente de verificación" y no permite iniciar sesión hasta verificar el correo (RF-002).

CA-001.1 Dado un Visitante que diligencia todos los campos válidos y acepta la política, cuando confirma el registro, entonces el sistema crea la cuenta en estado "Pendiente de verificación", envía el correo de verificación y muestra "Revisa tu correo para activar la cuenta".

CA-001.2 Dado un correo ya registrado, cuando el Visitante intenta registrarse, entonces el sistema no crea la cuenta y muestra un mensaje genérico que no confirma si el correo existe: "No fue posible completar el registro con los datos suministrados".

CA-001.3 Dada una contraseña que no cumple la RN-001.3, el sistema indica en tiempo real qué condiciones faltan y no habilita el botón de registro.

CA-001.4 Si la confirmación de contraseña no coincide, el sistema muestra "Las contraseñas no coinciden" y no envía el formulario.

CA-001.5 Si no se marca la aceptación de la política, el sistema no permite completar el registro.

### RF-002 — Verificación de correo electrónico

| **ID** | RF-002 |
| --- | --- |
| **Nombre** | **Verificación de correo electrónico** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Visitante |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe confirmar que el correo pertenece a quien se registró. Para ello envía un enlace de un solo uso que, al abrirse, activa la cuenta.

**Criterios de aceptación y/o reglas de negocio**

RN-002.1 El enlace contiene un token aleatorio de al menos 128 bits, vence a las 24 horas y solo puede usarse una vez.

RN-002.2 Se puede pedir un reenvío como máximo cada 60 segundos y como máximo 5 veces en 24 horas. Cada reenvío invalida los tokens anteriores.

RN-002.3 Las cuentas que sigan "Pendiente de verificación" 7 días después del registro se eliminan físicamente.

CA-002.1 Dado un token válido y vigente, cuando el Visitante abre el enlace, entonces la cuenta pasa a estado "Activa" y el sistema redirige al inicio de sesión con el mensaje "Cuenta verificada".

CA-002.2 Dado un token vencido o ya usado, el sistema muestra "El enlace no es válido o expiró" y ofrece la opción "Reenviar correo de verificación".

CA-002.3 Dado un usuario que pide un reenvío antes de 60 segundos, el sistema no envía el correo e indica cuántos segundos faltan.

### RF-003 — Inicio de sesión

| **ID** | RF-003 |
| --- | --- |
| **Nombre** | **Inicio de sesión** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario / Administrador |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe autenticar a Usuarios y Administradores con correo y contraseña, y dar acceso solo a las funciones de su rol.

**Criterios de aceptación y/o reglas de negocio**

RN-003.1 Solo inician sesión las cuentas en estado "Activa". Las cuentas "Pendiente de verificación", "Bloqueada" o "Inactiva" reciben el mismo mensaje genérico.

RN-003.2 Si las credenciales son incorrectas, el mensaje es siempre "Correo o contraseña incorrectos". No indica cuál de los dos campos falló.

RN-003.3 Al autenticarse se crea una sesión con un token firmado. El token viaja en una cookie HttpOnly, Secure y SameSite=Strict, o en un mecanismo equivalente.

RN-003.4 Cada intento de inicio de sesión, exitoso o fallido, se registra en la bitácora (RF-019) con fecha, hora, IP y resultado.

RN-003.5 Según su rol, el Usuario llega al dashboard (RF-065) y el Administrador al panel de administración.

CA-003.1 Dadas credenciales inválidas, el sistema muestra el mensaje de la RN-003.2 y suma un intento fallido (RF-004).

CA-003.2 Si un Usuario intenta abrir una ruta de administración, el sistema niega el acceso (HTTP 403).

### RF-004 — Bloqueo temporal por intentos fallidos

| **ID** | RF-004 |
| --- | --- |
| **Nombre** | **Bloqueo temporal por intentos fallidos** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe bloquear temporalmente una cuenta cuando se acumulen intentos fallidos consecutivos, para mitigar ataques de fuerza bruta.

**Criterios de aceptación y/o reglas de negocio**

RN-004.1 Con 5 intentos fallidos consecutivos en 15 minutos, la cuenta queda "Bloqueada" durante 15 minutos.

RN-004.2 Un inicio de sesión exitoso reinicia el contador.

RN-004.3 Cuando se bloquea la cuenta, el sistema envía un correo al titular informando el bloqueo, la hora y la IP de origen.

RN-004.4 La cuenta se desbloquea automáticamente al terminar el tiempo, o cuando el titular restablece la contraseña (RF-005).

CA-004.1 En el quinto intento fallido, la cuenta queda bloqueada y cualquier intento posterior, aun con la contraseña correcta, se rechaza durante 15 minutos.

CA-004.2 Cumplidos los 15 minutos, el usuario puede iniciar sesión con la contraseña correcta.

CA-004.3 El bloqueo y el desbloqueo quedan registrados en la bitácora.

### RF-005 — Recuperación de contraseña

| **ID** | RF-005 |
| --- | --- |
| **Nombre** | **Recuperación de contraseña** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Visitante |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir restablecer la contraseña olvidada mediante un enlace temporal enviado al correo registrado.

**Criterios de aceptación y/o reglas de negocio**

RN-005.1 El enlace de restablecimiento vence a los 30 minutos y se puede usar una sola vez.

RN-005.2 Al solicitarlo, el sistema siempre responde "Si el correo está registrado, recibirás un enlace", exista o no la cuenta.

RN-005.3 La nueva contraseña cumple la RN-001.3 y no puede ser igual a ninguna de las 3 últimas contraseñas.

RN-005.4 Al restablecer la contraseña se cierran todas las sesiones activas de la cuenta y se envía un correo de confirmación del cambio.

CA-005.1 Dado un enlace vigente y una contraseña válida, el sistema actualiza la contraseña y redirige al inicio de sesión.

CA-005.2 Dado un enlace vencido o usado, el sistema muestra "El enlace no es válido o expiró".

CA-005.3 Si la nueva contraseña repite alguna de las 3 últimas, el sistema la rechaza con "No puedes reutilizar contraseñas recientes".

### RF-006 — Cierre de sesión y expiración por inactividad

| **ID** | RF-006 |
| --- | --- |
| **Nombre** | **Cierre de sesión y expiración por inactividad** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario / Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir cerrar la sesión de forma voluntaria y debe cerrarla automáticamente tras un periodo de inactividad.

**Criterios de aceptación y/o reglas de negocio**

RN-006.1 La sesión expira tras 15 minutos de inactividad. Sin importar la actividad, expira a las 8 horas de haberse creado.

RN-006.2 Un minuto antes de expirar por inactividad, el sistema avisa y ofrece "Continuar sesión".

RN-006.3 Al cerrar la sesión, el token se invalida en el servidor y deja de servir aunque se reutilice.

CA-006.1 Cuando el Usuario pulsa "Cerrar sesión", el sistema invalida el token y redirige al inicio de sesión. Si luego usa "Atrás" en el navegador, no ve información protegida.

CA-006.2 Tras 15 minutos sin actividad, la siguiente petición redirige al inicio de sesión con el mensaje "Tu sesión expiró por inactividad".

CA-006.3 Si el usuario estaba diligenciando un formulario al expirar la sesión, los datos no se guardan y se le informa.

### RF-007 — Cambio de contraseña

| **ID** | RF-007 |
| --- | --- |
| **Nombre** | **Cambio de contraseña** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario autenticado cambie su contraseña si conoce la actual.

**Criterios de aceptación y/o reglas de negocio**

RN-007.1 Pide la contraseña actual, la nueva y la confirmación. La nueva cumple la RN-001.3 y la RN-005.3.

RN-007.2 Al cambiar la contraseña se cierran todas las demás sesiones activas, se conserva la actual y se envía un correo de notificación.

CA-007.1 Si la contraseña actual es incorrecta, el sistema rechaza el cambio con "La contraseña actual no es correcta" y suma un intento fallido (RF-004).

CA-007.2 Con datos válidos, el sistema confirma "Contraseña actualizada" y la contraseña anterior deja de funcionar.

### RF-008 — Gestión del perfil financiero y tributario

| **ID** | RF-008 |
| --- | --- |
| **Nombre** | **Gestión del perfil financiero y tributario** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario consulte y actualice sus datos personales y su perfil financiero y tributario. De este perfil dependen los cálculos de seguridad social (MOD-05) y de renta (MOD-07).

**Criterios de aceptación y/o reglas de negocio**

RN-008.1 Datos editables: nombres, apellidos, fecha de nacimiento (el usuario debe tener 18 años o más), ciudad de residencia, teléfono (10 dígitos), últimos dos dígitos del NIT o documento (para el calendario tributario, RF-016) y número de personas a cargo (0–10).

RN-008.2 Perfil de ocupación (selección múltiple): Asalariado, Independiente por cuenta propia, Contratista por prestación de servicios, Rentista de capital, Pensionado. Con "Asalariado" se pide además si tiene salario integral (sí/no).

RN-008.3 Con "Contratista" o "Independiente" se pide la clase de riesgo ARL (I a V) y si la afiliación a ARL es obligatoria o voluntaria.

RN-008.4 Se pide la condición de responsable de IVA (sí/no). Si es "sí", la obligación de declarar renta se marca automáticamente (RF-045).

RN-008.5 El correo y el documento no se pueden editar desde el perfil. Cambiar el correo exige verificar el nuevo correo (mismo flujo de RF-002).

RN-008.6 Cada cambio en el perfil tributario queda en un historial con fecha de vigencia, para que los cálculos de periodos pasados usen el perfil que regía entonces.

CA-008.1 Si el Usuario no completa el perfil de ocupación, el sistema no deja usar MOD-05 ni MOD-07 y muestra "Completa tu perfil financiero para usar este módulo".

CA-008.2 Al guardar cambios válidos, el sistema confirma "Perfil actualizado" y los nuevos valores se aplican desde la fecha de vigencia indicada.

CA-008.3 Una fecha de nacimiento que da menos de 18 años se rechaza con "Debes ser mayor de edad".

### RF-009 — Gestión de la autorización de tratamiento de datos personales

| **ID** | RF-009 |
| --- | --- |
| **Nombre** | **Gestión de la autorización de tratamiento de datos personales** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe registrar, mostrar y gestionar la autorización del titular para el tratamiento de sus datos personales, según la Ley 1581 de 2012 y el Decreto 1377 de 2013.

**Criterios de aceptación y/o reglas de negocio**

RN-009.1 Cada aceptación queda registrada con la versión de la política, fecha y hora, IP y medio. El registro es inmutable.

RN-009.2 Cuando el Administrador publica una nueva versión de la política, el Usuario debe aceptarla en su siguiente inicio de sesión para seguir usando el sistema.

RN-009.3 El Usuario puede consultar en cualquier momento el texto de la política vigente y el historial de sus aceptaciones.

RN-009.4 El Usuario puede revocar la autorización. Revocarla implica pedir la supresión de la cuenta (RF-010), y el sistema lo advierte antes de confirmar.

CA-009.1 Dada una nueva versión de la política, al iniciar sesión el Usuario ve la política y solo accede al sistema si la acepta.

CA-009.2 El historial muestra cada aceptación con versión, fecha y hora.

### RF-010 — Supresión de la cuenta

| **ID** | RF-010 |
| --- | --- |
| **Nombre** | **Supresión de la cuenta** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario pida eliminar su cuenta y todos sus datos personales y financieros, en ejercicio del derecho de supresión (Ley 1581 de 2012, art. 8).

**Criterios de aceptación y/o reglas de negocio**

RN-010.1 Para confirmar, el usuario ingresa su contraseña y escribe la palabra "ELIMINAR".

RN-010.2 La cuenta pasa a "Pendiente de supresión" y se bloquea de inmediato. Durante 15 días calendario el titular puede cancelar la solicitud con el enlace que recibe por correo.

RN-010.3 Pasados los 15 días, el Sistema elimina físicamente los datos personales y financieros. En la bitácora solo queda un identificador anónimo del evento de supresión.

RN-010.4 La solicitud se atiende en un plazo máximo de 15 días hábiles (Ley 1581, art. 15).

CA-010.1 Al confirmar la solicitud, el usuario sale de la sesión y recibe un correo con la fecha programada de supresión.

CA-010.2 Si cancela dentro del plazo, la cuenta vuelve a "Activa" con todos sus datos.

CA-010.3 Después de la supresión, ninguna búsqueda por correo o documento devuelve datos de esa cuenta, y el correo puede usarse en un nuevo registro.

### RF-011 — Exportación de datos personales

| **ID** | RF-011 |
| --- | --- |
| **Nombre** | **Exportación de datos personales** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir que el Usuario descargue todos sus datos registrados en un formato estructurado y legible por máquina.

**Criterios de aceptación y/o reglas de negocio**

RN-011.1 El archivo de exportación es un .zip con un archivo JSON y un archivo CSV por entidad: perfil, ingresos, gastos, categorías, presupuestos, inversiones, créditos, activos, pasivos y aportes.

RN-011.2 Se permite como máximo 1 exportación cada 24 horas. El enlace de descarga vence a las 24 horas.

CA-011.2 El archivo contiene el 100 % de los registros activos del usuario. Se verifica comparando el conteo con el de la base de datos.

### RF-012 — Verificación en dos pasos con código enviado al correo

| **ID** | RF-012 |
| --- | --- |
| **Nombre** | **Verificación en dos pasos con código enviado al correo** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario / Administrador |
| **Prioridad** | Baja (Could) — obligatoria para el rol Administrador si se implementa |

Descripción

El sistema debe permitir activar una verificación adicional al iniciar sesión. Después de validar la contraseña, el sistema envía un código numérico de un solo uso al correo registrado, y el usuario debe ingresarlo para completar el acceso. No requiere instalar ninguna aplicación adicional.

Criterios de aceptación y/o reglas de negocio

RN-012.1 El Usuario activa o desactiva la verificación desde su perfil. Para activarla, el sistema envía un código al correo registrado y el usuario debe ingresarlo para confirmar. Para desactivarla, se piden la contraseña y un código válido.

RN-012.2 El código tiene 6 dígitos numéricos generados con un generador aleatorio criptográficamente seguro. Vence a los 10 minutos, sirve una sola vez y se guarda solo como hash, nunca en texto plano.

RN-012.3 Cada código admite máximo 5 intentos. Al agotarlos, el código se invalida y los intentos cuentan como intentos fallidos según RF-004.

RN-012.4 El usuario puede pedir un nuevo código después de 60 segundos, con un máximo de 3 reenvíos por inicio de sesión. Cada código nuevo invalida el anterior.

RN-012.5 El correo muestra el código, la fecha y hora de la solicitud, la IP de origen y el aviso "Si no fuiste tú, cambia tu contraseña". El correo nunca incluye un enlace de acceso directo.

RN-012.6 El usuario puede marcar "Recordar este dispositivo por 30 días". Durante ese plazo, en ese navegador no se pide el código. Cambiar la contraseña (RF-005, RF-007) anula todos los dispositivos recordados.

CA-012.1 Con la verificación activa, después de una contraseña correcta el sistema envía el código, muestra la pantalla para ingresarlo y no crea la sesión hasta validarlo.

CA-012.2 Si el código es correcto y está vigente, el sistema inicia la sesión y redirige según la RN-003.5.

CA-012.3 Si el código está vencido, el sistema muestra "El código expiró. Solicita uno nuevo".

CA-012.4 Si el código es incorrecto, el sistema muestra "Código incorrecto. Te quedan N intentos".

## MOD-02 — Administración y parámetros normativos

### RF-013 — Gestión de parámetros normativos por vigencia

| **ID** | RF-013 |
| --- | --- |
| **Nombre** | **Gestión de parámetros normativos por vigencia** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Administrador |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Administrador cree, consulte y modifique los parámetros normativos de cada vigencia anual. Todos los cálculos del sistema usan estos parámetros.

**Criterios de aceptación y/o reglas de negocio**

RN-013.1 Parámetros mínimos por vigencia (año), con su fuente normativa (número y fecha del acto administrativo):

| **Parámetro** | **Valor de referencia (validar contra la fuente oficial)** |
| --- | --- |
| UVT | Año gravable 2025: $ 49.799 (Res. DIAN 000193 de 2024). Vigencia 2026: según la resolución DIAN vigente. |
| SMMLV | 2025: $ 1.423.500. Vigencia 2026: según el decreto vigente. |
| Tarifa de salud — independiente | 12,5 % |
| Tarifa de pensión — independiente | 16 % |
| Tarifa de salud — aporte del empleado | 4 % |
| Tarifa de pensión — aporte del empleado | 4 % |
| Porcentaje del IBC para independientes | 40 % del ingreso mensual (Ley 1955 de 2019, art. 244) |
| Porcentaje del IBC para salario integral | 70 % |
| IBC mínimo / máximo | 1 SMMLV / 25 SMMLV |
| Topes para declarar renta | Patrimonio bruto 4.500 UVT; ingresos, consumos con tarjeta de crédito, compras y consumos, consignaciones: 1.400 UVT cada uno |
| Renta exenta del 25 % — límite anual | 790 UVT |
| Límite global de rentas exentas y deducciones | 40 % del ingreso neto y 1.340 UVT anuales |
| Tarifa del GMF | 0,4 % (4x1000); exención de 350 UVT mensuales |
| IPC (inflación) del año | Según DANE |

RN-013.2 Solo puede haber una vigencia activa por año. No se puede borrar una vigencia que ya se usó en algún cálculo; solo se puede reemplazar por una nueva versión, guardando la anterior en el historial.

RN-013.3 Todo cambio guarda el valor anterior, el valor nuevo, el administrador, la fecha y hora y la justificación (obligatoria, mínimo 20 caracteres) en la bitácora (RF-019).

RN-013.4 Los cálculos que el Usuario ya guardó no se recalculan solos. Si cambia un parámetro de su vigencia, el sistema le muestra el aviso "Los parámetros normativos de AAAA fueron actualizados; recalcule para ver valores vigentes".

CA-013.1 Un Administrador puede registrar la vigencia AAAA con todos los parámetros de la RN-013.1. Si falta alguno, el sistema no la deja en estado "Activa".

CA-013.2 Un valor negativo o un porcentaje fuera de 0–100 se rechaza.

CA-013.3 Un Usuario no puede ver la pantalla de edición de parámetros (HTTP 403).

### RF-014 — Gestión de tablas de tarifas escalonadas

| **ID** | RF-014 |
| --- | --- |
| **Nombre** | **Gestión de tablas de tarifas escalonadas** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Administrador |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Administrador mantenga, por vigencia, las tablas escalonadas que usan los cálculos: tarifa marginal de renta (art. 241 ET), Fondo de Solidaridad Pensional y tarifas ARL por clase de riesgo.

**Criterios de aceptación y/o reglas de negocio**

RN-014.1 Tabla de renta (art. 241 ET), con valores en UVT:

| **Desde (>) UVT** |  | **Tarifa marginal** | **Impuesto** |
| --- | --- | --- | --- |
| 0 | 1.090 | 0 % | 0 |
| 1.090 | 1.700 | 19 % |  |
| 1.700 | 4.100 | 28 % |  |
| 4.100 | 8.670 | 33 % |  |
| 8.670 | 18.970 | 35 % |  |
| 18.970 | 31.000 | 37 % |  |
| 31.000 | En adelante | 39 % |  |

RN-014.3 Tabla ARL (Decreto 1072 de 2015) por clase de riesgo: I 0,522 %; II 1,044 %; III 2,436 %; IV 4,350 %; V 6,960 %.

RN-014.4 Validación de rangos: los tramos de una tabla deben ser continuos, sin huecos ni traslapes, y cubrir de 0 a "en adelante". Si no, la tabla no se puede activar.

CA-014.1 Si se registra una tabla con un hueco entre tramos, el sistema rechaza la activación e indica qué tramo tiene el error.

### RF-015 — Gestión del catálogo de categorías predeterminadas

| **ID** | RF-015 |
| --- | --- |
| **Nombre** | **Gestión del catálogo de categorías predeterminadas** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Administrador |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Administrador mantenga el catálogo global de categorías y subcategorías de ingresos y gastos que todo Usuario nuevo recibe al registrarse.

**Criterios de aceptación y/o reglas de negocio**

RN-015.1 Catálogo inicial mínimo de gastos: Vivienda, Alimentación, Transporte, Salud, Educación, Servicios públicos, Telecomunicaciones, Entretenimiento, Vestuario, Obligaciones financieras, Impuestos, Seguridad social, Seguros, Mascotas, Donaciones y Otros. Cada una tiene al menos 2 subcategorías.

RN-015.2 Catálogo inicial mínimo de tipos de fuente de ingreso: Salario, Honorarios/Servicios, Arrendamientos, Rendimientos financieros, Dividendos, Pensión, Venta de activos, Negocio propio, Otros.

RN-015.3 Una categoría predeterminada que algún Usuario ya usó no se puede borrar; solo se inactiva. Al inactivarla, no aparece en registros nuevos, pero los históricos la conservan.

RN-015.4 Los nombres son únicos dentro del mismo nivel y tipo (ingreso o gasto), con un máximo de 40 caracteres.

CA-015.1 Un Usuario creado después de agregar una categoría al catálogo la ve disponible. Los Usuarios anteriores la reciben como categoría nueva sin perder sus personalizaciones.

CA-015.2 Una categoría inactivada no aparece en el formulario de registro de gastos, pero sí en los reportes de periodos en que se usó.

### RF-016 — Gestión del calendario tributario y de aportes

| **ID** | RF-016 |
| --- | --- |
| **Nombre** | **Gestión del calendario tributario y de aportes** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Administrador |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir que el Administrador registre, por vigencia, las fechas de vencimiento para declarar renta de personas naturales y para pagar los aportes a seguridad social de independientes, según los dos últimos dígitos del documento o NIT.

**Criterios de aceptación y/o reglas de negocio**

RN-016.1 Cada registro tiene: vigencia, tipo de obligación (Renta personas naturales / Seguridad social independientes), rango de dígitos (00–99) y fecha de vencimiento.

RN-016.2 Los rangos de dígitos de una obligación y vigencia deben cubrir del 00 al 99 sin traslapes.

RN-016.3 Se puede cargar desde un archivo CSV con columnas digito_desde, digito_hasta, fecha. El sistema valida el archivo completo antes de guardarlo; si una fila es inválida, no guarda ninguna.

CA-016.1 Al cargar un calendario que no cubre algún dígito, el sistema lo rechaza e indica los dígitos faltantes.

CA-016.2 Un Usuario con dígitos "37" ve como fecha de vencimiento la del rango que contiene el 37.

### RF-017 — Gestión de cuentas de usuario por el Administrador

| **ID** | RF-018 |
| --- | --- |
| **Nombre** | **Gestión de cuentas de usuario por el Administrador** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Administrador |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir que el Administrador consulte el listado de cuentas, cambie su estado (Activa/Inactiva), desbloquee cuentas y cree otros Administradores, sin acceder a la información financiera de los Usuarios.

**Criterios de aceptación y/o reglas de negocio**

RN-018.1 El listado muestra solo nombre, correo, estado, fecha de registro y fecha del último acceso. No muestra montos ni registros financieros (principio de minimización de datos).

RN-018.2 Para inactivar una cuenta se exige una justificación. El Usuario recibe un correo de notificación.

RN-018.3 Un Administrador no puede inactivarse a sí mismo. El sistema debe conservar siempre al menos un Administrador activo.

CA-018.2 Ninguna ruta de administración devuelve datos financieros de los Usuarios. Se verifica con pruebas de acceso.

## MOD-03 — Gestión de ingresos (característica A)

### RF-018 — Gestión de fuentes de ingreso

| **ID** | RF-020 |
| --- | --- |
| **Nombre** | **Gestión de fuentes de ingreso** |
| **Módulo** | MOD-03 Gestión de ingresos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario cree, consulte, edite e inactive sus fuentes de ingreso. Ejemplos: "Empresa XYZ – salario", "Apartamento Calle 10 – arriendo", "CDT Banco ABC".

**Criterios de aceptación y/o reglas de negocio**

RN-020.1 Campos: nombre de la fuente* (3–60 caracteres, único por usuario), tipo de fuente* (catálogo RN-015.2), clasificación tributaria* (cédula: Rentas de trabajo, Rentas de capital, Rentas no laborales, Pensiones, Dividendos y participaciones — art. 330 ET), pagador o entidad (opcional, máximo 80 caracteres) y estado (Activa/Inactiva).

RN-020.3 Una fuente con ingresos asociados no se puede borrar, solo inactivar. Una fuente inactiva no aparece al registrar ingresos nuevos.

CA-020.1 Al crear una fuente con un nombre que ya existe para ese usuario, el sistema muestra "Ya existe una fuente con ese nombre".

CA-020.2 Al intentar borrar una fuente con ingresos asociados, el sistema solo ofrece la opción "Inactivar".

### RF-019 — Registro de ingreso

| **ID** | RF-021 |
| --- | --- |
| **Nombre** | **Registro de ingreso** |
| **Módulo** | MOD-03 Gestión de ingresos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario registre un ingreso asociado a una fuente, con los datos que necesitan los cálculos de flujo de caja, seguridad social y renta.

**Criterios de aceptación y/o reglas de negocio**

RN-021.3 Si la moneda no es COP, se guardan el monto original, la tasa usada y el monto convertido a COP. Este último es el que se usa en todos los cálculos.

RN-021.4 El ingreso hereda la clasificación tributaria de la fuente, salvo que el usuario la cambie en ese registro.

CA-021.1 Al registrar un ingreso con monto bruto $ 5.000.000, retención $ 150.000 y aportes $ 400.000, el sistema guarda y muestra un neto de $ 4.450.000.

CA-021.2 Una retención mayor que el monto bruto se rechaza con "La retención no puede superar el monto bruto".

CA-021.3 Una fecha futura se rechaza con la sugerencia de crear un ingreso recurrente o programado.

CA-021.4 El ingreso registrado se refleja de inmediato en el dashboard (RF-065) y en el patrimonio líquido si está asociado a una cuenta (RF-061).

### RF-020 — Gestión de ingresos recurrentes

| **ID** | RF-022 |
| --- | --- |
| **Nombre** | **Gestión de ingresos recurrentes** |
| **Módulo** | MOD-03 Gestión de ingresos |
| **Actor principal** | Usuario / Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir definir ingresos que se repiten con una periodicidad. El Sistema genera automáticamente cada ocurrencia en la fecha programada.

**Criterios de aceptación y/o reglas de negocio**

RN-022.1 Periodicidad: semanal, quincenal, mensual, bimestral, trimestral, semestral o anual. Se definen fecha de inicio*, fecha de fin (opcional) o número de ocurrencias (opcional, 1–360) y los mismos datos de RF-021.

RN-022.2 El Sistema genera cada ocurrencia a las 00:05 del día programado, en estado "Generado automáticamente". Si el día no existe en el mes (por ejemplo, el 31), se usa el último día del mes.

RN-022.3 Modo de confirmación configurable: "Registrar automáticamente" o "Requiere confirmación". En el segundo modo, la ocurrencia queda "Pendiente" y no entra en los cálculos hasta que el usuario la confirme.

RN-022.4 Editar la plantilla solo cambia las ocurrencias futuras. Las ya generadas no cambian.

CA-022.1 Con un ingreso mensual programado para el día 30 desde el 30/01/2026, el Sistema genera la ocurrencia de febrero el 28/02/2026.

CA-022.2 Al pausar o terminar una recurrencia, no se generan más ocurrencias a partir de ese momento.

CA-022.3 Un ingreso recurrente "Pendiente" aparece en el centro de notificaciones (RF-058) hasta que se confirme o descarte.

### RF-021 — Edición y eliminación de ingresos

| **ID** | RF-023 |
| --- | --- |
| **Nombre** | **Edición y eliminación de ingresos** |
| **Módulo** | MOD-03 Gestión de ingresos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario corrija o elimine ingresos registrados, manteniendo coherentes los cálculos que dependen de ellos.

**Criterios de aceptación y/o reglas de negocio**

RN-023.1 Se pueden editar todos los campos de RF-021, con las mismas validaciones.

RN-023.2 La eliminación sigue la RNG-07.

RN-023.3 Al editar o eliminar un ingreso, se recalculan automáticamente los indicadores del dashboard y de presupuesto del periodo afectado. También se marcan como "Desactualizados" los cálculos guardados de seguridad social y renta de ese periodo.

CA-023.1 Si se elimina un ingreso de marzo, el total de ingresos de marzo en el dashboard baja exactamente en el monto convertido de ese ingreso.

CA-023.2 Antes de eliminar, el sistema muestra un diálogo con la fecha, la fuente y el monto del ingreso.

### RF-022 — Consulta y filtrado de ingresos

| **ID** | RF-024 |
| --- | --- |
| **Nombre** | **Consulta y filtrado de ingresos** |
| **Módulo** | MOD-03 Gestión de ingresos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe mostrar el listado de ingresos del Usuario, con filtros, ordenamiento y totales.

**Criterios de aceptación y/o reglas de negocio**

RN-024.1 Filtros combinables: rango de fechas, fuente, tipo de fuente, clasificación tributaria, moneda, origen (manual / recurrente / importado) y rango de montos.

RN-024.2 Columnas: fecha, fuente, clasificación tributaria, monto bruto, retención, aportes, neto y moneda original.

RN-024.3 Al pie del listado se muestran los totales de monto bruto, retención y neto del conjunto filtrado completo, no solo de la página visible.

CA-024.1 Con un filtro de enero 2026 y fuente "Empresa XYZ", el listado y los totales incluyen únicamente esos registros.

CA-024.2 Si no hay resultados, se muestra "No hay ingresos para los filtros seleccionados".

## MOD-04 — Gestión de gastos (característica B)

### RF-023 — Registro de gasto

| **ID** | RF-025 |
| --- | --- |
| **Nombre** | **Registro de gasto** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario registre un gasto con su monto, fecha, clasificación (RF-026) y medio de pago.

**Criterios de aceptación y/o reglas de negocio**

RN-025.1 Campos: fecha* (no posterior a la fecha actual), monto* (RNG-09), moneda* (COP por defecto), categoría* y subcategoría*, medio de pago* (Efectivo, Tarjeta débito, Tarjeta de crédito, Transferencia, Billetera digital, Otro), comercio o beneficiario (opcional, máximo 80 caracteres) y descripción (opcional, máximo 255 caracteres).

RN-025.2 Si el medio de pago es "Tarjeta de crédito", el monto se acumula en el indicador "Consumos con tarjeta de crédito" del año (RF-044). Todo gasto se acumula en "Compras y consumos totales".

RN-025.3 Si el gasto pertenece a la categoría "Obligaciones financieras" y se asocia a un crédito registrado, el sistema lo registra como pago de cuota (RF-050) y no lo cuenta dos veces.

RN-025.4 Al guardar el gasto se actualiza la ejecución del presupuesto de su categoría (RF-056) y se evalúan las alertas (RF-057).

CA-025.1 Un gasto con monto 0 o negativo se rechaza.

CA-025.2 Al registrar un gasto con tarjeta de crédito de $ 200.000, el acumulado anual de consumos con tarjeta y el de compras y consumos suben $ 200.000 cada uno.

### RF-024 — Clasificación multidimensional del gasto

| **ID** | RF-026 |
| --- | --- |
| **Nombre** | **Clasificación multidimensional del gasto** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe clasificar cada gasto en varias dimensiones a la vez. Así se pueden hacer análisis cruzados (por ejemplo, "gastos variables discrecionales pagados con tarjeta de crédito").

**Criterios de aceptación y/o reglas de negocio**

RN-026.1 Dimensiones:

**Categoría / subcategoría** (jerarquía de 2 niveles, obligatoria).

**Naturaleza**: Fijo / Variable (obligatoria; la sugiere la subcategoría).

**Necesidad**: Esencial / Discrecional (obligatoria; la sugiere la subcategoría).

**Medio de pago** (obligatoria, RN-025.1).

**Relevancia tributaria**: No deducible / Potencialmente deducible, con subtipo: intereses de vivienda, medicina prepagada, dependientes, aportes voluntarios a pensión o AFC, GMF, otros (por defecto "No deducible").

**Etiquetas libres**: de 0 a 10 por gasto, cada una de 2–30 caracteres, reutilizables (por ejemplo, "viaje-cartagena").

RN-026.2 Cada subcategoría tiene valores sugeridos para Naturaleza y Necesidad, que el usuario puede cambiar en cada gasto.

RN-026.3 Los gastos "Potencialmente deducibles" se consolidan en el resumen tributario anual (RF-043).

CA-026.1 En el formulario de gasto, al elegir la subcategoría "Arriendo", Naturaleza = Fijo y Necesidad = Esencial se precargan.

CA-026.2 El listado de gastos se puede filtrar combinando cualquier dimensión, y los totales corresponden exactamente al filtro.

CA-026.3 Una etiqueta con más de 30 caracteres, o una undécima etiqueta en el mismo gasto, se rechaza con un mensaje que indica el límite.

### RF-025 — Gestión de categorías y subcategorías personalizadas

| **ID** | RF-027 |
| --- | --- |
| **Nombre** | **Gestión de categorías y subcategorías personalizadas** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario cree, renombre, inactive y reorganice sus propias categorías y subcategorías de gasto, además de las predeterminadas.

**Criterios de aceptación y/o reglas de negocio**

RN-027.1 Límites: 50 categorías y 30 subcategorías por categoría como máximo. Nombres de 2–40 caracteres, únicos en el mismo nivel.

RN-027.2 Una categoría o subcategoría con gastos asociados no se puede borrar. Se puede inactivar o fusionar con otra; al fusionar, el usuario elige el destino y todos los gastos pasan a él.

RN-027.3 Renombrar una categoría actualiza su nombre en todos los registros y reportes, incluidos los históricos.

RN-027.4 Cada categoría puede tener un ícono y un color (paleta definida), que se usan en los gráficos.

CA-027.1 Al fusionar la subcategoría "Café" en "Restaurantes", todos los gastos de "Café" quedan en "Restaurantes" y "Café" desaparece.

CA-027.2 Al intentar crear una categoría número 51, el sistema muestra "Alcanzaste el máximo de 50 categorías".

### RF-026 — Gestión de gastos recurrentes

| **ID** | RF-028 |
| --- | --- |
| **Nombre** | **Gestión de gastos recurrentes** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario / Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir definir gastos periódicos (arriendo, suscripciones, servicios). El Sistema los genera automáticamente.

**Criterios de aceptación y/o reglas de negocio**

RN-028.1 Se aplican las mismas reglas de periodicidad, generación, confirmación y edición que en RF-022 (RN-022.1 a RN-022.4).

RN-028.2 Para gastos de monto variable (por ejemplo, servicios públicos), el usuario puede marcar "Monto estimado". La ocurrencia se genera como "Pendiente" con el monto estimado y el usuario debe confirmar el monto real.

RN-028.3 Tres días antes de cada ocurrencia, el Sistema envía un recordatorio (RF-058), si el usuario lo tiene habilitado.

CA-028.1 Una suscripción mensual de $ 39.900 el día 5 genera un gasto el día 5 de cada mes con la clasificación configurada.

CA-028.2 Un gasto con "Monto estimado" no entra en la ejecución presupuestal hasta que se confirma.

### RF-027 — Edición, eliminación y consulta de gastos

| **ID** | RF-029 |
| --- | --- |
| **Nombre** | **Edición, eliminación y consulta de gastos** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir listar, filtrar, editar y eliminar gastos, y debe recalcular los indicadores que dependen de ellos.

**Criterios de aceptación y/o reglas de negocio**

RN-029.1 Filtros: rango de fechas, categoría, subcategoría, naturaleza, necesidad, medio de pago, relevancia tributaria, etiqueta, rango de montos y texto libre en descripción o comercio.

RN-029.2 Al editar o eliminar un gasto se recalcula la ejecución del presupuesto del periodo afectado. Las alertas ya emitidas no se borran, pero si el gasto deja de superar el umbral se muestran como "Resuelta".

RN-029.3 Edición masiva: se pueden seleccionar hasta 100 gastos y cambiarles la categoría, la naturaleza, la necesidad o las etiquetas en una sola operación.

CA-029.1 Los totales al pie corresponden al conjunto filtrado completo.

| **Característica (guía del proyecto)** | **Requisitos funcionales** |
| --- | --- |
| A. Gestión de ingresos múltiples | RF-018, RF-019, RF-020, RF-021, RF-022 |
| B. Clasificación de gastos multidimensional | RF-023, RF-024, RF-025, RF-026, RF-027 |
| Transversal: seguridad, datos personales y administración | RF-001 a RF-017 |
| I. Otros según necesidades del cliente | Se gestionan mediante solicitudes de cambio (sección 6) |

# 5. Gestión de cambios a los requisitos

Todo cambio solicitado por el cliente (característica I y "Cambios de cliente" del Corte 2) se registra como Solicitud de Cambio (SC-NNN) con: descripción, justificación, RF afectados, impacto en alcance, tiempo y costo, prioridad MoSCoW y decisión (Aprobada / Rechazada / Aplazada).

Un RF aprobado solo se modifica mediante una SC aprobada por el Product Owner. Cada modificación sube la versión del documento (1.x para ajustes, 2.0 para cambios de alcance) y se registra en el historial de versiones.

Cuando se aprueba una SC, se actualiza la matriz de trazabilidad (sección 4) y los casos de prueba asociados.

# 6. Aprobación

| **Rol** | **Nombre** | **Firma** | **Fecha** |
| --- | --- | --- | --- |
| Ingeniera de Requisitos / Analista de Negocio | Derly | Derly | 20/10/2026 |
| Product Owner / Cliente |  |  |  |
| Aseguramiento de Calidad |  |  |  |
