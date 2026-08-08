<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Bootstrap 5 pagination.
 *
 * The framework's default_full template emits `<ul class="pagination">` but
 * plain `<li>`/`<a>` inside it, which Bootstrap 5 leaves as a run of bare
 * links ("123") -- unusable once a list runs past a couple of pages. Bootstrap
 * needs `page-item` / `page-link` on the children.
 *
 * @var PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>

<nav aria-label="<?= lang('Pager.pageNavigation') ?>" class="mt-3">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($pager->hasPrevious()) : ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="<?= lang('Pager.first') ?>">
                    <span aria-hidden="true">&laquo;&laquo;</span>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="<?= lang('Pager.previous') ?>">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link) : ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>"><?= $link['title'] ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNext()) : ?>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getNext() ?>" aria-label="<?= lang('Pager.next') ?>">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= $pager->getLast() ?>" aria-label="<?= lang('Pager.last') ?>">
                    <span aria-hidden="true">&raquo;&raquo;</span>
                </a>
            </li>
        <?php endif ?>
    </ul>
</nav>
