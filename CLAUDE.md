# PromoTrack AFP-RF — working notes

CodeIgniter 4 + Shield. Promotion records and workflow for Philippine Army Reserve Force unit staff.
Read [README.md](README.md) for setup and [docs/phase1-design.md](docs/phase1-design.md) for the
architecture, schema, permission matrix, and workflow state table — the design document is the
contract, not a historical artifact.

## Commands

```bash
vendor/bin/phpunit --no-coverage        # full suite; feature tests rebuild the test schema
vendor/bin/phpunit --no-coverage --filter SomeTest
php spark serve                          # http://localhost:8080
php spark migrate
php spark routes
```

On Windows the PHP binary is `C:\xampp\php\php.exe` and is not on PATH; call it by full path.
MySQL must be running (XAMPP) — feature tests need `promotrack_afprf_test` to be creatable.

## Rules that are easy to break by accident

**Never set `promotion_packages.status` directly.** `WorkflowStateMachine` validates the transition and
`PackageWorkflowService` writes status + `package_status_histories` + `audit_logs` in one transaction.
A direct update silently drops the audit trail.

**Never UPDATE a `promotion_rule_versions` row to change a threshold.** Insert a new version and expire
the old one (`Admin\PromotionRulesController::storeVersion`). Past assessments cite the version that was
in force when they ran; overwriting it makes them unexplainable.

**Never add a route without its `permission` filter.** `tests/Feature/AccessControlTest.php` drives real
requests per role and will fail — that is the point of it.

**Never load a front-end asset from a CDN.** CSP is `'self'`; a CDN `<script>` is refused by the browser
with no server-side error, and the intranet deployment has no outbound internet. Vendor it into
`public/assets/vendor/` instead. Inline `<script>` needs `{csp-script-nonce}`; inline `style="…"`
attributes cannot be nonced, so put them in `public/assets/css/app.css`.

**Never trust `getClientMimeType()` / `getClientExtension()` on an upload.** `FileStorageService`
detects the type with `finfo` and refuses on failed detection. Note that libmagic on this XAMPP build
returns `false` for a file whose contents start with `<?php` — that must stay a refusal, not a 500.

**Thresholds live in data, not in PHP.** Anything marked *REQUIRES OFFICIAL AFP/PHILIPPINE ARMY
VALIDATION* in the design document is a placeholder in `promotion_rule_versions` / reference tables.
Do not hard-code one in a service or a validation rule.

## Patterns already in the codebase — reuse rather than reinvent

- **One controller for several near-identical record sets**, driven by a const map:
  `ReservistRecordsController::TYPES`, `Admin\ReferenceDataController::SETS`.
- **Custom validation rules** go in `app/Validation/ReservistRules.php` (registered in
  `app/Config/Validation.php`) — e.g. `rank_progression`, `unique_rank_transition`.
- **Listing queries** expose a `listQuery()` that returns the model unexecuted so the controller can
  `paginate()` it; `listForDisplay()` stays as the `findAll()` convenience. Lists are paged in SQL,
  and DataTables is configured with `paging: false, searching: false` so it only sorts the page.
- **CSV output** is `csv_from_rows()` in `app/Helpers/csv_helper.php` (loaded via
  `BaseController::$helpers`). No PhpSpreadsheet unless real `.xlsx` formatting is required.
- **Settings** use the already-installed `codeigniter4/settings` package (`setting('App.foo')`), not a
  bespoke settings model. Only add a setting something actually reads.
- **404 vs 403 on record access**: a record that exists but is not the caller's answers 404, so the
  response cannot be used to probe for ids. Unit-level refusals answer 403 from `UnitAccessFilter`.

## Gotchas found the hard way

- `UnitAccessFilter` reads the record id from the **first numeric URI segment**. It used to read the
  last, which silently no-opped on every path ending in a verb (`/reservists/12/edit`).
- `Model::paginate()` puts the pager on **the instance it was called on**. If a helper returns
  `new Model()`, read `->pager` from that instance, not from a property.
- Feature tests that call `actingAs()` must `auth()->logout()` + `session()->destroy()` in `tearDown()`,
  or the next test class starts logged in and its authorization cases pass for the wrong reason.
- `PageNotFoundException` propagates in feature tests rather than rendering a 404 — assert the
  exception, not `assertStatus(404)`.
- Seeders are not idempotent; re-running `InitialSeeder` on a populated database fails on a duplicate
  unit code.

## Not built yet

User management (`users.manage`) is still a disabled placeholder in the sidebar — accounts are created
by seeder or by SQL. Everything else in the design document's module table is implemented.
