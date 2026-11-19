# ODD Feature Tasks: Módulo de Reportes y Dashboards Dinámicos (MOD-11)

**Proyecto:** FinanzApp (SCFP - Sistema de Control Financiero Personal)  
**Módulo:** MOD-11 / Módulo H (Reportes y Dashboards Dinámicos: Backend)  
**Marco Normativo / Requisitos:** RF-065, RF-066, RF-067, RF-070, Propuestas AN §6.6, RNG-02, RNG-08  
**Feature Branch:** `feature/modulo-reportes`  
**Ruta de Tareas:** `odd/tasks/modulo-reportes.md`  
**Engram Topic:** `odd/modulo-reportes/tasks`  
**Nota de Alcance:** Implementación exclusiva de **Backend** (Servicios de dominio, exportadores XLSX/PDF, controladores API/JSON y suite de pruebas unitarias/funcionales) por instrucción expresa del usuario ("Solo haz el backend, nada de frontend").

---

## 1. Objetivo y Alcance
Implementar el motor consolidador, analítico y transaccional del **Módulo H: Reportes y Dashboards Dinámicos (MOD-11)** de FinanzApp, integrando los módulos de Ingresos (MOD-03), Gastos (MOD-04), Seguridad Social (MOD-05), Inversiones (MOD-06), Créditos (MOD-08) y Presupuestos (MOD-09). Cubre de forma completa la lógica de negocio y endpoints de las Pantallas 06 a 10 y las 6 Historias de Usuario (`HU-H01` a `HU-H06`):

1. **Pantalla 06 (`HU-H01`, `HU-H02` · `RF-065`):** Dashboard Principal (`/resumen`) con 7 tarjetas KPI, bloque de próximos 15 días, banner de déficit y series de 4 gráficos interactivos de comportamiento financiero.
2. **Pantalla 07 (`HU-H03` · Extensión AN §6.6):** Indicadores de Salud Financiera (`/reportes/indicadores`) con 7 métricas estandarizadas, modales de transparencia de cálculo y exportación a PDF con aviso legal.
3. **Pantalla 08 (`HU-H04` · `RF-066`):** Reporte de Flujo de Caja (`/reportes/flujo-de-caja`) con agrupación periódica (semanal, mensual, trimestral, anual), gráfico combinado (barras + línea acumulada), consistencia estricta con el Dashboard y exportación a XLSX y PDF.
4. **Pantalla 09 (`HU-H05` · `RF-067`):** Reporte de Gastos por Dimensión y Top de Comercios (`/reportes/gastos`) con tabla de contingencia dinámica bidimensional, nota de etiquetas no sumables, ranking Top 10 comercios y exportación a XLSX.
5. **Pantalla 10 (`HU-H06` · `RF-070`):** Comparativo entre Periodos (`/reportes/comparativo`) con validación de duración equivalente ($\pm 3$ días), variaciones absolutas y porcentuales, ordenamiento por monto en pesos, accesibilidad cromática/simbólica, manejo de división por cero (`N/A`) y exportación a XLSX.

---

## 2. Plan de Tareas (Work-Units)

- [x] **T1: Servicio Dashboard y KPIs del Resumen (HU-H01, RF-065)**
  - **Alcance:** Creación de `App\Services\Reportes\DashboardResumenService`. Resolución de periodos (`mes-actual`, `mes-anterior`, `trimestre`, `ano`, `personalizado`), cálculo de periodos anteriores equivalentes (`RN-065.5`), obligaciones de los próximos 15 días (cuotas de crédito pendientes y gastos recurrentes activos con bandera `es_monto_estimado`), las 7 tarjetas KPI con paridad estricta de gastos (`CA-065.1`), variación con flecha y signo accesible, y detección de déficit con causas del incremento.
  - **Verificación:** Pruebas unitarias de cálculo, paridad y resolución temporal en `DashboardResumenServiceTest` (6/6 pruebas pasando).
  - **Commit:** `feat(reportes): servicio de consolidacion de kpis y dashboard principal (RF-065)`

- [x] **T2: Motor de Gráficos Financieros y Estados Vacíos del Resumen (HU-H02, RF-065)**
  - **Alcance:** Generación de series de datos para los 4 gráficos interactivos del Resumen: histórico 12 meses Ingresos vs. Gastos con déficit en rojo (`RN-065.3`), Top 8 categorías de gasto + "Otras", donas Fijo vs. Variable y Esencial vs. Discrecional. Lógica de estados vacíos con acciones sugeridas (`RN-065.6`).
  - **Verificación:** Verificado en suite de `DashboardResumenServiceTest` e integración en `ResumenController`.
  - **Commit:** `feat(reportes): motor de graficos interactivos y estados vacios para resumen (RF-065)`

- [x] **T3: Servicio de Indicadores de Salud Financiera y Exportación PDF (HU-H03, AN §6.6)**
  - **Alcance:** Creación de `App\Services\Reportes\IndicadoresSaludService`. Las 7 métricas estandarizadas (Tasa de ahorro con meta del 10% y tendencia 6m, Ejecución presupuestal con umbrales de riesgo, Endeudamiento con semáforo, Gasto diario promedio, Concentración de gasto top 3 vs. resto, Ticket promedio y conteo, y Regla 50/30/20). Desglose para modal "Ver detalle" con fórmulas y valores reales. Plantilla y aviso legal reglamentario (`RNG-08`).
  - **Verificación:** Pruebas unitarias en `IndicadoresSaludServiceTest` (6/6 pruebas pasando) y descarga PDF en `ReportesControllerUnitTest`.
  - **Commit:** `feat(reportes): servicio de indicadores de salud financiera y diagnostico (AN §6.6)`

- [x] **T4: Servicio y Generador de Flujo de Caja con Exportaciones (HU-H04, RF-066)**
  - **Alcance:** Creación de `App\Services\Reportes\FlujoCajaService` y `App\Exports\FlujoCajaExport`. Agrupación temporal (`semanal`, `mensual`, `trimestral`, `anual`), ingresos netos por fuente, gastos por categoría, flujo neto y acumulado. Garantía de consistencia estricta donde flujo neto mensual == balance del Dashboard (`CA-066.1`). Exportación a XLSX (numérico con formato moneda) y PDF.
  - **Verificación:** Pruebas unitarias en `FlujoCajaServiceTest` (5/5 pruebas pasando) y exportaciones en `ReportesControllerUnitTest`.
  - **Commit:** `feat(reportes): servicio de flujo de caja historico y exportaciones (RF-066)`

- [x] **T5: Servicio de Gastos Multidimensionales y Ranking de Comercios (HU-H05, RF-067)**
  - **Alcance:** Creación de `App\Services\Reportes\GastosDimensionService` y `App\Exports\GastosDimensionExport`. Matriz de contingencia bidimensional (Categoría, Subcategoría, Naturaleza, Necesidad, Medio de pago, Relevancia tributaria, Etiqueta, Mes) con totales, porcentajes, conteo y promedio por gasto. Paridad estricta del total general (`CA-067.1`). Alerta de no sumabilidad en etiquetas (`CA-067.2`). Top 10 comercios/beneficiarios con barras proporcionales (`RN-067.3`).
  - **Verificación:** Pruebas unitarias en `GastosDimensionServiceTest` (4/4 pruebas pasando) y exportación XLSX en `ReportesControllerUnitTest`.
  - **Commit:** `feat(reportes): servicio de analisis multidimensional y top de comercios (RF-067)`

- [x] **T6: Servicio de Comparativo entre Periodos y Validaciones (HU-H06, RF-070)**
  - **Alcance:** Creación de `App\Services\Reportes\ComparativoPeriodosService` y `App\Exports\ComparativoPeriodosExport`. Validación estricta de duración equivalente ($\pm 3$ días, `RN-070.3`), variaciones absolutas y porcentuales, ordenamiento descendente por monto de variación en pesos (`RN-070.2`), signos `+` / `-` accesibles y manejo de división por cero (`N/A`, `CA-070.2`).
  - **Verificación:** Pruebas unitarias en `ComparativoPeriodosServiceTest` (6/6 pruebas pasando) y exportación XLSX en `ReportesControllerUnitTest`.
  - **Commit:** `feat(reportes): servicio de comparativo entre periodos y variaciones (RF-070)`

- [x] **T7: Controladores, Rutas HTTP y Clases de Exportación Excel/PDF**
  - **Alcance:** Creación de `ResumenController` y `ReportesController`. Registro de rutas `/resumen`, `/reportes/indicadores`, `/reportes/flujo-de-caja`, `/reportes/gastos`, `/reportes/comparativo` y endpoints de exportación XLSX/PDF en `routes/web.php`. Respuestas JSON estructuradas para API y frontend desacoplado.
  - **Verificación:** Pruebas unitarias de controladores en `ReportesControllerUnitTest` (10/10 pruebas pasando).
  - **Commit:** `feat(reportes): controladores y rutas http del modulo de reportes y resumen`

- [-] **T8: Vistas Blade de Alta Fidelidad y Frontend (EXCLUIDO POR USUARIO)**
  - **Estado:** Omitido explícitamente a solicitud del usuario ("Solo haz el backend, nada de frontend"). Las plantillas PDF internas (`indicadores.blade.php` y `flujo-caja.blade.php`) se mantienen operativas exclusivamente para la renderización del motor DomPDF de las exportaciones.

- [x] **T9: Suite de Pruebas Automatizadas y Cierre de Aceptación**
  - **Alcance:** Ejecución de la suite completa de pruebas unitarias (`tests/Unit/*Reportes*.php`) y funcionales (`tests/Feature/ReportesModuloTest.php`). Verificación de los criterios de aceptación de `HU-H01` a `HU-H06`.
  - **Verificación:** 44/44 pruebas del Módulo H pasando exitosamente (100% éxito) con 166 aserciones.
  - **Commit:** `test(reportes): suite de pruebas automatizadas y verificacion integral MOD-11`
