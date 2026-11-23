# Social security: FSP tier boundaries and pensioner exemption

## Objective
Fix QA findings CP-SS-047, CP-SS-048, CP-SS-049 and CP-SS-052 (SMMLV 2026 = $1.750.905).

## Problem
- CP-SS-047/048/049: an IBC of exactly 17, 18 or 19 SMMLV is charged the higher FSP rate (1.4 % / 1.6 % / 1.8 %). The ERS expects the lower one (1.2 % / 1.4 % / 1.6 %). Exactly 20 SMMLV already uses the lower rate (1.8 %).
- CP-SS-052: a profile marked only as "Pensionado" with an $8.000.000 salary is charged pension $320.000 and FSP $80.000; both must be $0.

## Why (root cause)
- `SeguridadSocialService::tarifaFsp()` treats every tier as `[desde, hasta)`. Ley 797/2003 art. 8 reads "igual o superior a 4/16 SMMLV" (lower bound inclusive) but "de 16 a 17, de 17 a 18, de 18 a 19, de 19 a 20, superiores a 20" — per the ERS, an exact 17/18/19/20 SMMLV belongs to the lower tier. Only the 20 SMMLV case was special-cased.
- RN-033.6 (pensioner pays no pension and no FSP) is applied only in `calcularIndependiente()`. `calcularAsalariado()` and `consolidarMultiIngreso()` ignore the profile occupation.

## Scope
- `app/Services/SeguridadSocialService.php`: `tarifaFsp()`, `calcularAsalariado()`, `consolidarMultiIngreso()`.
- `app/Http/Controllers/SeguridadSocialController.php`: pass the pensioner flag to `calcularAsalariado()` callers.
- `tests/Feature/SeguridadSocialNormativa2026Test.php`: new regression tests.

## Constraints
- Boundary comparisons in pesos (bcmath), not truncated SMMLV quotients.
- 4 and 16 SMMLV exact keep charging the new tier (inclusive lower bound).
- Open legal point: the norm verdict for 17–19 is "Revisar" in the QA sheet; implementation follows the ERS. Confirm with an accountant.

## Tasks
- [x] T1 — FSP: exact 17/18/19/20 SMMLV use the lower tier; ±$1 checks for 4, 16, 17, 18, 19, 20. Route: delegated (writer trigger: service + tests). RED: "IBC 29765385: se esperaba 1.2 y fue 1.400". GREEN: `test_fsp_limites_de_tramo_en_pesos` 18 assertions.
- [x] T2 — Pensioner: asalariado and multi-ingreso charge $0 pension (employee and employer) and $0 FSP when the profile has "Pensionado". Route: delegated (writer trigger: service + controller + tests). RED: unknown named parameter / '320000.00' vs '0.00'. GREEN: `test_asalariado_pensionado_no_paga_pension_ni_fsp`, `test_multi_ingreso_pensionado_no_paga_pension_ni_fsp`. Parent follow-up: `aplica_fsp_20_smmlv` false for pensioners.

## Acceptance criteria
- 29.765.385 → 1.2 %, 31.516.290 → 1.4 %, 33.267.195 → 1.6 %, 35.018.100 → 1.8 %; +$1 on each → next tier.
- 7.003.620 → 1 %, 28.014.480 → 1.2 % (unchanged).
- Pensioner asalariado salary $8.000.000 → pension 0, FSP 0, health unchanged.

## Checks
- TDD: strict (session config `Strict TDD Mode: enabled`). Runner: `~/.config/herd/bin/php.bat artisan test --filter=SeguridadSocialNormativa2026Test` (tests run against local pgsql `bd_finanzApp` inside transactions).

## Delivery
- Branch/commit pending user decision: the working tree already holds unrelated uncommitted edits in the same files.

## Progress
- T1 and T2 done (uncommitted). `artisan test --filter=SeguridadSocial`: 47/47 passed, 383 assertions (parent re-run after follow-up).
- Next: user decides branch/commit strategy; accountant confirms 17–19 boundary and employer pension for pensioners; QA re-runs CP-SS-047/048/049/052.
