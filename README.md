# PromoTrack AFP-RF

Promotion records and workflow tracking for Philippine Army Reserve Force unit staff.

A reservist's service record is maintained here, evaluated against **versioned** promotion rules, and
assembled into a promotion package that is routed through a controlled workflow:

```
Unit S1 Clerk → CDC → RCDG → external approving authority (outside the system) → Records Officer
```

The system records what the external authority decided; it never decides on their behalf.

Full architecture, schema, permission matrix, and workflow state table: [docs/phase1-design.md](docs/phase1-design.md).

---

## Stack

| | |
|---|---|
| Framework | CodeIgniter 4.7 |
| PHP | 8.2+ (`intl`, `mbstring`, `mysqlnd`, `fileinfo`) |
| Database | MySQL / MariaDB, InnoDB, `utf8mb4` |
| Auth | CodeIgniter Shield (7 groups, permission matrix in `app/Config/AuthGroups.php`) |
| PDF | Dompdf |
| Front end | Bootstrap 5.3, DataTables, Chart.js, SweetAlert2 — **vendored** in `public/assets/vendor/` |

There are no CDN dependencies. The Content-Security-Policy restricts scripts and styles to `'self'`,
and the system is expected to run on an intranet with no outbound internet access. If you add a
front-end library, vendor it — a CDN `<script>` is silently refused by the browser.

---

## Setup

```bash
composer install
cp env .env                     # then edit: baseURL, database credentials, encryption.key
php spark migrate
php spark db:seed InitialSeeder     # units, ranks, education tracks, training types, rules
php spark db:seed AdminUserSeeder   # superadmin account
php spark db:seed DemoUsersSeeder   # one demo account per role
php spark serve                     # http://localhost:8080
```

Seeded accounts all use the password `ChangeMe!12345` and **must be changed before any real use**.

| Account | Role |
|---|---|
| `superadmin@promotrack.local` | Superadmin |
| `clerk.demo@promotrack.local` | Unit S1 Clerk |
| `cdc.demo@promotrack.local` | CDC Administrator |
| `rcdg.demo@promotrack.local` | RCDG Reviewer |
| `commander.demo@promotrack.local` | Commander / Viewer (read-only) |
| `records.demo@promotrack.local` | Records Officer |

The seeders are not idempotent — re-running `InitialSeeder` on a populated database fails on a
duplicate unit code. Drop and re-migrate to reseed from scratch.

---

## Tests

```bash
vendor/bin/phpunit --no-coverage
```

Feature tests build and drop the schema on `database.tests` (default `promotrack_afprf_test`), so that
database must exist and the credentials in `.env` must be able to create tables in it.

| Suite | Covers |
|---|---|
| `tests/unit/` | Eligibility evaluation, workflow state machine, date arithmetic, upload whitelist |
| `tests/Feature/AccessControlTest.php` | Cross-unit and cross-role access — the IDOR / privilege-escalation suite required by design §12 |
| `tests/Feature/CsrfGuardTest.php` | CSRF refusal and its recovery path |
| `tests/Feature/ReservistRecordsTest.php` | Service-record add/remove and their ownership guards |
| `tests/Feature/PromotionRuleVersioningTest.php` | That a rule change supersedes rather than overwrites |
| `tests/Feature/ReservistManagementTest.php` | Reservist CRUD end to end |

---

## Layout

```
app/
  Controllers/
    Admin/            reference data, promotion rules, audit logs, settings + backups
    CDC/ RCDG/ Records/   the workflow stages
  Libraries/
    WorkflowStateMachine.php   the only place a package status may change
  Services/
    EligibilityService.php     evaluates a reservist against the rule version in force
    PackageWorkflowService.php transitions + history + audit, in one transaction
    FileStorageService.php     upload whitelist, checksum, storage outside public/
    BackupService.php          mysqldump wrapper; restore is a documented shell command
  Filters/
    CsrfGuard.php       refuses like the framework filter, recovers the form
    UnitAccessFilter.php record-level organizational-unit ownership
writable/
  uploads/    package documents — never served directly, always streamed by a controller
  backups/    database dumps
```

---

## Conventions worth knowing before changing anything

**Nothing sets `promotion_packages.status` directly.** Every transition goes through
`WorkflowStateMachine` + `PackageWorkflowService`, which write the status, a
`package_status_histories` row, and an `audit_logs` row inside one transaction. A controller that
writes the column itself breaks the audit trail.

**A promotion rule is never edited in place.** Changing a threshold inserts a new
`promotion_rule_versions` row and expires the previous one. Assessments cite the version that was in
force when they ran, and that has to stay resolvable afterwards.

**Every threshold is a placeholder.** Time in grade, MLES, fitness thresholds, rank ordering, and
promotion-order numbering are all marked *REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION* in the
design document. None of them is hard-coded in PHP; they live in `promotion_rule_versions` so they can
be corrected as data.

**Authorization is enforced more than once, on purpose.** Shield permission → route filter →
controller check → record ownership. Adding a route without its `permission` filter is caught by
`AccessControlTest`.

**Uploads are judged by their bytes.** `FileStorageService` uses `finfo`, not the browser's
`Content-Type` or filename, and treats a failed detection as a refusal.

---

## License

MIT — see [LICENSE](LICENSE).
