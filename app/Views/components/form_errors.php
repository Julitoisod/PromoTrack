<?php
/**
 * Renders a single field's validation error, right below its input.
 *
 * Controllers that redirect back after a failed validation must chain
 * `->withInput()->with('errors', $validation->getErrors())` so this
 * component can find the per-field message in flashdata.
 *
 * Usage inside a form view:
 *   <?= view('components/form_errors', ['field' => 'reservist_number']) ?>
 */
$field  ??= null;
$errors = session()->getFlashdata('errors') ?? [];
$error  = ($field !== null) ? ($errors[$field] ?? null) : null;
?>
<?php if ($error !== null): ?>
    <div class="invalid-feedback d-block"><?= esc($error) ?></div>
<?php endif; ?>
