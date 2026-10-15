# Architecture decision log

| # | Date | Decision | Reason | Migration path if changed |
|---|---|---|---|---|
| 1 | 2026-09-28 | Shared MySQL schema, `institution_id` + `branch_id` on every tenant table, fail-closed `BranchScope` | Master prompt default; simplest ops for 10 branches; consolidated reporting via plain SQL | See ARCHITECTURE §D.9 (database-per-branch) |
| 2 | 2026-09-28 | Tenant resolved from Host only (central domains → custom domains → `<sub>.<base>`) | Nothing in the URL/payload can select another tenant | — |
| 3 | 2026-09-28 | Host-only session cookies + single-use handoff tokens for switching | A session stolen on one branch is useless on another; explicit audit trail of switches | — |
| 4 | 2026-09-28 | spatie/laravel-permission **without teams**; branch access via `branch_user` + `branches.access-all` | A user's role is the same in every branch they may enter (matches how staff are appointed); simpler than per-team roles | Enable spatie teams with `team_foreign_key=branch_id`, migrate `model_has_roles` rows per `branch_user` row |
| 5 | 2026-09-28 | Roles are platform-wide; only Super Admin creates/edits them | Prevents one institution changing another's permissions | Part 7: add `roles.institution_id` for institution-owned custom roles |
| 6 | 2026-09-28 | Bootstrap 5.3 + Alpine + Chart.js via Vite; built assets committed to `public/build` | Works on Laragon without Node; easy to maintain | — |
| 7 | 2026-09-28 | Local dev on `*.localhost:8000` via `php artisan serve` | No hosts-file edits on Windows | Apache/`school.test` alternative documented |
| 8 | 2026-09-28 | Server-side paginated tables (no jQuery DataTables) | Queries stay tenant-scoped & indexed; no full-table loads | — |
| 9 | 2026-09-28 | PDF engine for Part 6: mPDF | Correct Bangla glyph shaping | — |
| 10 | 2026-10-08 | Inventory is valued at **moving weighted-average cost**; stock value is stored (`stock_value`) next to the quantity, not derived from the average | Keeps the stock sub-ledger exactly equal to the "Inventory stock" account (no rounding drift); the last unit leaves with whatever value remains | FIFO layers would need a `stock_lots` table; item totals stay valid |
| 11 | 2026-10-08 | Suppliers = the accounts `vendors` table; goods bought on credit post to the existing *Accounts payable* and are paid per goods receipt | One supplier list and one payable account across expenses and inventory | Split into a `suppliers` table with a mapping migration if suppliers need more than contact data |
| 12 | 2026-10-08 | Only **consumables** can be issued; durable equipment is received into stock and moves to the asset register in 6c-2 | Issuing equipment as an expense would understate assets | 6c-2 adds "register as asset" (Dr fixed asset / Cr inventory stock) |
| 13 | 2026-10-08 | Maker ≠ approver for purchase requests and orders; adjustments, supplier payments, audit completion and every reversal need `inventory.approve` (no maker-checker there) | Same rule as expenses; a one-person office can still adjust and pay | Add maker-checker to adjustments if auditors ask |
| 14 | 2026-10-08 | A receipt is reversible only while its goods are still on hand at the received value and no payment is attached | Stops reversals that would push stock or its value below zero | Lot tracking would allow partial reversals |

