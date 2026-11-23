# Credit-card expenses linked to credits, and TRM shown in details

## Objective
1. When an expense is paid with `TARJETA_CREDITO`, the card selector (teammate flow, commit 8d0ded0) keeps linking the consumption to an existing card, and adds a "Nueva compra a cuotas" option (`credito_id = NUEVA`) that asks for: bank, number of installments, statement (cut-off) date, payment date and annual interest rate. Saving creates a new credit in the credits module (MOD-08) linked via `gastos.credito_id`.
2. Income and expense details (and any other USD/TRM operation detail) show the TRM used for the COP conversion. Informational only: no calculation changes; backend queries must return the value.

## Scope
- Gasto model/controller/service/requests, expense form Blade + JS, credit creation via `CreditoService` (tipo `TARJETA_CREDITO`).
- New migration `2026_11_24_000001`: `creditos.fecha_corte` and `gastos.genera_credito` (marker: the linked credit was created by the expense). `gastos.credito_id` comes from the teammate's migration 2026_11_22_000001; the earlier duplicate migration was dropped.
- Income/expense detail views + API/JSON responses: expose `tasa_cambio_usada`.
- Feature tests.

## Constraints
- Do not change existing TRM calculations.
- Reuse `CreditoService` to build the amortization plan; do not duplicate credit logic.
- Delivery: user asked to commit and push directly to `main`.

## TDD
Mode: enabled (global CLAUDE.md "Strict TDD Mode: enabled"). Runner: `php artisan test` (PHPUnit).

## Tasks
- [x] T1 — Combined design: existing card (teammate `ConsumoTarjetaService`) or new installment purchase. Route: delegated (writer trigger). New credit created via `CreditoController::registrar()` (extracted from `store`; `validarDatos()` public) inside the gasto transaction; the gasto is marked `genera_credito=true` and `ConsumoTarjetaService::aplicar()` returns early for it, so capital is never added twice and later edit/delete never touch that credit's capital (credit is managed in /creditos). Switching to non-card payment detaches the link without modifying the credit; an existing-card gasto can switch to NUEVA (its consumption is returned to the old card). RED: 17/20 `GastoTarjetaCreditoTest` failing. GREEN: 20/20, 92 assertions; full suite same 5 pre-existing failures as baseline; JS tests +2.
- [x] T2 — TRM shown in income/expense tables (`x-ui.trm-used`, USD only). JSON endpoints already returned `tasa_cambio_usada`; tests lock it in. No calculation changes.
- [ ] T3 — Commit and push to main.

## Acceptance criteria
- Expense with `medio_pago=TARJETA_CREDITO` requires the five fields and creates one `Credito` linked by `gastos.credito_id`; other payment methods show/require none of them.
- Detail of a USD income/expense shows the TRM used.
- `php artisan test` green except known pre-existing failures.

## Progress
- Created 2026-11-23. Reworked 2026-11-23 to combine with commit 8d0ded0 (teammate card linkage).
