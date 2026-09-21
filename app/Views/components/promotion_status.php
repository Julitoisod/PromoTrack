<?php
/**
 * The reservist's promotion standing as a single badge.
 *
 * `null` means no assessment has been run for them yet, which is what a
 * freshly encoded reservist looks like -- shown as "Pending" rather than
 * as a verdict the system has not actually reached. The other three are
 * `promotion_assessments.promotion_status` as EligibilityService writes
 * it.
 *
 * Expected variables:
 *   $status  string|null
 */
[$variant, $label] = match ($status ?? null) {
    'eligible'         => ['success', 'Eligible'],
    'overdue'          => ['danger', 'Overdue'],
    'not_yet_eligible' => ['secondary', 'Not Yet Eligible'],
    default            => ['warning', 'Pending'],
};
?>
<span class="badge text-bg-<?= esc($variant) ?>"><?= esc($label) ?></span>
