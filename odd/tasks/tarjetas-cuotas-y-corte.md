# Credit cards: type label, purchase breakdown, statement/payment day, per-purchase installments

## Objective
1. Dashboard tiles show the product type next to the name: "Nu · Crédito" / "Bancolombia · Débito".
2. Credits module (card detail) lists the individual purchases (gastos) linked to the card.
3. Profile card registration asks for statement (corte) day and payment day; the card billing cycle is computed from them.
4. Expense with an existing credit card asks for optional number of installments (default 1) and a "Compra sin intereses" toggle.

## Product decision (user, 2026-11-26: "Por compra")
Each purchase is amortized over its own installments (default 1). A purchase made after the statement day falls into the next statement. Monthly card payment = Σ per purchase (capital / installments + interest at the card rate on that purchase's outstanding balance; zero interest when `sin_intereses`), due on the payment day following the statement.

## Scope
- New columns: `creditos.dia_corte`, `creditos.dia_pago` (smallint 1..31, nullable for legacy cards); `gastos.numero_cuotas` (smallint, default 1), `gastos.sin_intereses` (boolean, default false). New migration(s), applied locally with `php artisan migrate --path=...`.
- Reuse `CreditoService`/`PagoCreditoService` card plan (`reemplazarPlan`, `ajustarPlanTarjeta`, `comprasTarjeta`, interest-by-days helpers); do not duplicate credit logic.
- Frontend payload whitelist `resources/js/api/index.js` must carry the new expense fields.
- Out of scope: recurring expenses, NUEVA flow changes beyond defaults (it already asks cuotas/fecha_corte/fecha_pago).

## Constraints
- Spanish identifiers/UI copy (project convention), bcmath money strings, soft delete.
- Tests use DatabaseTransactions, never RefreshDatabase.
- ~400 changed lines per task is advisory only.

## TDD
Mode: disabled (source: user preference recorded in cuentas-bancarias-y-tarjetas, "si es pequeña sí TDD, sino no"; this feature is substantial). Ordinary functional checks run. Runner: `php artisan test` (PHPUnit 12), `npm test` (needs php.exe on PATH), `npm run build`.

## Delivery
Branch `feat/tarjetas-cuotas-y-corte`, one work-unit commit per task (Conventional Commits, no AI attribution). Strategy: ask-on-risk. Push/PR are the user's decision. RDD: on (default).

## Tasks
- [x] T1 — Dashboard type label (`DashboardResumenService::obtenerCuentas` adds `tipo`, `resources/js/reports/index.js` renders "nombre · Débito/Crédito"). Route: delegated (single writer for the feature).
- [x] T2 — Card purchase breakdown in Credits detail (endpoint/payload + `resources/js/credits/index.js`): fecha, descripción, monto, cuotas, sin intereses. Route: delegated.
- [x] T3 — Profile card form: `dia_corte`, `dia_pago` (required for new cards), store on creditos, show next statement/payment date in card detail. Route: delegated.
- [x] T4 — Expense with existing card: optional `numero_cuotas` (default 1) + `sin_intereses` checkbox; card plan computed per purchase using corte/pago days. Route: delegated.

## Acceptance criteria
- Dashboard shows "Banco · Débito" / "Banco · Crédito".
- Card detail lists each linked purchase.
- Card registration requires corte/pago day; plan due dates follow them.
- A 3-installment purchase on a card adds capital/3 per month; "sin intereses" adds no interest for that purchase; empty installments = 1.
- `php artisan test` and `npm test` green.

## Progress
- Created 2026-11-26 after exploration (mapper: dashboard reports/index.js:23, ConsumoTarjetaService::aplicar, PagoCreditoService::comprasTarjeta:969, GastoController::reglas 806-840, profile/show.blade.php 257-286).

- T1 done. Files: DashboardResumenService::obtenerCuentas (tipo DEBITO|CREDITO), resources/js/reports/index.js (nombre · Débito/Crédito), ResumenCuentasTest. Checks: ResumenCuentasTest|CuentaOtroBancoTest|TarjetaDePerfilEnGastoTest 9/9. Commit: 02e744f.

- T2 done. Files: CreditoController::show adds `compras` (gastos vigentes de la tarjeta; numero_cuotas/sin_intereses default 1/false until T4 columns), resources/js/credits/index.js (card "Compras" table), TarjetaComprasDetalleTest. Checks: TarjetaComprasDetalleTest 2/2; Credito*/CupoTarjeta 35/35; npm test 109/109. Commit: 3a999c4.

- T3 done. Migration 2026_11_26_000003 (creditos.dia_corte/dia_pago smallint nullable + CHECK 1..31, applied locally), new CicloTarjetaService (corteDeCompra, pagoDeCorte, proximaFecha, day clamped to month end), Credito fillable/casts, CuentaBancariaController::storeTarjeta (required 1..31) + profile form/list, CreditoController::show `ciclo` (proximo_corte/proximo_pago) + credits JS stats. Decision: NUEVA-from-expense cards and /creditos form left without days (legacy behaviour). Test edits: 4 existing tests that POST perfil.tarjetas.store now send dia_corte/dia_pago (required field consequence). New: CicloTarjetaServiceTest, PerfilCuentasTarjetasTest case. Checks: 55 related PHP tests pass; npm test 109/109. Commit: bb5a01e.

- T4 done. Migration 2026_11_26_000004 (gastos.numero_cuotas smallint default 1 + CHECK>=1, gastos.sin_intereses bool default false; applied locally). PagoCreditoService: planTarjetaPorCompra (per-purchase installments; corte via CicloTarjetaService; cuota 1 interest by real days like H07, later cuotas saldo x tasa periodica, 0 when sin_intereses; same-date cuotas summed; rounding absorbed in last row), crearPlan branch (only cards with dia_corte+dia_pago and purchases), ajustarPlanTarjeta($forzar,$compras) with planFuturoTarjetaPorCompra for cards with payments, comprasTarjeta carries cuotas/sin_intereses, situacion excludes sin_intereses purchases from causado interest. ConsumoTarjetaService::aplicar builds compras incl. new purchase conditions, forces replan when only cuotas/sin_intereses/fecha change, rejects cuotas>1/sin_intereses on cards without ciclo (422 numero_cuotas). GastoController: rules (existing card: optional cuotas 1..72, sin_intereses boolean; NUEVA keeps required cuotas) + prepararAtributos stores both (NUEVA also stores its cuotas). UI: expense form block + updateCard, rules.js validation, api whitelist. Legacy cards without ciclo and NUEVA cards keep the old single-plan path. No existing test expectation changed in T4. New: TarjetaCuotasPorCompraTest (9), JS test. Checks: full php artisan test 510/510; npm test 110/110; npm run build ok. Commit: d73d8d0.

## Next step
Done; user decides push/PR.
