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
| Elaborado por | [Nombre] — Ingeniera de Requisitos / Analista de Negocio |
| Revisado por | [Nombre] — Aseguramiento de Calidad |
| Aprobado por | [Nombre] — Cliente / Product Owner |

## Historial de versiones

| **Versión** | **Fecha** | **Autor** | **Descripción del cambio** |
| --- | --- | --- | --- |
| 1.0 | 20/10/2026 | [Nombre] | Versión inicial de los requisitos funcionales a partir de la guía del proyecto nuclear (características A–I). |

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
| Resoluciones DIAN de UVT; decretos de SMMLV; certificaciones de tasa de usura de la Superintendencia Financiera; TRM del Banco de la República | Parámetros normativos que se actualizan por vigencia. |

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
| MOD-05 | Seguridad social | C |
| MOD-06 | Inversiones y rentabilidad | D |
| MOD-07 | Declaración de renta | C, E |
| MOD-08 | Créditos | F |
| MOD-09 | Presupuestos, metas y alertas | G |
| MOD-10 | Patrimonio y proyección patrimonial | Eje problémico (proyección patrimonial) |
| MOD-11 | Reportes y dashboards | H |

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
| TRM | Tasa Representativa del Mercado (COP por USD). |
| EA / NMV | Tasa Efectiva Anual / Tasa Nominal Mensual Vencida. |
| Cédula general | Agrupa las rentas de trabajo, de capital y no laborales (art. 330 ET). |
| GMF | Gravamen a los Movimientos Financieros (4x1000). |
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

### RF-012 — Autenticación en dos pasos

| **ID** | RF-012 |
| --- | --- |
| **Nombre** | **Autenticación en dos pasos (2FA)** |
| **Módulo** | MOD-01 Gestión de usuarios y seguridad |
| **Actor principal** | Usuario / Administrador |
| **Prioridad** | Baja (Could) — obligatoria para el rol Administrador si se implementa |

**Descripción**

El sistema debe permitir activar un segundo factor de autenticación con código temporal TOTP (RFC 6238), compatible con aplicaciones autenticadoras.

**Criterios de aceptación y/o reglas de negocio**

RN-012.1 Al activar el 2FA se muestra un código QR y se generan 10 códigos de recuperación de un solo uso.

RN-012.2 El código TOTP tiene 6 dígitos y una ventana de 30 segundos, con tolerancia de ±1 ventana.

RN-012.3 Para desactivar el 2FA se pide la contraseña y un código TOTP válido.

CA-012.1 Con el 2FA activo, después de la contraseña el sistema pide el código y no crea la sesión hasta validarlo.

CA-012.2 Un código inválido cuenta como intento fallido según RF-004.

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

### RF-017 — Registro de la TRM

| **ID** | RF-017 |
| --- | --- |
| **Nombre** | **Registro de la Tasa Representativa del Mercado (TRM)** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Sistema / Administrador |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe guardar la TRM diaria (COP por USD) para convertir a pesos los ingresos, gastos e inversiones registrados en dólares. La TRM se obtiene de forma automática de la fuente oficial (datos abiertos del Banco de la República / Superintendencia Financiera) o la registra el Administrador.

**Criterios de aceptación y/o reglas de negocio**

RN-017.1 El Sistema consulta la fuente oficial todos los días a las 08:00 (America/Bogota). Si la consulta falla, la reintenta cada hora hasta 6 veces y luego avisa al Administrador.

RN-017.2 Si no hay TRM para una fecha (festivos o fines de semana), se usa la TRM vigente más reciente anterior a esa fecha.

RN-017.3 El Administrador puede registrar o corregir una TRM manualmente, con una justificación que queda en la bitácora.

RN-017.4 Monedas soportadas en la v1.0: COP y USD. Para cualquier otra moneda, el usuario ingresa manualmente la tasa de conversión de cada registro.

CA-017.1 Un ingreso de USD 100 con fecha 15/03/2026 se convierte con la TRM vigente en esa fecha, y el valor en COP queda guardado con la TRM usada.

CA-017.2 Si cambia la TRM de una fecha, los registros que ya la usaron no cambian, salvo que el usuario los edite.

### RF-018 — Gestión de cuentas de usuario por el Administrador

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

### RF-019 — Bitácora de auditoría

| **ID** | RF-019 |
| --- | --- |
| **Nombre** | **Bitácora de auditoría** |
| **Módulo** | MOD-02 Administración y parámetros normativos |
| **Actor principal** | Sistema / Administrador |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe registrar en una bitácora inalterable los eventos de seguridad y de administración, y debe permitir que el Administrador la consulte y filtre.

**Criterios de aceptación y/o reglas de negocio**

RN-019.1 Eventos mínimos: inicios de sesión exitosos y fallidos, bloqueos y desbloqueos, cambios y restablecimientos de contraseña, aceptación y revocación de la política de datos, solicitudes de supresión, accesos denegados (403/404 de la RNG-01), cambios en parámetros, tablas, catálogo, calendario, TRM manual y estado de cuentas.

RN-019.2 Cada evento guarda: fecha y hora (con zona horaria), tipo de evento, actor, IP, recurso afectado, valor anterior y valor nuevo (cuando aplique) y resultado.

RN-019.3 La bitácora no se puede editar ni borrar desde la aplicación, por ningún rol. Se conserva por lo menos 1 año.

RN-019.4 La bitácora nunca guarda contraseñas, tokens ni montos financieros de los Usuarios.

CA-019.1 El Administrador filtra la bitácora por rango de fechas, tipo de evento y actor, y la exporta a CSV.

CA-019.2 Ninguna ruta de la API permite modificar o borrar un registro de la bitácora.

## MOD-03 — Gestión de ingresos (característica A)

### RF-020 — Gestión de fuentes de ingreso

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

### RF-021 — Registro de ingreso

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

### RF-022 — Gestión de ingresos recurrentes

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

### RF-023 — Edición y eliminación de ingresos

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

### RF-024 — Consulta y filtrado de ingresos

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

### RF-025 — Registro de gasto

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

### RF-026 — Clasificación multidimensional del gasto

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

### RF-027 — Gestión de categorías y subcategorías personalizadas

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

### RF-028 — Gestión de gastos recurrentes

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

### RF-029 — Edición, eliminación y consulta de gastos

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

### RF-030 — Adjuntar soporte del gasto

| **ID** | RF-030 |
| --- | --- |
| **Nombre** | **Adjuntar soporte del gasto** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir adjuntar a un gasto una imagen o un PDF de su soporte (factura o recibo). Esto es útil sobre todo para los gastos potencialmente deducibles.

**Criterios de aceptación y/o reglas de negocio**

RN-030.1 Formatos permitidos: PDF, JPG, PNG. Tamaño máximo: 5 MB por archivo y 3 archivos por gasto.

RN-030.2 El tipo real del archivo se valida por su contenido (tipo MIME / firma), no solo por la extensión. Los archivos rechazados no se guardan.

RN-030.3 Los archivos se guardan cifrados en reposo y solo se descargan con un enlace firmado que vence en 5 minutos.

CA-030.1 Un archivo .exe renombrado como .pdf se rechaza con "Tipo de archivo no permitido".

CA-030.2 Un archivo de 6 MB se rechaza con "El archivo supera 5 MB".

### RF-031 — Importación masiva de movimientos

| **ID** | RF-031 |
| --- | --- |
| **Nombre** | **Importación masiva de movimientos** |
| **Módulo** | MOD-04 Gestión de gastos (aplica también a MOD-03) |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir importar ingresos y gastos desde un archivo CSV o XLSX con una plantilla definida, con vista previa y validación antes de confirmar.

**Criterios de aceptación y/o reglas de negocio**

RN-031.1 El sistema ofrece una plantilla descargable con las columnas tipo (I/G), fecha (DD/MM/AAAA), monto, moneda, categoria, subcategoria, fuente, medio_pago, descripcion.

RN-031.2 Máximo 1.000 filas y 2 MB por archivo.

RN-031.3 La vista previa marca cada fila como válida o con error, e indica la columna y la regla incumplida. El usuario puede importar solo las filas válidas o cancelar.

RN-031.4 Detección de duplicados: si ya existe un registro con la misma fecha, monto, tipo y descripción, la fila se marca "Posible duplicado" y no se importa salvo que el usuario la marque.

RN-031.5 Los registros importados quedan con origen "Importado" e identificador del lote. Se puede deshacer el lote completo dentro de las 24 horas siguientes.

CA-031.1 Al importar un archivo con 10 filas, 2 de ellas con fecha inválida, la vista previa muestra 8 válidas y 2 con error, y al confirmar se crean exactamente 8 registros.

CA-031.2 Al deshacer el lote, se eliminan sus 8 registros y se recalculan los indicadores.

### RF-032 — Cálculo del GMF (4x1000)

| **ID** | RF-032 |
| --- | --- |
| **Nombre** | **Cálculo del Gravamen a los Movimientos Financieros (4x1000)** |
| **Módulo** | MOD-04 Gestión de gastos |
| **Actor principal** | Sistema |
| **Prioridad** | Baja (Could) |

**Descripción**

El sistema debe estimar el GMF de los retiros y pagos hechos desde cuentas bancarias que el Usuario marque como gravadas, teniendo en cuenta la cuenta exenta.

**Criterios de aceptación y/o reglas de negocio**

RN-032.1 GMF = monto del movimiento × tarifa vigente (0,4 %, RF-013).

RN-032.2 Si la cuenta está marcada como exenta, el GMF aplica solo a la parte de los retiros del mes calendario que supera el tope de exención mensual (350 UVT de la vigencia).

RN-032.3 El GMF estimado se registra como gasto automático en "Impuestos > GMF", con relevancia tributaria "Potencialmente deducible – GMF" (50 % deducible, art. 115 ET).

CA-032.1 Si en una cuenta no exenta se registra un pago con débito de $ 1.000.000, se genera un GMF de $ 4.000.

CA-032.2 En una cuenta exenta, con retiros mensuales que superan el tope en $ 500.000, el GMF del mes es $ 2.000.

## MOD-05 — Seguridad social (característica C)

### RF-033 — Cálculo de aportes a seguridad social como independiente o contratista

| **ID** | RF-033 |
| --- | --- |
| **Nombre** | **Cálculo de aportes a seguridad social como independiente o contratista** |
| **Módulo** | MOD-05 Seguridad social |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe calcular el IBC y los aportes mensuales a salud, pensión, FSP y ARL de un Usuario con perfil Independiente o Contratista, a partir de los ingresos de rentas de trabajo del periodo o de un valor ingresado manualmente.

**Criterios de aceptación y/o reglas de negocio**

RN-033.1 Ingreso base del mes = suma de los ingresos brutos con clasificación "Rentas de trabajo" (no asalariado) del mes de cotización, o el valor que el usuario ingrese manualmente para simular.

RN-033.2 IBC = Ingreso base × 40 % (parámetro, RF-013). Se ajusta así: si es menor que 1 SMMLV, el IBC es 1 SMMLV; si es mayor que 25 SMMLV, el IBC es 25 SMMLV. El IBC se redondea al peso superior.

RN-033.3 Si el ingreso base es menor que 1 SMMLV, el sistema informa: "Con ingresos inferiores a 1 SMMLV no existe obligación de cotizar como independiente; el cálculo mostrado es voluntario sobre 1 SMMLV."

RN-033.4 Aportes:

Salud = IBC × 12,5 %

Pensión = IBC × 16 %

FSP = IBC × tarifa de la tabla RN-014.2, según el IBC expresado en SMMLV (0 % si es menor que 4 SMMLV)

ARL = IBC × tarifa de la clase de riesgo del perfil (RN-014.3). Solo se incluye si la afiliación es obligatoria o el usuario la marcó como voluntaria.

RN-033.5 Cada aporte se redondea al múltiplo de $ 100 superior (Decreto 1990 de 2016). Total = suma de los aportes redondeados.

RN-033.6 Un Pensionado no calcula aporte a pensión ni a FSP.

RN-033.7 El resultado se guarda como "Liquidación estimada" del periodo (AAAA-MM), con los parámetros usados, y se puede volver a calcular.

CA-033.2 Con ingreso base = $ 2.000.000 (vigencia 2025), el 40 % es $ 800.000 < SMMLV, así que el IBC es $ 1.423.500.

CA-033.3 Con ingreso base = $ 200.000.000 (vigencia 2025), el 40 % supera 25 SMMLV, así que el IBC es $ 35.587.500 y el FSP es 2 % (25 SMMLV > 20).

CA-033.4 La pantalla de resultados muestra el desglose de cada concepto, los parámetros usados, la vigencia y el aviso de la RNG-08.

### RF-034 — Cálculo de aportes a seguridad social como asalariado

| **ID** | RF-034 |
| --- | --- |
| **Nombre** | **Cálculo de aportes a seguridad social como asalariado** |
| **Módulo** | MOD-05 Seguridad social |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe calcular los aportes que el empleador descuenta del salario de un Usuario Asalariado (salud, pensión y FSP), para estimar el salario neto y registrar esos aportes como ingresos no constitutivos de renta.

**Criterios de aceptación y/o reglas de negocio**

RN-034.1 Datos de entrada: salario mensual* y si es salario integral (del perfil).

RN-034.2 IBC = salario (o 70 % del salario integral), con un mínimo de 1 SMMLV y un máximo de 25 SMMLV.

RN-034.3 Aporte del empleado: salud 4 %, pensión 4 %, FSP según la tabla RN-014.2 si el IBC es de 4 SMMLV o más. El ARL no se le descuenta al empleado.

RN-034.5 El usuario puede aplicar el resultado al ingreso registrado (RF-021) para llenar el campo "aportes descontados".

CA-034.1 Con un salario de $ 6.000.000 (vigencia 2025, 4,21 SMMLV): salud $ 240.000; pensión $ 240.000; FSP 1 % = $ 60.000; neto sin retención $ 5.460.000.

CA-034.2 Con un salario integral de $ 20.000.000: IBC = $ 14.000.000.

### RF-035 — Registro de pagos de aportes (PILA)

| **ID** | RF-035 |
| --- | --- |
| **Nombre** | **Registro de pagos de aportes a seguridad social** |
| **Módulo** | MOD-05 Seguridad social |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir registrar los pagos reales de seguridad social hechos por PILA, compararlos con la liquidación estimada y llevar el histórico anual de aportes para el cálculo de renta.

**Criterios de aceptación y/o reglas de negocio**

RN-035.1 Campos: periodo cotizado* (AAAA-MM), fecha de pago*, número de planilla (opcional, máximo 20 caracteres), valores pagados de salud*, pensión*, FSP, ARL e intereses de mora (si hubo), y soporte (opcional, mismas reglas de RF-030).

RN-035.2 No puede haber dos pagos del mismo periodo, salvo que se marque "Planilla de corrección".

RN-035.3 Al registrar el pago se crea automáticamente un gasto en "Seguridad social", con la subcategoría de cada concepto, sin duplicar.

RN-035.4 Los aportes obligatorios de salud y pensión pagados se acumulan como "Ingreso no constitutivo de renta" del año gravable (RF-043).

RN-035.5 Si el valor pagado difiere de la liquidación estimada en más de $ 1.000 en algún concepto, el sistema muestra la diferencia.

CA-035.1 Al registrar el pago del periodo 2026-03, aparece un gasto de "Seguridad social" con fecha de pago y monto total iguales a los del registro.

CA-035.2 Al intentar registrar otra vez el periodo 2026-03 sin "corrección", el sistema lo rechaza.

### RF-036 — Alerta de vencimiento de pago de seguridad social

| **ID** | RF-036 |
| --- | --- |
| **Nombre** | **Alerta de vencimiento de pago de seguridad social** |
| **Módulo** | MOD-05 Seguridad social |
| **Actor principal** | Sistema |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe avisar a los Usuarios Independientes o Contratistas la fecha límite de pago de sus aportes mensuales, según el calendario (RF-016) y los dos últimos dígitos de su documento.

**Criterios de aceptación y/o reglas de negocio**

RN-036.1 Se envían alertas 5 días y 1 día antes de la fecha límite, si el periodo aún no tiene un pago registrado (RF-035).

RN-036.2 Si llega la fecha límite sin pago registrado, se genera una alerta "Vencido" con la advertencia de posibles intereses de mora.

RN-036.3 Si no hay calendario para la vigencia, no se generan alertas y se le informa al Administrador.

CA-036.1 Un Usuario independiente con vencimiento el 10/04 y sin pago registrado recibe alertas el 05/04 y el 09/04.

CA-036.2 Si registra el pago el 06/04, la alerta del 09/04 no se genera.

## MOD-06 — Inversiones y rentabilidad (característica D)

### RF-037 — Registro de inversión

| **ID** | RF-037 |
| --- | --- |
| **Nombre** | **Registro de inversión** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario registre cada inversión con sus características, para hacerle seguimiento y analizar su rentabilidad.

**Criterios de aceptación y/o reglas de negocio**

RN-037.1 Campos: nombre* (3–60 caracteres), tipo* (CDT, Cuenta de ahorro de alto rendimiento, Fondo de inversión colectiva, Acciones, ETF, Bonos/TES, Fondo voluntario de pensión, Criptoactivo, Inmueble para renta, Negocio/Participación, Otro), entidad o emisor (opcional), moneda*, fecha de apertura*, monto inicial* y nivel de riesgo declarado (Bajo/Medio/Alto).

RN-037.2 Para CDT y Bonos se piden además: tasa pactada (EA, 0–100 %), plazo en días (1–3.650), periodicidad de pago de intereses (al vencimiento, mensual, trimestral, semestral, anual) y fecha de vencimiento (se calcula, pero el usuario puede ajustarla).

RN-037.3 Para Acciones, ETF y Criptoactivos se piden además: símbolo (opcional), cantidad de unidades (> 0, hasta 8 decimales) y precio unitario de compra.

RN-037.4 El monto inicial se registra automáticamente como primer movimiento "Aporte" (RF-038) y la inversión aparece como activo en el patrimonio (RF-061).

RN-037.5 Estado: Activa, Vencida (la marca el Sistema al pasar la fecha de vencimiento) o Liquidada (cuando la retira el usuario).

CA-037.2 Si una inversión de tipo Acciones no tiene cantidad de unidades, se rechaza.

### RF-038 — Registro de movimientos de inversión

| **ID** | RF-038 |
| --- | --- |
| **Nombre** | **Registro de movimientos de inversión** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir registrar los movimientos de cada inversión. Estos flujos son la base del cálculo de rentabilidad.

**Criterios de aceptación y/o reglas de negocio**

RN-038.1 Tipos de movimiento: Aporte, Retiro parcial, Rendimiento o dividendo pagado, Comisión o costo, Retención en la fuente sobre rendimientos, Liquidación total.

RN-038.2 Campos: tipo*, fecha* (igual o posterior a la fecha de apertura y no futura), monto*, unidades (si aplica) y nota.

RN-038.3 Un retiro no puede superar el último valor de mercado registrado. Una "Liquidación total" deja la inversión en estado "Liquidada" con valor actual 0.

RN-038.4 Los rendimientos pagados se registran automáticamente como ingreso (RF-021) con clasificación "Rentas de capital" y la retención informada. Este ingreso no se duplica en el flujo de caja.

CA-038.1 Al registrar un dividendo de $ 300.000, la inversión suma un movimiento "Rendimiento" y se crea un ingreso de $ 300.000 en rentas de capital.

CA-038.2 Un retiro con fecha anterior a la de apertura se rechaza.

### RF-039 — Actualización del valor de mercado

| **ID** | RF-039 |
| --- | --- |
| **Nombre** | **Actualización del valor de mercado de la inversión** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir registrar el valor actual (valor de mercado) de cada inversión en una fecha dada, y guardar el histórico de valoraciones.

**Criterios de aceptación y/o reglas de negocio**

RN-039.2 Para CDT y Bonos, el sistema propone un valor causado = capital × (1 + tasa EA)^(días transcurridos/365), que el usuario puede aceptar o reemplazar.

RN-039.3 La valoración más reciente es el "valor actual" de la inversión en patrimonio, rentabilidad y dashboard.

RN-039.4 Si una inversión activa lleva más de 35 días sin valoración, se muestra el indicador "Valoración desactualizada".

CA-039.1 Con un CDT de $ 10.000.000 al 10 % EA, a los 182 días el valor causado propuesto es $ 10.486.719 ± $ 1.

CA-039.2 El histórico de valoraciones se puede ver en un gráfico de línea por fecha.

### RF-040 — Cálculo de rentabilidad de una inversión

| **ID** | RF-040 |
| --- | --- |
| **Nombre** | **Cálculo de rentabilidad de una inversión** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario / Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe calcular y mostrar los indicadores de rentabilidad de cada inversión a una fecha de corte, a partir de sus movimientos y su valor actual.

**Criterios de aceptación y/o reglas de negocio**

RN-040.1 Definiciones, a la fecha de corte:

ROI (%) = Ganancia total / Σ Aportes × 100

Rentabilidad anualizada (TIR no periódica, XIRR): tasa *r*

RN-040.2 La XIRR se resuelve numéricamente (Newton-Raphson con respaldo por bisección), con tolerancia 1×10⁻⁶ y máximo 100 iteraciones. Si no converge o la inversión tiene menos de 30 días, se muestra "No calculable" y solo se muestra el ROI.

RN-040.3 Los porcentajes se muestran con 2 decimales. Las ganancias en verde y las pérdidas en rojo, con signo (el color no es el único indicador).

CA-040.1 Con un aporte de $ 1.000.000 el 01/01/2025 y valor actual de $ 1.100.000 el 01/01/2026: ganancia = $ 100.000, ROI = 10,00 %, XIRR = 10,00 % ± 0,01 %.

CA-040.3 Con una inflación de vigencia del 5 % y una XIRR del 10 %, la rentabilidad real es 4,76 %.

### RF-041 — Consolidado del portafolio de inversiones

| **ID** | RF-041 |
| --- | --- |
| **Nombre** | **Consolidado del portafolio de inversiones** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe mostrar una vista consolidada de todas las inversiones activas del Usuario, con su composición y su rentabilidad agregada.

**Criterios de aceptación y/o reglas de negocio**

RN-041.1 La vista muestra: valor total del portafolio en COP, capital aportado neto total, ganancia total, ROI del portafolio y XIRR del portafolio (con todos los flujos de todas las inversiones).

RN-041.2 Muestra la distribución porcentual del valor actual por tipo de inversión, por nivel de riesgo y por moneda, en gráficos de torta o barras.

RN-041.3 La tabla de detalle por inversión tiene: nombre, tipo, valor actual, % del portafolio, ROI, XIRR, fecha de última valoración y estado.

RN-041.4 Las inversiones en USD se convierten con la TRM vigente a la fecha de corte (RF-017).

CA-041.1 La suma de los valores actuales de la tabla de detalle es igual al valor total del portafolio.

CA-041.2 La suma de los porcentajes de distribución es 100 % (± 0,01 % por redondeo).

### RF-042 — Simulación y comparación de alternativas de inversión

| **ID** | RF-042 |
| --- | --- |
| **Nombre** | **Simulación y comparación de alternativas de inversión** |
| **Módulo** | MOD-06 Inversiones y rentabilidad |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir simular hasta 4 alternativas de inversión hipotéticas y compararlas en valor final, ganancia y rentabilidad real, sin registrarlas como inversiones.

**Criterios de aceptación y/o reglas de negocio**

RN-042.3 Resultados por alternativa: total aportado, valor final bruto, impuestos, valor final neto, ganancia neta, valor final en pesos de hoy (descontando la inflación de la vigencia) y un gráfico de evolución.

RN-042.4 Las simulaciones se pueden guardar con un nombre (máximo 20 por usuario) y no afectan el patrimonio ni los reportes.

CA-042.1 Una alternativa de $ 10.000.000, sin aportes, al 12 % EA a 12 meses y sin impuestos ni comisión da un valor final de $ 11.200.000 ± $ 1.

CA-042.2 La comparación resalta la alternativa con mayor valor final neto.

## MOD-07 — Declaración de renta (características C y E)

### RF-043 — Consolidación anual de información tributaria

| **ID** | RF-043 |
| --- | --- |
| **Nombre** | **Consolidación anual de información tributaria** |
| **Módulo** | MOD-07 Declaración de renta |
| **Actor principal** | Sistema / Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe consolidar automáticamente, para un año gravable, la información registrada que sirve para evaluar la obligación de declarar y para estimar el impuesto.

**Criterios de aceptación y/o reglas de negocio**

RN-043.1 Conceptos consolidados del 1 de enero al 31 de diciembre del año gravable:

Ingresos brutos totales y por cédula (RF-021, RF-038)

Retenciones en la fuente practicadas

Aportes obligatorios a salud y pensión (RF-034, RF-035)

Consumos con tarjeta de crédito (RN-025.2)

Compras y consumos totales (RN-025.2)

Gastos potencialmente deducibles por subtipo (RN-026.1)

Patrimonio bruto y deudas al 31 de diciembre (RF-063)

RN-043.2 Los valores se muestran en COP y en UVT (valor / UVT del año gravable), con 2 decimales.

RN-043.3 Cada concepto se puede abrir (drill-down) para ver los registros que lo componen.

CA-043.1 El total de ingresos brutos consolidado de 2025 es exactamente la suma de los ingresos brutos (en COP) con fecha en 2025.

CA-043.2 Al abrir "Consumos con tarjeta de crédito" aparecen solo los gastos de 2025 con ese medio de pago.

### RF-044 — Registro de información tributaria complementaria

| **ID** | RF-044 |
| --- | --- |
| **Nombre** | **Registro de información tributaria complementaria** |
| **Módulo** | MOD-07 Declaración de renta |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario complete o corrija, para un año gravable, los valores que el sistema no puede saber del todo con lo registrado (por ejemplo, depósitos y consignaciones bancarias o consumos hechos antes de usar el sistema).

**Criterios de aceptación y/o reglas de negocio**

RN-044.1 Campos por año gravable: total de consignaciones, depósitos o inversiones financieras del año*; ajuste de consumos con tarjeta de crédito; ajuste de compras y consumos; ajuste de ingresos brutos; ajuste de patrimonio bruto al 31/12. Cada ajuste puede ser positivo o negativo.

RN-044.2 Valor final de cada criterio = valor consolidado (RF-043) + ajuste. El valor final no puede ser negativo.

RN-044.3 Cada ajuste exige una nota explicativa (mínimo 10 caracteres) y queda en el historial del año gravable.

CA-044.1 Con consumos con tarjeta consolidados de $ 50.000.000 y un ajuste de + $ 10.000.000, el valor evaluado es $ 60.000.000.

CA-044.2 Un ajuste que deja el valor final negativo se rechaza.

### RF-045 — Evaluación de la obligación de declarar renta

| **ID** | RF-045 |
| --- | --- |
| **Nombre** | **Evaluación de la obligación de declarar renta** |
| **Módulo** | MOD-07 Declaración de renta |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe determinar si el Usuario, persona natural residente, está obligado a presentar la declaración de renta de un año gravable, según los topes de los arts. 592, 593, 594-1 y 594-3 del ET. Debe mostrar el resultado de cada criterio.

**Criterios de aceptación y/o reglas de negocio**

RN-045.1 Criterios evaluados con la UVT del año gravable (valores finales de la RN-044.2). Basta con que se cumpla uno para quedar obligado:

| **#** | **Criterio** | **Condición de obligación** |
| --- | --- | --- |
| C1 | Patrimonio bruto al 31/12 | > 4.500 UVT |
| C2 | Ingresos brutos del año |  |
| C3 | Consumos con tarjeta de crédito | > 1.400 UVT |
| C4 | Compras y consumos totales | > 1.400 UVT |
| C5 | Consignaciones, depósitos o inversiones financieras | > 1.400 UVT |
| C6 | Responsable de IVA (perfil, RN-008.4) | = Sí |

RN-045.2 Resultado: "OBLIGADO A DECLARAR" si se cumple al menos un criterio; "NO OBLIGADO" si no se cumple ninguno. Con resultado "NO OBLIGADO", el sistema informa que la declaración voluntaria es posible.

RN-045.3 Por cada criterio se muestran: el valor en COP, el valor en UVT, el tope en COP y en UVT, el estado (Cumple/No cumple) y el margen restante hasta el tope.

RN-045.4 Si el año gravable está en curso, el resultado se marca "Preliminar" y se proyecta a 31/12 con el promedio mensual de los meses transcurridos.

RN-045.5 Si el usuario no ha registrado el campo de consignaciones (RN-044.1), el resultado se muestra como "Incompleto" con la advertencia correspondiente.

RN-045.6 La evaluación se puede guardar como constancia en PDF (RF-069), con fecha de generación, parámetros usados y el aviso de la RNG-08.

CA-045.1 Con UVT 2025 = $ 49.799, ingresos brutos de $ 69.718.600 (exactamente 1.400 UVT) y los demás criterios por debajo del tope, el resultado es "OBLIGADO A DECLARAR" por C2.

CA-045.2 Con UVT 2025 = $ 49.799, consumos con tarjeta de crédito de $ 69.718.600 (exactamente 1.400 UVT) y los demás criterios por debajo, C3 queda "No cumple" (la condición es "mayor que").

CA-045.3 Con un patrimonio bruto de $ 224.095.501 (> 4.500 UVT = $ 224.095.500), C1 queda "Cumple".

CA-045.4 Un Usuario con perfil "Responsable de IVA = Sí" queda "OBLIGADO A DECLARAR" sin importar los demás criterios.

### RF-046 — Estimación del impuesto de renta (cédula general simplificada)

| **ID** | RF-046 |
| --- | --- |
| **Nombre** | **Estimación del impuesto de renta (cédula general simplificada)** |
| **Módulo** | MOD-07 Declaración de renta |
| **Actor principal** | Usuario |
| **Prioridad** | Baja (Could) |

**Descripción**

El sistema debe estimar el impuesto de renta de la cédula general (rentas de trabajo, de capital y no laborales) y el saldo a pagar o a favor después de las retenciones, con un modelo simplificado y explícito.

**Criterios de aceptación y/o reglas de negocio**

RN-046.1 Procedimiento (valores anuales del año gravable):

Ingresos brutos de la cédula general (RF-043).

**Renta líquida**.

Rentas exentas y deducciones, informadas o consolidadas: deducción por dependientes, intereses de vivienda, medicina prepagada, 50 % del GMF, aportes voluntarios a pensión o AFC.

Suma de los puntos 3 y 4, limitada al menor valor entre el 40 % de la renta líquida y 1.340 UVT.

**Renta líquida gravable**

Se convierte a UVT y se aplica la tabla RN-014.1. El impuesto en UVT × UVT da el impuesto en COP.

**Saldo a pagar** (si es positivo) o **saldo a favor** (si es negativo).

RN-046.2 Los valores en COP del resultado se redondean al múltiplo de $ 1.000 más cercano (art. 577 ET).

RN-046.3 La pantalla lista de forma explícita lo que la estimación no considera: cédulas de pensiones y de dividendos, ganancias ocasionales, rentas de fuente extranjera, descuentos tributarios, anticipo de renta, deducción del 1 % por compras con factura electrónica y otros beneficios especiales.

RN-046.4 Cada paso se muestra con su valor intermedio, para que se pueda revisar o auditar.

CA-046.1 Con una renta líquida gravable de 2.000 UVT y UVT = $ 49.799, el impuesto estimado es 200 UVT = $ 9.959.800, que se redondea a $ 9.960.000.

CA-046.2 Si las deducciones y exenciones superan el 40 % de la renta líquida, el sistema aplica el límite y muestra el valor no aprovechado.

CA-046.3 Con retenciones mayores que el impuesto, el resultado se muestra como "Saldo a favor".

### RF-047 — Recordatorio de vencimiento de la declaración de renta

| **ID** | RF-047 |
| --- | --- |
| **Nombre** | **Recordatorio de vencimiento de la declaración de renta** |
| **Módulo** | MOD-07 Declaración de renta |
| **Actor principal** | Sistema |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe recordar a los Usuarios obligados a declarar la fecha límite de presentación, según los dos últimos dígitos de su documento y el calendario de la vigencia (RF-016).

**Criterios de aceptación y/o reglas de negocio**

RN-047.1 Recordatorios 30, 15, 5 y 1 día antes del vencimiento, solo si la última evaluación (RF-045) es "OBLIGADO" o "Incompleto".

RN-047.2 El Usuario puede marcar "Declaración presentada" con su fecha. Desde ese momento no recibe más recordatorios de ese año gravable.

CA-047.1 Un Usuario obligado con dígitos "37" recibe los cuatro recordatorios en las fechas que corresponden a su rango.

CA-047.2 Un Usuario "NO OBLIGADO" no recibe recordatorios.

## MOD-08 — Créditos (característica F)

### RF-048 — Registro de crédito

| **ID** | RF-048 |
| --- | --- |
| **Nombre** | **Registro de crédito** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario registre sus créditos vigentes con sus condiciones financieras, para generar el plan de pagos, controlar el saldo y llevarlo a los pasivos.

**Criterios de aceptación y/o reglas de negocio**

RN-048.3 Si el crédito comenzó antes de registrarse en el sistema, el usuario puede indicar el número de cuotas ya pagadas y el saldo actual. El sistema toma ese saldo como punto de partida y lo concilia con la tabla teórica (diferencia máxima tolerada: 1 %; si es mayor, se muestra una advertencia).

RN-048.4 Al registrarse, el crédito se agrega automáticamente como pasivo (RF-062) por su saldo de capital.

CA-048.1 Una tasa del 1,5 % NMV se guarda también como 19,56 % EA (2 decimales).

CA-048.2 Un plazo de 0 o mayor que 360 se rechaza.

### RF-049 — Generación de la tabla de amortización

| **ID** | RF-049 |
| --- | --- |
| **Nombre** | **Generación de la tabla de amortización** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe generar la tabla de amortización de cada crédito registrado, según su sistema de amortización.

**Criterios de aceptación y/o reglas de negocio**

RN-049.2 Abono constante a capital (alemán): capital por cuota = P / n; interés = saldo inicial × i; cuota = capital + interés.

RN-049.3 Columnas: n.º de cuota, fecha de pago, saldo inicial, cuota (capital + interés), interés, abono a capital, seguros y cargos, pago total, saldo final y estado (Pendiente/Pagada/Vencida).

RN-049.4 Los valores se redondean al peso. La diferencia acumulada por redondeo se ajusta en la última cuota, de modo que el saldo final sea exactamente $ 0.

RN-049.5 Totales de la tabla: total de intereses, total de capital, total de seguros y costo total del crédito.

CA-049.1 Un crédito de $ 10.000.000 a 12 cuotas con tasa mensual del 1 % (francés) tiene una cuota de $ 888.488, la cuota 12 deja saldo final $ 0 y el total de intereses es $ 661.855 ± $ 12.

CA-049.2 La suma de la columna "abono a capital" es igual al monto desembolsado.

CA-049.3 La tabla se puede exportar a XLSX y PDF (RF-069).

### RF-050 — Registro de pagos de cuotas

| **ID** | RF-050 |
| --- | --- |
| **Nombre** | **Registro de pagos de cuotas de crédito** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir registrar el pago de las cuotas de un crédito, actualizar su estado y su saldo, y reflejar el pago en los gastos y en el pasivo.

**Criterios de aceptación y/o reglas de negocio**

RN-050.1 Campos: cuota(s) pagada(s)*, fecha de pago*, valor pagado* e intereses de mora (si hubo).

RN-050.2 Al registrar el pago, la cuota pasa a "Pagada", el pasivo baja en el abono a capital y se crea un gasto en "Obligaciones financieras" por el valor pagado (RN-025.3).

RN-050.3 Si la fecha de pago es posterior a la fecha de la cuota, el pago se marca "Pagada con atraso".

RN-050.4 El Sistema marca como "Vencida" toda cuota no pagada al día siguiente de su fecha de pago y genera una alerta (RF-058).

RN-050.5 Si el valor pagado supera la cuota, el sistema pregunta si el excedente es un abono extraordinario (RF-052).

CA-050.1 Al pagar la cuota 3, el saldo del pasivo queda igual al saldo final de la cuota 3 de la tabla.

CA-050.2 Al día siguiente de vencer una cuota sin pago, su estado aparece como "Vencida" y hay una alerta en el centro de notificaciones.

### RF-051 — Simulador de crédito

| **ID** | RF-051 |
| --- | --- |
| **Nombre** | **Simulador de crédito** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir simular un crédito hipotético (monto, tasa, plazo, sistema de amortización) y mostrar la cuota, la tabla de amortización, el costo total y el impacto en la capacidad de endeudamiento, sin registrarlo.

**Criterios de aceptación y/o reglas de negocio**

RN-051.1 Se usan las mismas fórmulas de RF-048 y RF-049.

RN-051.3 Se pueden comparar hasta 3 escenarios lado a lado (cuota, total de intereses, costo total).

RN-051.4 Se muestra el indicador de endeudamiento proyectado (RF-053) si se tomara el crédito simulado.

RN-051.5 El usuario puede convertir una simulación en un crédito registrado (RF-048) con los datos ya llenos.

CA-051.1 En el modo (b), con una cuota de $ 888.488, i = 1 % y n = 12, el monto máximo es $ 10.000.000 ± $ 5.

CA-051.2 Una simulación no aparece en pasivos, gastos ni dashboard.

### RF-052 — Simulación y registro de abono extraordinario

| **ID** | RF-052 |
| --- | --- |
| **Nombre** | **Simulación y registro de abono extraordinario a capital** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir simular y registrar un abono extraordinario a capital de un crédito, con la opción de reducir el plazo o reducir la cuota, y mostrar el ahorro en intereses (prepago sin penalidad, Ley 1555 de 2012).

**Criterios de aceptación y/o reglas de negocio**

RN-052.1 Campos: fecha del abono*, monto* (mayor que 0 y menor o igual al saldo de capital) y opción* (Reducir plazo / Reducir cuota).

RN-052.3 Antes de confirmar, se muestra una comparación: cuota antes y después, plazo antes y después, total de intereses antes y después, y ahorro en intereses.

RN-052.4 Al confirmar, la tabla se regenera desde la fecha del abono, las cuotas pagadas se conservan y el pasivo se actualiza.

CA-052.1 Un abono igual al saldo total deja el crédito en estado "Cancelado" y el pasivo en $ 0.

CA-052.2 Con "Reducir plazo", la cuota posterior es igual a la anterior (salvo la última) y el número de cuotas disminuye.

### RF-053 — Indicador de endeudamiento

| **ID** | RF-053 |
| --- | --- |
| **Nombre** | **Indicador de endeudamiento** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe calcular y mostrar el nivel de endeudamiento del Usuario como proporción de sus ingresos, con una clasificación por semáforo.

**Criterios de aceptación y/o reglas de negocio**

RN-053.1 Endeudamiento (%) = Σ pagos totales mensuales de los créditos activos del mes actual / promedio de ingresos netos mensuales de los últimos 3 meses completos × 100.

RN-053.3 Si no hay ingresos registrados en los últimos 3 meses, se muestra "No calculable: registre sus ingresos".

RN-053.4 También se muestra la razón deuda/patrimonio = pasivos totales / activos totales × 100 (RF-063).

CA-053.1 Con cuotas de $ 1.500.000 e ingresos promedio de $ 4.000.000, el indicador es 37,50 % en Amarillo.

CA-053.2 Al registrar un crédito nuevo, el indicador se actualiza en el dashboard sin recargar manualmente.

### RF-054 — Alerta de tasa de usura

| **ID** | RF-054 |
| --- | --- |
| **Nombre** | **Alerta de tasa de usura** |
| **Módulo** | MOD-08 Créditos |
| **Actor principal** | Sistema |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe advertir cuando la tasa EA de un crédito registrado o simulado supere la tasa de usura vigente que certifica la Superintendencia Financiera para su modalidad.

**Criterios de aceptación y/o reglas de negocio**

RN-054.1 El Administrador registra la tasa de usura por modalidad (Consumo y ordinario, Microcrédito, Consumo de bajo monto) y por periodo de vigencia, como parámetro de RF-013.

RN-054.2 La comparación usa la tasa de usura vigente en la fecha de desembolso (crédito registrado) o en la fecha actual (simulación), según la modalidad del tipo de crédito.

RN-054.3 Mensaje: "La tasa de este crédito (X % EA) supera la tasa de usura vigente para su modalidad (Y % EA). Verifique las condiciones con la entidad."

RN-054.4 Si no hay tasa de usura registrada para el periodo, no se compara y se muestra "Sin tasa de usura de referencia para el periodo".

CA-054.1 Un crédito de consumo con una tasa 0,01 puntos porcentuales por encima de la usura vigente muestra la advertencia. Uno con una tasa igual a la usura no la muestra.

## MOD-09 — Presupuestos, metas y alertas (característica G)

### RF-055 — Creación de presupuesto

| **ID** | RF-055 |
| --- | --- |
| **Nombre** | **Creación de presupuesto** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir que el Usuario defina presupuestos de gasto por categoría (o subcategoría) para un periodo.

**Criterios de aceptación y/o reglas de negocio**

RN-055.1 Campos: periodo* (mensual por defecto; también semanal, trimestral o anual), fecha de inicio*, líneas de presupuesto* (categoría o subcategoría + monto asignado > 0), repetir automáticamente en periodos siguientes (sí/no) y traslado de saldo no usado (sí/no).

RN-055.2 Una categoría solo puede tener una línea por periodo. Si se presupuesta una subcategoría, su categoría padre no puede tener línea propia en ese periodo (se evita el doble conteo).

RN-055.3 Se puede copiar el presupuesto del periodo anterior o generar una propuesta con el promedio de gasto real de los últimos 3 meses por categoría, redondeado al múltiplo de $ 10.000 superior.

RN-055.4 El sistema muestra el presupuesto total frente al ingreso promedio de los últimos 3 meses. Si el presupuesto supera el ingreso, advierte "El presupuesto supera tus ingresos promedio en $ X".

RN-055.5 Si el traslado de saldo está activo, el saldo no gastado de una línea se suma al monto de la misma línea del periodo siguiente. Si hubo sobregasto, no se traslada.

CA-055.1 Al crear un presupuesto mensual para octubre de 2026 con "Alimentación $ 1.200.000", la línea aparece con ejecución 0 % si no hay gastos.

CA-055.2 Al intentar crear una segunda línea de "Alimentación" en el mismo periodo, el sistema la rechaza.

### RF-056 — Seguimiento de la ejecución presupuestal

| **ID** | RF-056 |
| --- | --- |
| **Nombre** | **Seguimiento de la ejecución presupuestal** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe mostrar en tiempo real cuánto se ha ejecutado de cada línea presupuestal y del presupuesto total del periodo.

**Criterios de aceptación y/o reglas de negocio**

RN-056.3 Cuando un periodo cierra, el Sistema guarda un resumen inmutable (asignado, gastado, diferencia) para comparar históricos.

CA-056.1 Con $ 1.200.000 asignados y $ 1.000.000 gastados, la línea muestra 83,33 %, "En riesgo" y $ 200.000 disponibles.

### RF-057 — Alertas de gasto por umbral

| **ID** | RF-057 |
| --- | --- |
| **Nombre** | **Alertas de gasto por umbral** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe generar alertas cuando la ejecución de una línea presupuestal o del presupuesto total llegue a umbrales configurados, y también cuando se detecten gastos atípicos.

**Criterios de aceptación y/o reglas de negocio**

RN-057.1 Umbrales por defecto: 80 % y 100 % de ejecución. El Usuario puede configurar hasta 3 umbrales por línea, entre 50 % y 150 %.

RN-057.2 Cada umbral genera como máximo una alerta por línea y periodo, aunque se registren más gastos.

RN-057.3 Alerta de proyección: si a mitad del periodo o después la proyección al cierre (RN-056.1) supera el 110 % del asignado, se genera una alerta preventiva, una vez por periodo.

RN-057.4 Alerta de gasto atípico: un gasto individual mayor que 3 veces el promedio de los gastos de su subcategoría en los últimos 6 meses (con al menos 5 registros previos) genera una alerta informativa.

RN-057.5 La alerta contiene: tipo, línea o categoría, % alcanzado, monto asignado, monto gastado, fecha y hora, y un enlace al detalle.

CA-057.1 Con $ 1.000.000 asignados, un gasto que lleva la ejecución de 75 % a 85 % genera una alerta de 80 %. Un gasto posterior que la lleva a 90 % no genera otra.

CA-057.2 Un gasto que lleva la ejecución de 70 % a 105 % genera las alertas de 80 % y de 100 %.

### RF-058 — Centro de notificaciones

| **ID** | RF-058 |
| --- | --- |
| **Nombre** | **Centro de notificaciones** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Usuario / Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe reunir en un centro de notificaciones todas las alertas y recordatorios del Usuario, y entregarlos por los canales que él habilite.

**Criterios de aceptación y/o reglas de negocio**

RN-058.1 Canales: dentro de la aplicación (obligatorio, siempre activo) y correo electrónico (opcional, por tipo de notificación).

RN-058.2 Tipos: presupuesto (RF-057), recurrentes pendientes (RF-022/RF-028), vencimiento de seguridad social (RF-036), vencimiento de renta (RF-047), cuota vencida (RF-050), usura (RF-054), metas (RF-060), valoración desactualizada (RN-039.4), sistema (cambios de política, exportación lista).

RN-058.3 Cada notificación tiene un estado (No leída / Leída / Archivada) y una prioridad (Informativa / Advertencia / Crítica).

RN-058.4 El ícono de notificaciones muestra cuántas hay sin leer (si son más de 99, muestra "99+").

RN-058.5 Las notificaciones archivadas se borran a los 180 días.

CA-058.1 El Usuario puede marcar una o todas como leídas, y filtrar por tipo y estado.

CA-058.2 Si el usuario desactivó el correo para "Presupuesto", una alerta de presupuesto aparece solo dentro de la aplicación.

### RF-059 — Configuración de preferencias de notificación

| **ID** | RF-059 |
| --- | --- |
| **Nombre** | **Configuración de preferencias de notificación** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir que el Usuario elija, por tipo de notificación, si la recibe por correo y con cuánta anticipación recibe los recordatorios.

**Criterios de aceptación y/o reglas de negocio**

RN-059.1 Hay una matriz de tipos de notificación (RN-058.2) × canal (correo sí/no).

RN-059.2 Anticipación de los recordatorios de recurrentes: 0–7 días (3 por defecto).

RN-059.3 Resumen opcional por correo: semanal (lunes 07:00) o mensual (día 1, 07:00), con ingresos, gastos, ejecución presupuestal y alertas del periodo.

RN-059.4 Las notificaciones de seguridad (bloqueo, cambio de contraseña, supresión) siempre se envían por correo y no se pueden desactivar.

CA-059.1 Los cambios en las preferencias aplican a las notificaciones que se generen después de guardar.

CA-059.2 En la matriz, las notificaciones de seguridad aparecen deshabilitadas para edición, con la explicación.

### RF-060 — Metas de ahorro

| **ID** | RF-060 |
| --- | --- |
| **Nombre** | **Metas de ahorro** |
| **Módulo** | MOD-09 Presupuestos, metas y alertas |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir que el Usuario defina metas de ahorro con un monto objetivo y una fecha límite, registre aportes a ellas y vea su avance y el ahorro mensual que necesita.

**Criterios de aceptación y/o reglas de negocio**

RN-060.4 Notificaciones al llegar al 50 %, al 100 % y 30 días antes de la fecha objetivo si el avance es < 80 %.

CA-060.1 Una meta de $ 6.000.000 a 12 meses con $ 0 acumulado muestra un ahorro mensual requerido de $ 500.000.

CA-060.2 Al registrar un aporte que completa el 100 %, la meta pasa a "Lograda" y se genera una notificación.

## MOD-10 — Patrimonio y proyección patrimonial

### RF-061 — Registro de activos

| **ID** | RF-061 |
| --- | --- |
| **Nombre** | **Registro de activos** |
| **Módulo** | MOD-10 Patrimonio y proyección patrimonial |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir registrar los activos del Usuario y su valor, para calcular el patrimonio bruto y el neto.

**Criterios de aceptación y/o reglas de negocio**

RN-061.1 Tipos de activo: Efectivo y cuentas bancarias, Inversiones (las alimenta RF-037 automáticamente), Inmuebles, Vehículos, Cuentas por cobrar, Otros bienes.

RN-061.2 Campos: nombre*, tipo*, valor actual*, fecha de valoración*, moneda, valor fiscal (opcional: avalúo catastral o costo fiscal, que se usa en el patrimonio bruto para renta si se registra) y descripción.

RN-061.3 Las inversiones de MOD-06 aparecen como activos de solo lectura en este módulo; se editan únicamente desde MOD-06.

RN-061.4 Cada actualización de valor guarda un histórico (fecha, valor) para la evolución patrimonial.

RN-061.5 Para cuentas bancarias, el usuario puede activar "Actualizar saldo con movimientos": así el saldo cambia con cada ingreso o gasto que se asocie a esa cuenta.

CA-061.1 Al registrar un inmueble de $ 300.000.000, el patrimonio bruto sube en esa misma cantidad.

CA-061.2 Una inversión de MOD-06 aparece en activos con su valor actual, y la opción de editarla desde aquí no está disponible.

### RF-062 — Registro de pasivos

| **ID** | RF-062 |
| --- | --- |
| **Nombre** | **Registro de pasivos** |
| **Módulo** | MOD-10 Patrimonio y proyección patrimonial |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe registrar los pasivos del Usuario. Los créditos de MOD-08 se integran automáticamente y también se pueden registrar otras deudas.

**Criterios de aceptación y/o reglas de negocio**

RN-062.1 Los créditos de MOD-08 aparecen como pasivos de solo lectura por su saldo de capital vigente.

RN-062.2 Otras deudas (por ejemplo, préstamos de familiares o cuentas por pagar): nombre*, acreedor, saldo*, fecha de corte* y fecha de vencimiento (opcional).

RN-062.3 Cada actualización de saldo guarda un histórico.

CA-062.1 Después de pagar una cuota (RF-050), el pasivo del crédito baja exactamente en el abono a capital.

### RF-063 — Cálculo del patrimonio neto

| **ID** | RF-063 |
| --- | --- |
| **Nombre** | **Cálculo del patrimonio neto** |
| **Módulo** | MOD-10 Patrimonio y proyección patrimonial |
| **Actor principal** | Sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe calcular el patrimonio bruto, los pasivos y el patrimonio neto del Usuario en la fecha actual o en una fecha de corte, y mostrar su evolución.

**Criterios de aceptación y/o reglas de negocio**

RN-063.2 Se calculan dos versiones: "Valor comercial" (valor actual) y "Valor fiscal" (usa el valor fiscal si está registrado; si no, el comercial). El valor fiscal al 31/12 es el que usa RF-043.

RN-063.3 Liquidez = (efectivo + inversiones con liquidez inmediata) / gastos mensuales promedio de los últimos 6 meses. Se expresa en "meses de cobertura" con 1 decimal.

RN-063.4 Gráfico de evolución mensual del patrimonio neto de los últimos 12 meses, con el último día de cada mes como fecha de corte.

RN-063.5 Las monedas distintas de COP se convierten con la TRM de la fecha de corte.

CA-063.1 Con activos de $ 350.000.000 y pasivos de $ 120.000.000, el patrimonio neto es $ 230.000.000.

CA-063.2 El patrimonio bruto fiscal al 31/12/2025 coincide con el valor que usa C1 en RF-045 (antes de ajustes).

### RF-064 — Proyección patrimonial

| **ID** | RF-064 |
| --- | --- |
| **Nombre** | **Proyección patrimonial** |
| **Módulo** | MOD-10 Patrimonio y proyección patrimonial |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe proyectar la evolución del patrimonio neto del Usuario en un horizonte definido, con tres escenarios (pesimista, base y optimista), a partir de su patrimonio actual, su capacidad de ahorro y las rentabilidades esperadas.

**Criterios de aceptación y/o reglas de negocio**

RN-064.1 Datos de entrada:

Horizonte: 1 a 40 años (5 por defecto)

Patrimonio neto inicial: RF-063 (editable)

Crecimiento anual del ahorro: 0 % a 20 % (por defecto, la inflación de la vigencia)

Inflación anual esperada: por defecto la de la vigencia

RN-064.3 Los créditos vigentes reducen el pasivo según su tabla de amortización. Al terminar un crédito, el monto de su cuota se suma al ahorro mensual si el usuario activa "Reinvertir cuotas liberadas".

RN-064.4 Resultados: tabla anual por escenario (patrimonio nominal y patrimonio real en pesos de hoy = nominal / (1 + inflación)^años), gráfico de líneas de los 3 escenarios y el año en que se alcanza una meta de patrimonio opcional.

RN-064.5 Si el ahorro mensual es negativo, el sistema lo advierte: "Tu gasto supera tus ingresos; la proyección muestra un patrimonio decreciente."

RN-064.6 Las proyecciones se pueden guardar (máximo 10) con fecha y supuestos, para compararlas después con el patrimonio real.

CA-064.1 Con patrimonio inicial de $ 0, ahorro de $ 1.000.000 mensual sin crecimiento, 0 % de rentabilidad, sin deudas y horizonte de 1 año, el patrimonio final es $ 12.000.000.

CA-064.2 Con patrimonio inicial de $ 100.000.000, sin ahorro, 12 % EA y horizonte de 1 año, el patrimonio final es $ 112.000.000 ± $ 1.

CA-064.3 Con inflación del 5 %, un patrimonio nominal de $ 105.000.000 a 1 año corresponde a un patrimonio real de $ 100.000.000.

## MOD-11 — Reportes y dashboards (característica H)

### RF-065 — Dashboard principal

| **ID** | RF-065 |
| --- | --- |
| **Nombre** | **Dashboard principal** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe mostrar, después de iniciar sesión, un dashboard con los indicadores clave de las finanzas del Usuario y gráficos interactivos, para el periodo seleccionado.

**Criterios de aceptación y/o reglas de negocio**

RN-065.1 Selector de periodo: mes actual (por defecto), mes anterior, trimestre, año en curso o rango personalizado.

RN-065.2 Indicadores (tarjetas):

Ingresos netos del periodo

Gastos del periodo

Tasa de ahorro = balance / ingresos netos × 100

Patrimonio neto actual y variación frente al mes anterior

Endeudamiento (RF-053) con semáforo

Ejecución presupuestal total (RF-056)

Valor del portafolio y su ROI (RF-041)

Próximas obligaciones: cuotas, aportes y vencimientos de los próximos 15 días

RN-065.3 Gráficos:

Ingresos frente a gastos de los últimos 12 meses (barras agrupadas)

Distribución de gastos por categoría en el periodo (barras horizontales o torta, con un máximo de 8 categorías y "Otras")

Gastos fijos frente a variables y esenciales frente a discrecionales

Evolución del patrimonio neto (línea)

RN-065.4 Al hacer clic en un indicador o en un segmento de un gráfico, se abre el listado filtrado correspondiente (drill-down).

RN-065.5 Cada indicador muestra su variación porcentual frente al periodo anterior equivalente.

RN-065.6 Si no hay datos, cada componente muestra un estado vacío con una acción sugerida (por ejemplo, "Registra tu primer ingreso").

CA-065.1 La tarjeta "Gastos del periodo" muestra el mismo valor que el total del listado de gastos (RF-029) con el mismo filtro de fechas.

CA-065.2 Al cambiar el periodo, todos los componentes se actualizan de forma coherente.

### RF-066 — Reporte de flujo de caja

| **ID** | RF-066 |
| --- | --- |
| **Nombre** | **Reporte de flujo de caja** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe generar un reporte de flujo de caja por periodos (mensual por defecto), con ingresos, egresos y saldo, tanto real como proyectado.

**Criterios de aceptación y/o reglas de negocio**

RN-066.2 Modo proyectado: incluye los próximos 1 a 12 meses con los ingresos y gastos recurrentes programados (RF-022, RF-028) y las cuotas de crédito pendientes (RF-049).

RN-066.3 Los periodos proyectados se distinguen de los reales en la tabla y en el gráfico.

RN-066.4 Agrupación: semanal, mensual, trimestral o anual.

CA-066.1 El flujo neto de cada mes real es igual a los ingresos netos menos los gastos de ese mes según el dashboard.

CA-066.2 Un ingreso recurrente de $ 3.000.000 mensual aparece en cada mes proyectado.

### RF-067 — Reporte de gastos por dimensión

| **ID** | RF-067 |
| --- | --- |
| **Nombre** | **Reporte de gastos por dimensión** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe generar un reporte analítico de gastos agrupado por una o dos dimensiones de RF-026 (tabla dinámica), con totales, porcentajes y promedios.

**Criterios de aceptación y/o reglas de negocio**

RN-067.1 Dimensión principal y dimensión secundaria (opcional), a elegir entre: categoría, subcategoría, naturaleza, necesidad, medio de pago, relevancia tributaria, etiqueta y mes.

RN-067.2 Métricas por grupo: total, % del total, número de transacciones, promedio por transacción y promedio mensual.

RN-067.3 Top 10 de comercios o beneficiarios por monto en el periodo.

CA-067.1 La suma de los totales por grupo es igual al total de gastos del periodo filtrado.

CA-067.2 Si un gasto tiene varias etiquetas, en el agrupamiento por etiqueta cuenta en cada una de ellas. El reporte lo advierte con la nota "Un gasto puede tener múltiples etiquetas; los totales por etiqueta no son sumables".

### RF-068 — Reporte tributario anual

| **ID** | RF-068 |
| --- | --- |
| **Nombre** | **Reporte tributario anual** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe generar un reporte anual que reúna la información útil para preparar la declaración de renta: consolidado tributario, evaluación de obligación, aportes a seguridad social, patrimonio al 31/12 y, si existe, la estimación del impuesto.

**Criterios de aceptación y/o reglas de negocio**

RN-068.1 Secciones: (1) datos del contribuyente (sin el número completo del documento: solo los últimos 4 dígitos); (2) consolidado (RF-043) con ajustes (RF-044); (3) evaluación de obligación (RF-045); (4) aportes pagados por mes (RF-035); (5) ingresos por cédula y retenciones; (6) gastos potencialmente deducibles por subtipo; (7) activos y pasivos al 31/12 en valor fiscal; (8) estimación del impuesto (RF-046), si se generó.

RN-068.2 El reporte indica la UVT usada, la fecha de generación y el aviso de la RNG-08.

CA-068.1 Los valores del reporte coinciden con los de las pantallas de origen para el mismo año gravable.

CA-068.2 Se puede exportar a PDF y XLSX (RF-069).

### RF-069 — Exportación de reportes

| **ID** | RF-069 |
| --- | --- |
| **Nombre** | **Exportación de reportes** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe permitir exportar los listados y reportes a PDF y XLSX (y CSV para los listados), conservando los filtros aplicados.

**Criterios de aceptación y/o reglas de negocio**

RN-069.1 Reportes exportables: listados de ingresos y gastos, flujo de caja, gastos por dimensión, portafolio, tabla de amortización, evaluación de renta, reporte tributario anual, patrimonio y proyección patrimonial.

RN-069.2 El PDF incluye: nombre del sistema, título del reporte, nombre del usuario, periodo y filtros aplicados, fecha y hora de generación, numeración "Página X de Y" y, si aplica, el aviso de la RNG-08.

RN-069.3 El XLSX guarda los montos como valores numéricos (no texto), con formato de moneda, para que el usuario pueda hacer cálculos.

RN-069.4 Límite: 50.000 filas por exportación. Si se supera, el sistema pide ajustar los filtros.

CA-069.1 El total de un XLSX exportado, sumado en la hoja de cálculo, es igual al total que muestra la pantalla.

### RF-070 — Comparativo entre periodos

| **ID** | RF-070 |
| --- | --- |
| **Nombre** | **Comparativo entre periodos** |
| **Módulo** | MOD-11 Reportes y dashboards |
| **Actor principal** | Usuario |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe permitir comparar dos periodos de igual duración (por ejemplo, octubre 2026 frente a octubre 2025, o el T3 frente al T2) en ingresos, gastos por categoría, ahorro y ejecución presupuestal.

**Criterios de aceptación y/o reglas de negocio**

RN-070.2 Se resaltan las 5 categorías con mayor aumento y las 5 con mayor disminución.

RN-070.3 Los dos periodos deben tener el mismo número de días (± 3 días para comparar meses de distinta longitud).

CA-070.1 Si Alimentación tuvo $ 1.000.000 en A y $ 1.200.000 en B, el sistema muestra + $ 200.000 y + 20,00 %.

CA-070.2 Una categoría sin gasto en A y con gasto en B muestra "N/A" en la variación porcentual y aparece como "Nueva".

| **Característica (guía del proyecto)** | **Requisitos funcionales** |
| --- | --- |
| A. Gestión de ingresos múltiples | RF-020, RF-021, RF-022, RF-023, RF-024, RF-031, RF-017 |
| B. Clasificación de gastos multidimensional | RF-025, RF-026, RF-027, RF-028, RF-029, RF-030, RF-031, RF-032 |
| C. Seguridad social y obligaciones fiscales | RF-033, RF-034, RF-035, RF-036, RF-013, RF-014, RF-016, RF-043 |
| D. Inversiones y rentabilidad | RF-037, RF-038, RF-039, RF-040, RF-041, RF-042 |
| E. Obligación de declaración de renta | RF-043, RF-044, RF-045, RF-046, RF-047, RF-068 |
| F. Créditos y simulación de pagos | RF-048, RF-049, RF-050, RF-051, RF-052, RF-053, RF-054 |
| G. Presupuestos y alertas de gasto | RF-055, RF-056, RF-057, RF-058, RF-059, RF-060 |
| H. Reportes y dashboards dinámicos | RF-065, RF-066, RF-067, RF-068, RF-069, RF-070 |
| Proyección patrimonial (eje problémico) | RF-061, RF-062, RF-063, RF-064 |
| Transversal: seguridad, datos personales y administración | RF-001 a RF-019 |
| I. Otros según necesidades del cliente | Se gestionan mediante solicitudes de cambio (sección 6) |

# 5. Resumen por prioridad

| **Prioridad** | **Cantidad** | **Requisitos** |
| --- | --- | --- |
| Alta (Must) | 53 | RF-001–010, 013–015, 019–029, 033–035, 037–041, 043–045, 048–051, 053, 055–058, 061–069 |
| Media (Should) | 14 | RF-011, 016, 017, 018, 030, 031, 036, 042, 047, 052, 054, 059, 060, 070 |
| Baja (Could) | 3 | RF-012, 032, 046 |
| **Total** | **70** |  |

# 6. Gestión de cambios a los requisitos

Todo cambio solicitado por el cliente (característica I y "Cambios de cliente" del Corte 2) se registra como Solicitud de Cambio (SC-NNN) con: descripción, justificación, RF afectados, impacto en alcance, tiempo y costo, prioridad MoSCoW y decisión (Aprobada / Rechazada / Aplazada).

Un RF aprobado solo se modifica mediante una SC aprobada por el Product Owner. Cada modificación sube la versión del documento (1.x para ajustes, 2.0 para cambios de alcance) y se registra en el historial de versiones.

Cuando se aprueba una SC, se actualiza la matriz de trazabilidad (sección 4) y los casos de prueba asociados.

# 7. Aprobación

| **Rol** | **Nombre** | **Firma** | **Fecha** |
| --- | --- | --- | --- |
| Ingeniera de Requisitos / Analista de Negocio |  |  |  |
| Product Owner / Cliente |  |  |  |
| Scrum Master |  |  |  |
| Aseguramiento de Calidad |  |  |  |
