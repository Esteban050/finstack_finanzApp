# ODD Feature Tasks: Módulo de Declaración de Renta (MOD-07)

**Proyecto:** FinanzApp (SCFP - Sistema de Control Financiero Personal)  
**Módulo:** MOD-07 (Cálculo de la Obligación y Estimación de Declaración de Renta)  
**Marco Normativo:** Estatuto Tributario Colombiano (Art. 36-1, 38-41, 126-1, 206, 241, 335-336, 592-594-3)  
**Feature Branch:** `feature/modulo-inversiones`  
**Ruta de Tareas:** `odd/tasks/declaracion-renta.md`  
**Engram Topic:** `odd/declaracion-renta/tasks`  

---

## 1. Objetivo y Alcance
Implementar el motor consolidador, evaluador y liquidador tributario para personas naturales residentes en Colombia (MOD-07) que integra los módulos de Ingresos, Inversiones (CDTs, Cuentas de Alto Rendimiento, FICs, Acciones/ETFs, FPV y Cesantías) y Retiros, conforme a los requisitos RF-043 a RF-047 de la ERS y los mockups de alta fidelidad 01 a 07.

---

## 2. Plan de Tareas (Work-Units)

- [ ] **T1: Modelado y Migraciones de Base de Datos**
  - **Alcance:** Migración para crear tablas `declaraciones_renta`, `declaracion_renta_ajustes` y `declaracion_renta_recordatorios`. Modelos Eloquent con relaciones y casts.
  - **Verificación:** `php artisan migrate`, assertions en tests de base de datos.
  - **Commit:** `feat(renta): tablas y modelos para declaracion de renta anual y auditoria de ajustes`

- [ ] **T2: Servicio de Consolidación Anual de Ingresos e Inversiones (RF-043)**
  - **Alcance:** `ConsolidadoRentaService` que consolida ingresos por cédula (trabajo, capital, no laborales), INCRNGO (salud/pensión, componente inflacionario, Art. 36-1 BVC/MGC), rentas exentas (FPV, cesantías), retenciones, patrimonio bruto al 31/12 y piso de inversiones para C5. Conversión a UVT y estructura para drill-down.
  - **Verificación:** Tests unitarios y funcionales para `CA-043.1` y `CA-043.2`.
  - **Commit:** `feat(renta): servicio de consolidacion anual de ingresos e inversiones (RF-043)`

- [ ] **T3: Servicio de Información Complementaria y Ajustes Manuales (RF-044)**
  - **Alcance:** Registro de topes bancarios externos (C3 tarjetas, C4 compras, C5 consignaciones con piso automático), ajustes manuales sobre C1 y C2 con validación estricta de no negatividad (`CA-044.2`) y bitácora de auditoría (10-500 caracteres).
  - **Verificación:** Tests funcionales para `CA-044.1` y `CA-044.2`.
  - **Commit:** `feat(renta): informacion complementaria, piso de inversiones y auditoria de ajustes (RF-044)`

- [ ] **T4: Servicio de Evaluación de Obligación de Declarar (RF-045)**
  - **Alcance:** `EvaluacionObligacionService` que evalúa los 6 criterios (C1 > 4500 UVT, C2 >= 1400 UVT, C3 > 1400 UVT, C4 > 1400 UVT, C5 > 1400 UVT, C6 responsable IVA). Veredictos OBLIGADO, INCOMPLETO, NO OBLIGADO; badge preventivo "No cumple · cerca" (>= 90%); proyección para año en curso (`RN-045.4`).
  - **Verificación:** Tests funcionales para `CA-045.1`, `CA-045.2`, `CA-045.3` y `CA-045.4`.
  - **Commit:** `feat(renta): evaluacion de topes y veredicto de obligacion de declarar (RF-045)`

- [ ] **T5: Motor de Liquidación y Estimación de Impuesto Cédula General (RF-046)**
  - **Alcance:** `EstimacionRentaService` implementando los 8 pasos de la depuración tributaria (Ingresos brutos, INCRNGO, Rentas exentas de inversiones, Renta exenta 25% laboral con tope 790 UVT, Límite global 40% / 1340 UVT, RLG en COP/UVT, Impuesto tabla Art. 241 redondeado a $1.000, Retenciones y saldo neto a pagar / a favor / cero). Tarjeta contrafactual de ahorro tributario por inversiones.
  - **Verificación:** Tests unitarios y funcionales para `CA-046.1`, `CA-046.2` y `CA-046.3`.
  - **Commit:** `feat(renta): motor de liquidacion de cedula general e impacto de inversiones (RF-046)`

- [ ] **T6: Calendario de Vencimiento y Recordatorios (RF-047)**
  - **Alcance:** `CalendarioVencimientoService` para calcular fecha límite según los últimos 2 dígitos del NIT/documento, programación de 4 recordatorios (30, 15, 5, 1 días) y acción "Marcar como presentada" que desactiva recordatorios pendientes.
  - **Verificación:** Tests funcionales para `CA-047.1` y `CA-047.2`.
  - **Commit:** `feat(renta): calendario de vencimiento por nit y recordatorios (RF-047)`

- [ ] **T7: Controlador y Rutas del Módulo de Renta (MOD-07)**
  - **Alcance:** `DeclaracionRentaController` y rutas bajo prefijo `/renta/{anio}` para las 5 pantallas del flujo, exportación de PDF y acciones de guardado.
  - **Verificación:** Pruebas de integración de endpoints HTTP, redirects y validaciones.
  - **Commit:** `feat(renta): controlador y endpoints del modulo de declaracion de renta`

- [ ] **T8: Vistas Blade de Alta Fidelidad (Mockups 01 a 07)**
  - **Alcance:** Creación de vistas `consolidado.blade.php`, `complementaria.blade.php`, `evaluacion.blade.php`, `estimacion.blade.php`, `vencimiento.blade.php` y plantilla descargable de PDF `constancia.blade.php`. Enlace en `navigation.blade.php` con badge "Nuevo".
  - **Verificación:** Pruebas de renderizado de vistas, verificación de componentes y diseño accesible.
  - **Commit:** `feat(renta): interfaz de usuario blade de alta fidelidad segun mockups 01 a 07`

- [ ] **T9: Verificación Integral de Aceptación y Cierre**
  - **Alcance:** Ejecución de la suite completa de pruebas unitarias y de integración (`DeclaracionRentaTest` e `InversionesTributariasTest`), auditoría visual de los mockups y reporte final.
  - **Verificación:** `php artisan test`, verificación de criterios de aceptación RF-043 a RF-047.
  - **Commit:** `test(renta): suite completa de pruebas y verificacion integral MOD-07`
