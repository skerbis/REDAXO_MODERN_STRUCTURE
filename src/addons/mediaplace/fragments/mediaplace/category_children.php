<?php

/**
 * Wrapper um eine Liste von Kategorien auf einer Ebene (".mp-cat-children").
 * Sichtbarkeit (auf-/zugeklappt) wird rein per CSS ueber die
 * ".mp-cat-node-open"-Klasse auf dem Eltern-Node gesteuert (siehe
 * mediaplace.css), nicht durch Weglassen des Markups -- der komplette Baum
 * wird immer auf einmal gerendert, toggleCategory() in mediaplace.js schaltet
 * nur noch lokal eine Klasse um, ohne Nachladen.
 *
 * Vars:
 * - list<rex_media_category> $categories
 * - int $depth
 * - int $current_cat
 *
 * @var rex_fragment $this
 */

/** @var list<rex_media_category> $categories */
$categories = $this->getVar('categories');
$depth = (int) $this->getVar('depth');
$currentCat = (int) $this->getVar('current_cat');
?>
<div class="mp-cat-children" data-depth="<?= $depth ?>">
<?php foreach ($categories as $category): ?>
    <?php $this->subfragment('mediaplace/category_node.php', [
        'category' => $category,
        'depth' => $depth,
        'current_cat' => $currentCat,
    ]); ?>
<?php endforeach; ?>
</div>
