**ESPECIFICACIÓN DE REQUISITOS NO FUNCIONALES**

Sistema Web de Control Financiero Personal con Análisis de Rentabilidad y Proyección Patrimonial (SCFP)

Código: SCFP-ERS-RNF  |  Versión: 1.0  |  Estado: En revisión

Elaborado por: Derly Q— Ingeniera de Requisitos / Analista de Negocio

Docente: César Augusto Granada Muñoz

Octubre de 2026

**Tabla de contenido**

# Control del documento

| **Campo** | **Valor** |
| --- | --- |
| Código del documento | SCFP-ERS-RNF |
| Versión | 1.0 (borrador para revisión) |
| Fecha | 20/10/2026 |
| Estado | En revisión — pendiente de aprobación del cliente (Product Owner) |
| Elaborado por | Derly Q — Ingeniera de Requisitos / Analista de Negocio |
| Revisado por | Esteban S— Gerente |
| Aprobado por | Cesar G — Cliente / Product Owner |
| Documento relacionado | SCFP-ERS-RF — Especificación de Requisitos Funcionales v1.1 |

## Historial de versiones

| **Versión** | **Fecha** | **Autor** | **Descripción del cambio** |
| --- | --- | --- | --- |
| 1.0 | 20/10/2026 | Derly Q | Versión inicial de los requisitos no funcionales. |
| 1.1 | 20/10/2026 | Derly Q | Versión con correcciones y ajustes de RNF al alcance. |

# 1. Introducción

## 1.1 Propósito

Este documento define los requisitos no funcionales (RNF) del SCFP: las condiciones de calidad, seguridad y operación que el sistema debe cumplir sin importar la función que se use. Complementa la especificación de requisitos funcionales (SCFP-ERS-RF).

## 1.2 Referencias

| **Referencia** | **Uso** |
| --- | --- |
| ISO/IEC 25010:2023 — Modelo de calidad del producto | Clasificación de los RNF por característica de calidad. |
| ISO/IEC/IEEE 29148:2018 — Ingeniería de requisitos | Redacción verificable de los requisitos. |
| OWASP Top 10 (2021) | Referencia para los riesgos de seguridad en aplicaciones web. |
| WCAG 2.1 (W3C) | Referencia de accesibilidad. |
| Ley 1581 de 2012 y Decreto 1377 de 2013 | Protección de datos personales. |

## 1.3 Convenciones

**RNF-NNN**: requisito no funcional.

**Característica**: característica de calidad de la ISO/IEC 25010:2023 a la que pertenece el requisito.

**Prioridad**: se usa la misma escala MoSCoW del documento de requisitos funcionales: Alta (Must), Media (Should), Baja (Could).

Los valores numéricos son metas iniciales. El equipo y el Product Owner pueden ajustarlos durante los sprints mediante el control de cambios.

# 2. Requisitos no funcionales

## Eficiencia de desempeño

### RNF-001 — Tiempo de respuesta

| **ID** | RNF-001 |
| --- | --- |
| **Nombre** | **Tiempo de respuesta** |
| **Característica** | Eficiencia de desempeño — comportamiento temporal |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe responder con rapidez a las operaciones del usuario, para que la experiencia sea fluida.

**Criterios de aceptación**

Las operaciones de registro, edición y consulta responden en 3 segundos o menos en el 95 % de los casos.

El dashboard y los reportes cargan en 5 segundos o menos en el 95 % de los casos.

### RNF-002 — Usuarios concurrentes

| **ID** | RNF-002 |
| --- | --- |
| **Nombre** | **Usuarios concurrentes** |
| **Característica** | Eficiencia de desempeño — capacidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe soportar el uso simultáneo de varios usuarios sin que se degrade el servicio.

**Criterios de aceptación**

Varios usuarios pueden usar el sistema al mismo tiempo sin que las acciones de uno afecten la información ni el funcionamiento de los demás.

## Seguridad

### RNF-004 — Comunicación cifrada

| **ID** | RNF-004 |
| --- | --- |
| **Nombre** | **Comunicación cifrada** |
| **Característica** | Seguridad — confidencialidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

Toda la comunicación entre el navegador y el servidor debe viajar cifrada.

**Criterios de aceptación**

El sistema solo funciona por HTTPS con TLS 1.2 o superior. Las peticiones por HTTP se redirigen a HTTPS.

### RNF-005 — Protección de la información almacenada

| **ID** | RNF-005 |
| --- | --- |
| **Nombre** | **Protección de la información almacenada** |
| **Característica** | Seguridad — confidencialidad |
| **Alcance** | Base de datos, archivos y copias de seguridad |
| **Prioridad** | Alta (Must) |

**Descripción**

La información sensible debe estar protegida aunque alguien acceda a la base de datos o a las copias de seguridad.

**Criterios de aceptación**

Las contraseñas y los códigos de verificación se guardan solo como hash, nunca en texto plano.

La base de datos y las copias de seguridad están cifrados en reposo.

Las credenciales del sistema (claves, contraseñas de la base de datos, llaves de API) no están en el código fuente; se manejan con variables de entorno o un gestor de secretos.

### RNF-006 — Control de acceso por roles

| **ID** | RNF-006 |
| --- | --- |
| **Nombre** | **Control de acceso por roles** |
| **Característica** | Seguridad — autenticidad y confidencialidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

Cada usuario solo debe poder ver y hacer lo que corresponde a su rol (Usuario o Administrador), y nunca acceder a la información de otro usuario.

**Criterios de aceptación**

Los permisos se validan en el servidor en cada petición, no solo en la interfaz.

Las pruebas de acceso confirman que un Usuario no puede consultar datos de otro ni funciones de Administrador.

### RNF-007 — Protección frente a vulnerabilidades comunes

| **ID** | RNF-007 |
| --- | --- |
| **Nombre** | **Protección frente a vulnerabilidades comunes** |
| **Característica** | Seguridad — integridad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe estar protegido contra los riesgos de seguridad web más comunes, según el OWASP Top 10.

**Criterios de aceptación**

Se previenen como mínimo la inyección SQL, el XSS y el CSRF.

Antes de cada entrega de corte, un análisis de seguridad automatizado (por ejemplo, OWASP ZAP o el escáner de dependencias del repositorio) no reporta vulnerabilidades de severidad alta o crítica sin resolver.

### RNF-008 — Cumplimiento de la ley de protección de datos

| **ID** | RNF-008 |
| --- | --- |
| **Nombre** | **Cumplimiento de la ley de protección de datos** |
| **Característica** | Seguridad — responsabilidad / restricción legal |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El tratamiento de los datos personales debe cumplir la Ley 1581 de 2012: autorización del titular, finalidad informada y derechos de consulta, actualización y supresión.

**Criterios de aceptación**

La política de tratamiento de datos está publicada y se puede consultar desde cualquier pantalla.

Los derechos del titular se pueden ejercer desde la aplicación (RF-008, RF-009, RF-010, RF-011).

Solo se piden los datos personales necesarios para el funcionamiento del sistema.

## Fiabilidad

### RNF-009 — Disponibilidad

| **ID** | RNF-009 |
| --- | --- |
| **Nombre** | **Disponibilidad** |
| **Característica** | Fiabilidad — disponibilidad |
| **Alcance** | Ambiente de producción |
| **Prioridad** | Media (Should) |

**Descripción**

El sistema debe estar disponible la mayor parte del tiempo para que los usuarios consulten y registren su información cuando lo necesiten.

**Criterios de aceptación**

Disponibilidad mensual de al menos 99 %, sin contar el mantenimiento programado.

El mantenimiento programado se anuncia en la aplicación con al menos 24 horas de anticipación.

### RNF-010 — Copias de seguridad y recuperación

| **ID** | RNF-010 |
| --- | --- |
| **Nombre** | **Copias de seguridad y recuperación** |
| **Característica** | Fiabilidad — recuperabilidad |
| **Alcance** | Base de datos y archivos |
| **Prioridad** | Alta (Must) |

**Descripción**

Debe existir un procedimiento de copias de seguridad que permita recuperar la información ante una falla.

**Criterios de aceptación**

Se hace una copia de seguridad automática diaria y se conservan las de los últimos 30 días.

La restauración se prueba al menos una vez por corte académico y se deja evidencia.

### RNF-011 — Exactitud de los cálculos financieros

| **ID** | RNF-011 |
| --- | --- |
| **Nombre** | **Exactitud de los cálculos financieros** |
| **Característica** | Adecuación funcional — corrección |
| **Alcance** | MOD-05, MOD-06, MOD-07, MOD-08, MOD-10 |
| **Prioridad** | Alta (Must) |

**Descripción**

Los cálculos de dinero, tasas, impuestos y aportes deben ser exactos y consistentes en todo el sistema.

**Criterios de aceptación**

Los montos se manejan con tipos decimales, nunca con punto flotante.

Los ejemplos numéricos de los criterios de aceptación de los RF están automatizados como pruebas y pasan en cada versión.

### RNF-012 — Manejo de errores

| **ID** | RNF-012 |
| --- | --- |
| **Nombre** | **Manejo de errores** |
| **Característica** | Fiabilidad — tolerancia a fallos |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

Ante un error, el sistema debe informar al usuario con un mensaje claro, no perder información y registrar el error para que el equipo lo revise.

**Criterios de aceptación**

El usuario nunca ve mensajes técnicos (trazas, consultas SQL, nombres de servidores).

Todo error inesperado queda registrado en el log de la aplicación con fecha, hora y un identificador.

## Capacidad de interacción (usabilidad)

### RNF-013 — Facilidad de uso

| **ID** | RNF-013 |
| --- | --- |
| **Nombre** | **Facilidad de uso** |
| **Característica** | Capacidad de interacción — facilidad de aprendizaje |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

Una persona sin conocimientos contables debe poder usar las funciones principales sin capacitación previa.

**Criterios de aceptación**

En una prueba con al menos 5 usuarios, la mayoría (4 de 5) registra un ingreso y un gasto sin ayuda en 5 minutos o menos.

La navegación y los nombres de botones, campos y mensajes son consistentes en todo el sistema.

### RNF-014 — Diseño adaptable (responsive)

| **ID** | RNF-014 |
| --- | --- |
| **Nombre** | **Diseño adaptable (responsive)** |
| **Característica** | Capacidad de interacción — operabilidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

La aplicación debe verse y funcionar bien en computadores, tabletas y celulares.

**Criterios de aceptación**

Todas las pantallas se pueden usar sin desplazamiento horizontal en anchos de 360 px a 1920 px.

### RNF-015 — Accesibilidad

| **ID** | RNF-015 |
| --- | --- |
| **Nombre** | **Accesibilidad** |
| **Característica** | Capacidad de interacción — inclusividad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Media (Should) |

**Descripción**

La interfaz debe poder usarse por personas con limitaciones visuales o motoras, siguiendo las pautas WCAG 2.1 nivel AA.

**Criterios de aceptación**

El contraste de color cumple el nivel AA, la información no depende solo del color y todas las funciones se pueden usar con el teclado.

Una herramienta automática (por ejemplo, Lighthouse o axe) da un puntaje de accesibilidad de al menos 90 en las pantallas principales.

### RNF-016 — Idioma y formatos locales

| **ID** | RNF-016 |
| --- | --- |
| **Nombre** | **Idioma y formatos locales** |
| **Característica** | Capacidad de interacción — reconocibilidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

La aplicación debe estar en español de Colombia y usar los formatos locales de moneda, números y fechas.

**Criterios de aceptación**

Todos los textos de la interfaz, mensajes y correos están en español.

Los montos se muestran en pesos colombianos ($ 1.234.567) y las fechas como DD/MM/AAAA.

### RNF-017 — Ayuda y manual de usuario

| **ID** | RNF-017 |
| --- | --- |
| **Nombre** | **Ayuda y manual de usuario** |
| **Característica** | Capacidad de interacción — facilidad de aprendizaje |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

El sistema debe contar con un manual de usuario y con ayudas breves dentro de la aplicación, sobre todo para los conceptos financieros y tributarios.

**Criterios de aceptación**

El manual de usuario cubre todos los módulos y se entrega en el Corte 3.

Los términos técnicos (UVT, IBC, XIRR, entre otros) muestran una explicación corta al pasar el cursor o tocar un ícono de ayuda.

## Compatibilidad

### RNF-018 — Compatibilidad con navegadores

| **ID** | RNF-018 |
| --- | --- |
| **Nombre** | **Compatibilidad con navegadores** |
| **Característica** | Compatibilidad — interoperabilidad |
| **Alcance** | Todo el sistema |
| **Prioridad** | Alta (Must) |

**Descripción**

La aplicación debe funcionar correctamente en los navegadores más usados.

**Criterios de aceptación**

Funciona en las dos últimas versiones de Chrome, Edge, Firefox y Safari, sin necesidad de instalar complementos.

### RNF-019 — Integración con servicios externos

| **ID** | RNF-019 |
| --- | --- |
| **Nombre** | **Integración con servicios externos** |
| **Característica** | Compatibilidad — coexistencia |
| **Alcance** | Envío de correos y consulta de la TRM |
| **Prioridad** | Media (Should) |

**Descripción**

Si un servicio externo (proveedor de correo u otro) falla, el resto del sistema debe seguir funcionando.

**Criterios de aceptación**

Una falla del servicio externo no impide registrar ni consultar información.

Los envíos o consultas fallidas se reintentan y la falla queda registrada en el log.

### RNF-020 — Estándares de código y control de versiones

| **ID** | RNF-021 |
| --- | --- |
| **Nombre** | **Estándares de código y control de versiones** |
| **Característica** | Mantenibilidad — analizabilidad |
| **Alcance** | Código fuente |
| **Prioridad** | Alta (Must) |

**Descripción**

El código debe seguir una guía de estilo acordada por el equipo y estar versionado en un repositorio Git.

**Criterios de aceptación**

Todo cambio entra a la rama principal por medio de un pull request revisado por al menos otro integrante del equipo.

Se usa una herramienta de análisis estático (linter) sin errores en la rama principal.

### RNF-022 — Parametrización de valores normativos

| **ID** | RNF-023 |
| --- | --- |
| **Nombre** | **Parametrización de valores normativos** |
| **Característica** | Mantenibilidad — modificabilidad |
| **Alcance** | MOD-02 y módulos de cálculo |
| **Prioridad** | Alta (Must) |

**Descripción**

Los valores que cambian con la ley o con cada año (UVT, SMMLV, tarifas, topes) no deben estar escritos en el código, para poder actualizarlos sin programar.

**Criterios de aceptación**

Una revisión de código no encuentra valores normativos fijos en el código fuente.

Cambiar un parámetro desde el módulo de administración (RF-013) no requiere un nuevo despliegue.

### RNF-023 — Escalabilidad

| **ID** | RNF-025 |
| --- | --- |
| **Nombre** | **Escalabilidad** |
| **Característica** | Flexibilidad — escalabilidad |
| **Alcance** | Infraestructura |
| **Prioridad** | Baja (Could) |

**Descripción**

La arquitectura debe permitir atender más usuarios en el futuro agregando recursos, sin rediseñar el sistema.

**Criterios de aceptación**

El backend no guarda estado de sesión en memoria local, de modo que se pueden ejecutar varias instancias en paralelo.

## Documentación

### RNF-024 — Documentación técnica

| **ID** | RNF-026 |
| --- | --- |
| **Nombre** | **Documentación técnica** |
| **Característica** | Mantenibilidad — analizabilidad |
| **Alcance** | Proyecto |
| **Prioridad** | Alta (Must) |

**Descripción**

El proyecto debe entregar la documentación técnica necesaria para que otro equipo pueda entender, instalar y mantener el sistema.

**Criterios de aceptación**

Se entregan como mínimo: diagrama de arquitectura, modelo de datos, documentación de la API (OpenAPI/Swagger) y guía de instalación.

La documentación se entrega en el Corte 3 junto con el MVP final.

# 3. Aprobación

| **Rol** | **Nombre** | **Firma** | **Fecha** |
| --- | --- | --- | --- |
| Ingeniera de Requisitos / Analista de Negocio | Derly | Derly | 20/10/2026 |
| Gerente | Esteban | Esteban | 20/10/2026 |
| Product Owner / Cliente |  |  |  |
