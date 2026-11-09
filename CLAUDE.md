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

**Never authorize a reservist's own account with `belongsToUserUnit()`.** A reservist is scoped to
their own record, not to a unit — "same unit" would hand them every colleague's package. That method
returns `false` for the `reservist` group on purpose, so anything not yet updated fails closed; the
reservist surfaces authorize through `BaseController::canAccessPackageRow()` instead.

**Never let self-registration create a staff account.** `AuthGroups::$defaultGroup` is `reservist`.
It used to be `unit_s1_clerk`, which gave anyone who signed up `reservists.create`/`reservists.update`
over a whole unit. `ReservistRegistrationTest` asserts this directly — keep it that way.

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
- **A company is an organizational unit**, not a table of its own: `organizational_units` rows with
  `unit_type = 'company'` parented to the reserve unit, picked into `reservists.company_unit_id`.
  Reference Data already maintains them, each unit keeps its own set, and
  `UnitModel::activeUnits()` excludes them so a company can never be filed as somebody's whole unit.
- **A reservist's promotion standing is derived, never stored** — it is the `promotion_status` of
  their most recent `promotion_assessments` row, read by a correlated subquery in
  `ReservistModel::listQuery()`. No assessment yet renders as "Pending"; see
  `Views/components/promotion_status.php`, which is the only place the four badges are defined.
- **404 vs 403 on record access**: a record that exists but is not the caller's answers 404, so the
  response cannot be used to probe for ids. Unit-level refusals answer 403 from `UnitAccessFilter`.
- **The reservist portal** (client revision of 2026-11-09) is `my/*`: Dashboard, My Profile, ROTC
  Progress, My Documents (`my/promotion`), Notifications, Settings. Its controllers extend
  `ReservistPortalController`, which resolves the record from the session — never an id in the URL.
  The side menu is `Views/components/sidebar_reservist.php`; styles are in `public/assets/css/portal.css`.
- **A reservist's unit is their CDC.** Sign-up lists `unit_type = 'cdc'` units (1501–1505, inserted by
  the `AddStudentReservistFields` migration) and only that CDC's companies; `company_in_unit` enforces
  it server-side. Assign an S1 clerk to the CDC unit for them to see those reservists.
- **The student ROTC ladder (E1–E8)** is defined in `Config\RotcProgression` (labels, buttons) and
  worked out in `RotcProgressService` (completion, blocking, cadet/reserve ranks, status card). It is
  the student's own report: it never touches `reservists.current_rank_id`. Only enrolled
  undergraduates (`Reservist::isStudentReservist()`) are on it; their year level sets the E-level.
- **The education track is derived at sign-up** from the educational attainment
  (`ReservistRegistrationService::trackFor`): none/others → General, any college → 1st Year College.
  ROTC Professional is set by the clerk on the record.
- **The login page** follows the client's mock-up of 2026-11-10. Its two photos
  (`public/assets/img/login-salute.jpg`, `login-troops.jpg`) are crops of that mock-up image, seal
  included; every word on the page is real HTML. Swap in official photos and seal when the client has them.
- **Uploads that are not package documents** (profile photo, E-4 justification letter) go through
  `FileStorageService::storeAttachment()` / `attachmentPath()` — same type sniffing, writable/ only.

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
- `appTimezone` is `Asia/Manila`. Under the old `UTC`, "today" lagged eight hours, so between midnight
  and 8 AM a date entered as today failed `not_future_date` and time in grade read one day short.
- Bootstrap compiles its own blue into `.btn-outline-primary`, links and form checks; `--bs-primary`
  alone does not recolour them. The green overrides are at the end of `app.css`.
- The live host caches static files for a week (CDN and browser). Link local CSS/JS with
  `asset_url()` (app/Common.php), which appends the file mtime, or a deploy reaches returning users days late.
- A sign-up `<select>` option the form has hidden (another CDC's company) can still be chosen by a
  script; the UI test's `pick()` skips hidden options, and the server refuses the mismatch anyway.

## Not built yet

User management (`users.manage`) is still a disabled placeholder in the sidebar — staff accounts are
created by seeder or by SQL (reservists create their own by signing up). Everything else in the design
document's module table is implemented.

Staff can read a student's ROTC answers on the reservist profile but cannot correct or verify them
there yet; the student edits them on their own ROTC Progress page.

## Open question for the client

`PromotionRuleVersionsSeeder` encodes Table 3 of the thesis: minimum time in grade in **years**
(PFC→CPL 2, CPL→SGT 4, SGT→SSG 7 …). The stated requirement "every semester ma promote" implies a
6-month cycle, which contradicts Table 3. Nothing was overwritten — changing a threshold means adding
a new rule version via `Admin\PromotionRulesController::storeVersion`, never editing the seeded one.
Confirm which is authoritative before relying on the eligible/overdue verdicts.
