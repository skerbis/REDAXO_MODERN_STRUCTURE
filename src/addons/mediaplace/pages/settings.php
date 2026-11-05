<?php

/**
 * Settings for mediaplace.
 */

use FriendsOfRedaxo\Mediaplace\AltTextFieldInstaller;
use FriendsOfRedaxo\Mediaplace\AltTextStatus;

// Klassisches ALT-Text-/Dekorativ-Metainfo-Feld anlegen (siehe
// AltTextFieldInstaller) -- eigene, einmalige Anlage-Aktion, KEIN Teil des
// rex_config_form unten (kein gespeicherter Einstellungswert). installAltField()/
// installDecorativeField() sind einzeln idempotent (no-op, wenn das jeweilige
// Feld schon existiert), deshalb hier immer beide anstossen -- deckt auch den
// seltenen Fall ab, dass nur eines der beiden Felder fehlt.
$altFieldInstallMsg = '';
if (1 === rex_post('mediaplace_install_alt_fields', 'int', 0)) {
    $altError = AltTextFieldInstaller::installAltField();
    $decorativeError = AltTextFieldInstaller::installDecorativeField();
    $installError = $altError ?? $decorativeError;
    $altFieldInstallMsg = null === $installError
        ? rex_view::success(rex_i18n::msg('mediaplace_settings_alt_field_install_success'))
        : rex_view::error($installError);
}

$form = rex_config_form::factory('mediaplace');
$form->addFieldset(rex_i18n::msg('mediaplace_settings_menu_legend'));

$field = $form->addCheckboxField('replace_classic_mediapool');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_menu_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_menu_hint'));

$form->addFieldset(rex_i18n::msg('mediaplace_settings_features_legend'));

$field = $form->addCheckboxField('enable_own_metadata');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_feature_own_metadata_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_feature_own_metadata_hint'));

$field = $form->addCheckboxField('enable_metainfo_editing');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_feature_metainfo_editing_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_feature_metainfo_editing_hint'));

$field = $form->addCheckboxField('enable_alt_missing_filter');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_feature_alt_missing_filter_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_feature_alt_missing_filter_hint'));

$field = $form->addCheckboxField('disable_tagging');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_feature_tagging_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_feature_tagging_hint'));

$field = $form->addCheckboxField('disable_collections');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_feature_collections_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_feature_collections_hint'));

// Nur relevant/sichtbar, wenn das ffmpeg-Addon installiert ist -- ohne ffmpeg
// zeigt das Grid ohnehin immer nur das Datei-Icon, unabhaengig von dieser
// Einstellung (siehe FfmpegIntegration::isAvailable()).
if (rex_addon::get('ffmpeg')->isAvailable()) {
    $field = $form->addSelectField('video_thumb_mode');
    $field->setLabel(rex_i18n::msg('mediaplace_settings_video_thumb_mode_label'));
    $select = $field->getSelect();
    $select->addOption(rex_i18n::rawMsg('mediaplace_settings_video_thumb_mode_off'), 'off');
    $select->addOption(rex_i18n::rawMsg('mediaplace_settings_video_thumb_mode_static'), 'static');
    $select->addOption(rex_i18n::rawMsg('mediaplace_settings_video_thumb_mode_animated'), 'animated');
    $field->setAttribute('class', 'form-control');
    $field->setNotice(rex_i18n::msg('mediaplace_settings_video_thumb_mode_hint'));
}

$form->addFieldset(rex_i18n::msg('mediaplace_settings_upload_legend'));

$field = $form->addCheckboxField('enable_upload_resize');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_upload_resize_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_upload_resize_hint'));

$field = $form->addInputField('number', 'upload_resize_width', null, [
    'class' => 'form-control',
    'min' => '100',
]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_upload_resize_width'));

$field = $form->addInputField('number', 'upload_resize_height', null, [
    'class' => 'form-control',
    'min' => '100',
]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_upload_resize_height'));

// Upload-Anbieter (siehe UploadProviderRegistry): "Eingebaut" (leerer Wert)
// ersetzt den bisherigen Upload-Button/Drag&Drop durch nichts -- der eigene
// Flow bleibt aktiv. Liste zeigt ALLE registrierten Provider (nicht nach
// Recht des aktuell eingeloggten Admins gefiltert), da hier konfiguriert
// wird, nicht genutzt -- die Rechtepruefung fuer den TATSAECHLICH
// nutzenden User passiert separat in boot.php beim Ausliefern an #mp-root.
$uploadProviders = \FriendsOfRedaxo\Mediaplace\UploadProviderRegistry::getAllProviders();
$field = $form->addSelectField('upload_provider', null, [
    'class' => 'form-control selectpicker',
]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_upload_provider_label'));
$select = $field->getSelect();
$select->addOption(rex_i18n::rawMsg('mediaplace_settings_upload_provider_builtin'), '');
foreach ($uploadProviders as $providerId => $providerMeta) {
    $select->addOption((string) ($providerMeta['label'] ?? $providerId), $providerId);
}
$field->setNotice(rex_i18n::msg($uploadProviders ? 'mediaplace_settings_upload_provider_hint' : 'mediaplace_settings_upload_provider_hint_none'));

$form->addFieldset(rex_i18n::msg('mediaplace_settings_ai_alt_legend'));

$aiPlatformAvailable = rex_addon::exists('ai_platform') && rex_addon::get('ai_platform')->isAvailable();

$field = $form->addCheckboxField('enable_ai_alt_text');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_enable_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_alt_enable_hint') . ($aiPlatformAvailable ? '' : ' <strong>' . rex_i18n::msg('mediaplace_settings_ai_alt_not_available_hint') . '</strong>'));

// Vollstaendige, automatische Vervollstaendigung (Admin-Seite +
// AiAltCompleteCronjob) -- bewusst getrennt von enable_ai_alt_text: dieser
// Schalter erlaubt zusaetzlich, dass generierte Texte OHNE Review-Schritt
// direkt geschrieben werden (siehe Api\AiAltComplete-Docblock). Der
// bestehende "AI Bulk Management"-Dialog im Overlay bleibt davon
// unberuehrt (Review-vor-Speichern-Prinzip dort unveraendert).
$field = $form->addCheckboxField('enable_ai_alt_auto_complete');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_auto_complete_enable_label'), 1);
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_alt_auto_complete_enable_hint'));

$field = $form->addSelectField('ai_alt_prompt_profile');
$field->setLabel(rex_i18n::msg('mediaplace_settings_ai_alt_prompt_profile_label'));
$select = $field->getSelect();
$select->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_prompt_profile_accessibility'), 'accessibility');
$select->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_prompt_profile_neutral'), 'neutral');
$select->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_prompt_profile_seo'), 'seo');
$field->setAttribute('class', 'form-control');
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_alt_prompt_profile_hint'));

$field = $form->addTextAreaField('ai_alt_custom_prompt', null, ['class' => 'form-control', 'rows' => 3]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_ai_alt_custom_prompt_label'));
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_alt_custom_prompt_hint'));

$field = $form->addInputField('number', 'ai_alt_max_image_dimension', null, [
    'class' => 'form-control',
    'min' => '256',
    'max' => '2048',
]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_ai_alt_max_dimension_label'));
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_alt_max_dimension_hint'));

$aiPlatformProfiles = [];
if ($aiPlatformAvailable) {
    try {
        $aiPlatformProfiles = \FriendsOfRedaxo\AiPlatform\Service::getInstance()->getProfiles('image_understanding');
    } catch (\Throwable $e) {
        $aiPlatformProfiles = [];
    }
}
$field = $form->addSelectField('ai_alt_platform_profile_id');
$field->setLabel(rex_i18n::msg('mediaplace_settings_ai_alt_platform_profile_label'));
$select = $field->getSelect();
$select->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_alt_platform_profile_default'), '0');
foreach ($aiPlatformProfiles as $profile) {
    $label = (string) ($profile['name'] ?? ($profile['provider'] ?? 'Profil') . ' (' . ($profile['model'] ?? '?') . ')');
    $select->addOption($label, (string) $profile['id']);
}
$field->setAttribute('class', 'form-control');
$field->setNotice(
    $aiPlatformProfiles || !$aiPlatformAvailable
        ? rex_i18n::msg('mediaplace_settings_ai_alt_platform_profile_hint')
        : rex_i18n::msg('mediaplace_settings_ai_alt_platform_profile_none_hint'),
);

// KI-Auto-Tagging: geschlossenes Vokabular, siehe AiAutoTagService-Docblock
// -- welche Tags zur Auswahl stehen wird NICHT hier, sondern gezielt in der
// Tag-Verwaltung (pages/tag_management.php) pro Tag festgelegt ("Für
// KI-Vorschläge freigeben"). Teilt sich das Bildverstaendnis-Profil oben
// (ai_alt_platform_profile_id) mit der ALT-Text-Generierung.
$aiAllowedTagCount = count(\FriendsOfRedaxo\Mediaplace\SystemTagManager::getAiAllowedTagNames());

$field = $form->addCheckboxField('enable_ai_auto_tag');
$field->addOption(rex_i18n::rawMsg('mediaplace_settings_ai_auto_tag_enable_label'), 1);
$field->setNotice(
    rex_i18n::msg('mediaplace_settings_ai_auto_tag_enable_hint')
    . ($aiPlatformAvailable ? '' : ' <strong>' . rex_i18n::msg('mediaplace_settings_ai_alt_not_available_hint') . '</strong>')
    . (0 === $aiAllowedTagCount
        ? ' <strong>' . rex_i18n::msg('mediaplace_settings_ai_auto_tag_none_allowed_hint') . '</strong>'
        : ' ' . rex_i18n::msg('mediaplace_settings_ai_auto_tag_allowed_count_hint', $aiAllowedTagCount)),
);

$field = $form->addInputField('number', 'ai_auto_tag_max', null, [
    'class' => 'form-control',
    'min' => '1',
    'max' => '10',
]);
$field->setLabel(rex_i18n::msg('mediaplace_settings_ai_auto_tag_max_label'));
$field->setNotice(rex_i18n::msg('mediaplace_settings_ai_auto_tag_max_hint'));

$formHtml = $form->get();

// rex_form_base::createElement() faellt bei einer NICHT abgeschickten Checkbox
// (der Browser sendet fuer eine deaktivierte Checkbox ueberhaupt kein Feld mit)
// auf den zuvor gespeicherten Wert zurueck, statt "aus" zu speichern -- eine
// Checkbox in einem rex_config_form laesst sich dadurch nie wieder deaktivieren,
// sobald sie einmal aktiviert wurde (live beobachtet: "Klassischen Medienpool-
// Menuepunkt ersetzen" liess sich nicht abschalten). Betrifft jedes
// addCheckboxField() auf dieser Seite gleichermassen -- nach dem generischen
// Form-Save (oben) deshalb fuer alle hier den tatsaechlich gesendeten Zustand
// explizit nachtragen, als echten Bool-Wert statt des sonst ueblichen
// Pipe-Strings (beides wird ueberall nur truthy/falsy ausgewertet, siehe
// boot.php/lib/*).
//
// WICHTIG (frueherer Fix hier war selbst kaputt): rex_form_base gruppiert
// $_POST NICHT unter dem rex_config-Namespace ("mediaplace"), sondern unter
// der NORMALISIERTEN FIELDSET-LEGENDE (uebersetzter Text!) jedes einzelnen
// addFieldset()-Aufrufs -- siehe rex_form_base::fieldsetPostValues()/
// elementPostValue(). Diese Seite hat mehrere Fieldsets (Klassischer
// Medienpool/Features/Upload/KI), die Checkboxen landen also unter
// UNTERSCHIEDLICHEN Top-Level-Schluesseln wie "klassischer_medienpool"
// oder "features", nie unter "mediaplace" -- rex_post('mediaplace', ...)
// fand deshalb NIE etwas, der ganze Block war ein stiller No-Op (live
// verifiziert: gespeicherter Wert blieb dauerhaft NULL, rex_config::get()
// fiel damit ueber den ??-Operator immer auf den Default true zurueck,
// unabhaengig vom tatsaechlichen Haken-Zustand). Deshalb hier bewusst
// generisch ueber ALLE Top-Level-Gruppen in $_POST suchen, statt die
// (uebersetzungsabhaengige) Fieldset-Normalisierung nachzubauen.
$addonName = $this->getName();
// Zuverlaessiger, nicht uebersetzungsabhaengiger "wurde abgeschickt"-Check:
// rex_config_form haengt an den Save-Button-Namen genau dieses Muster an
// (siehe rex_config_form::__construct(), $id = md5($namespace . $fieldset)).
$formWasSubmitted = null !== rex_post(md5($addonName) . '_save', 'string', null);
if ($formWasSubmitted) {
    $checkboxFields = [
        'replace_classic_mediapool',
        'enable_own_metadata',
        'enable_metainfo_editing',
        'enable_alt_missing_filter',
        'disable_tagging',
        'disable_collections',
        'enable_upload_resize',
        'enable_ai_alt_text',
        'enable_ai_alt_auto_complete',
        'enable_ai_auto_tag',
    ];
    foreach ($checkboxFields as $checkboxField) {
        $isChecked = false;
        foreach ($_POST as $groupValue) {
            if (is_array($groupValue) && array_key_exists($checkboxField, $groupValue)) {
                $isChecked = isset($groupValue[$checkboxField]['1']);
                break;
            }
        }
        rex_config::set($addonName, $checkboxField, $isChecked);
    }
}

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('mediaplace_settings_menu_legend'));
$fragment->setVar('body', $formHtml, false);
$mainSectionHtml = $fragment->parse('core/page/section.php');

// Klassisches ALT-Text-Feld (med_alt) kennt bislang kein "absichtlich kein
// ALT-Text noetig" (dekoratives Bild) -- eigenes JSON-Feld vom Widget-Typ
// "alt" hat das laengst (siehe AltTextStatus::isOwnValueEmpty()). Eigene,
// separate Box statt Teil des Formulars oben: keine gespeicherte Einstellung,
// sondern eine einmalige Anlage-Aktion. Nur relevant/anzeigbar, wenn das
// metainfo-Addon ueberhaupt verfuegbar ist.
$altFieldSectionHtml = '';
if (AltTextFieldInstaller::isAvailable()) {
    $hasAltField = AltTextStatus::hasClassicAltField();
    $hasDecorativeField = AltTextStatus::hasClassicDecorativeField();
    $altFieldsComplete = $hasAltField && $hasDecorativeField;

    ob_start();
    ?>
    <p><?php echo rex_i18n::msg('mediaplace_settings_alt_field_intro'); ?></p>
    <?php echo $altFieldInstallMsg; ?>
    <?php if ($altFieldsComplete): ?>
        <p class="text-success"><i class="fa-solid fa-circle-check"></i> <?php echo rex_i18n::msg('mediaplace_settings_alt_field_both_exist'); ?></p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="mediaplace_install_alt_fields" value="1">
            <button type="submit" class="btn btn-default">
                <?php
                if (!$hasAltField && !$hasDecorativeField) {
                    echo rex_i18n::msg('mediaplace_settings_alt_field_install_both_btn');
                } elseif (!$hasDecorativeField) {
                    echo rex_i18n::msg('mediaplace_settings_alt_field_install_decorative_btn');
                } else {
                    echo rex_i18n::msg('mediaplace_settings_alt_field_install_alt_btn');
                }
                ?>
            </button>
        </form>
    <?php endif; ?>
    <?php
    $altFieldHtml = ob_get_clean();

    // Warnfarbe (statt der neutralen "edit"-Optik), solange etwas fehlt --
    // faellt sofort auf, ohne den ganzen Text lesen zu muessen (siehe unten:
    // ohnehin schon als Sidebar-Box direkt neben dem Formularanfang platziert,
    // um sie ueberhaupt erst sichtbar zu machen).
    $altFieldFragment = new rex_fragment();
    $altFieldFragment->setVar('class', $altFieldsComplete ? 'edit' : 'warning', false);
    $altFieldFragment->setVar('title', rex_i18n::msg('mediaplace_settings_alt_field_legend'));
    $altFieldFragment->setVar('body', $altFieldHtml, false);
    $altFieldSectionHtml = $altFieldFragment->parse('core/page/section.php');
}

// Zwei Spalten statt alles untereinander: die ALT-Text-Feld-Box ist eine
// einmalige Aktion, kein Einstellungswert -- unter dem (langen) Formular
// wuerde sie leicht uebersehen, als Sidebar direkt neben dessen Anfang
// bleibt sie ohne Scrollen sichtbar.
?>
<div class="row">
    <div class="col-sm-8"><?php echo $mainSectionHtml; ?></div>
    <div class="col-sm-4"><?php echo $altFieldSectionHtml; ?></div>
</div>
<?php
