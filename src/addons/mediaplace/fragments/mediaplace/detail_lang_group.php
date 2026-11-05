<?php

/**
 * Mehrsprachigkeits-Huelle fuer ein uebersetzbares Feld: bei genau einer
 * Sprache nur die einzelne Zeile, bei mehreren die erste Sprache offen plus
 * kollabierbarer "N weitere Sprachen"-Umschalter (Auf-/Zuklappen bleibt rein
 * JS-seitig, siehe .mp-lang-toggle Handler in mediaplace.js). Markup
 * identisch zu renderLangInputs() in mediaplace.js.
 *
 * Vars:
 * - string $field_key
 * - array  $values      Sprache-ID (string) -> Text
 * - bool   $multiline
 * - list<array{id:int,name:string,code:string}> $clangs
 * - string $input_class optional
 *
 * @var rex_fragment $this
 */

$fieldKey = (string) $this->getVar('field_key');
$values = $this->getVar('values');
$multiline = (bool) $this->getVar('multiline');
$clangs = $this->getVar('clangs');
$inputClass = (string) $this->getVar('input_class', '');

if (empty($clangs)) {
    $clangs = [['id' => 1, 'name' => 'Lang 1', 'code' => 'l1']];
}

$valueFor = static function ($clang) use ($values) {
    return (string) ($values[(string) $clang['id']] ?? '');
};

if (count($clangs) <= 1) {
    $this->subfragment('mediaplace/detail_lang_row.php', [
        'field_key' => $fieldKey,
        'clang' => $clangs[0],
        'value' => $valueFor($clangs[0]),
        'multiline' => $multiline,
        'input_class' => $inputClass,
    ]);
    return;
}

$restCount = count($clangs) - 1;
?>
<div class="mp-lang-group" data-lang-group="<?= rex_escape($fieldKey) ?>">
    <?php $this->subfragment('mediaplace/detail_lang_row.php', [
        'field_key' => $fieldKey,
        'clang' => $clangs[0],
        'value' => $valueFor($clangs[0]),
        'multiline' => $multiline,
        'input_class' => $inputClass,
    ]); ?>
    <button type="button" class="mp-lang-toggle" data-lang-toggle="<?= rex_escape($fieldKey) ?>"><i class="fa-solid fa-chevron-right"></i> <?= rex_escape($this->i18n($restCount > 1 ? 'mediaplace_lang_more_many' : 'mediaplace_lang_more_one', $restCount)) ?></button>
    <div class="mp-lang-extra" style="display:none">
        <?php for ($i = 1; $i < count($clangs); $i++): ?>
            <?php $this->subfragment('mediaplace/detail_lang_row.php', [
                'field_key' => $fieldKey,
                'clang' => $clangs[$i],
                'value' => $valueFor($clangs[$i]),
                'multiline' => $multiline,
                'input_class' => $inputClass,
            ]); ?>
        <?php endfor; ?>
    </div>
</div>
