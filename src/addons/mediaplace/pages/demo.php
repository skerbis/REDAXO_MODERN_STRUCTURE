<?php

// ---- Intro ----
echo '<p style="margin:0 0 20px;max-width:900px;">' . rex_i18n::msg('mediaplace_demo_page_intro') . '</p>';

// ---- Section 1: Overlay Demo ----
$chosenLabel = rex_i18n::msg('mediaplace_demo_chosen');
$content1 = '
<p>' . rex_i18n::msg('mediaplace_demo_overlay_intro') . '</p>
<p><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_overlay_hint') . '</small></p>
<div style="display:flex;gap:10px;flex-wrap:wrap;">
    <button class="btn btn-default" onclick="MP.open()">
        <i class="fa-solid fa-eye"></i> ' . rex_i18n::msg('mediaplace_demo_browse_only') . '
    </button>
    <button class="btn btn-primary" onclick="MP.open(function(f){ alert(\'' . rex_i18n::msg('mediaplace_demo_chosen') . ': \' + f); })">
        <i class="fa-solid fa-photo-film"></i> ' . rex_i18n::msg('mediaplace_demo_single_select') . '
    </button>
    <button class="btn btn-success" onclick="MP.open(function(files){ alert(\'' . rex_i18n::msg('mediaplace_demo_chosen') . ': \' + files.join(\', \')); }, { multiple: true })">
        <i class="fa-solid fa-images"></i> ' . rex_i18n::msg('mediaplace_demo_multi_select') . '
    </button>
</div>
<pre style="margin-top:15px;font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>// Nur Ansehen (Browse-only, kein Callback)
MP.open();

// Einzelauswahl
MP.open(function(filename) {
    console.log(\'' . $chosenLabel . ':\', filename);
});

// Mehrfachauswahl
MP.open(function(filenames) {
    console.log(\'' . $chosenLabel . ':\', filenames); // Array von Dateinamen
}, { multiple: true });</code></pre>
';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_demo_section_overlay_title'), false);
$fragment->setVar('body', $content1, false);
echo $fragment->parse('core/page/section.php');

// ---- Dynamically pick example media from DB ----
$sql = rex_sql::factory();
$anyMedia = $sql->getArray('SELECT filename FROM ' . rex::getTable('media') . ' ORDER BY id ASC LIMIT 3');
$demoFile1 = !empty($anyMedia[0]['filename']) ? $anyMedia[0]['filename'] : '';
$demoFile2 = !empty($anyMedia[1]['filename']) ? $anyMedia[1]['filename'] : '';
$demoFile3 = !empty($anyMedia[2]['filename']) ? $anyMedia[2]['filename'] : '';
$demoMultiVal = implode(',', array_filter([$demoFile1, $demoFile2, $demoFile3]));

// ---- Section 2: Single Widget ----
$content2 = '
<p>' . rex_i18n::msg('mediaplace_demo_widget_intro') . '</p>

<div class="form-group">
    <label>' . rex_i18n::msg('mediaplace_demo_widget_label_empty') . '</label>
    <input class="mp-widget form-control" name="demo_image" value="">
</div>

<div class="form-group">
    <label>' . rex_i18n::msg('mediaplace_demo_widget_label_prefilled') . '</label>
    <input class="mp-widget form-control" name="demo_doc" value="' . rex_escape($demoFile1) . '">
</div>

<h4 style="margin-top:25px;">' . rex_i18n::msg('mediaplace_demo_usage') . '</h4>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>&lt;!-- Einfach die CSS-Klasse mp-widget setzen --&gt;
&lt;input class="mp-widget" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]"&gt;</code></pre>

<p style="margin-top:10px;"><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_widget_hint') . '</small></p>
';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_demo_section_widget_single_title') . ' <code>mp-widget</code>', false);
$fragment->setVar('body', $content2, false);
echo $fragment->parse('core/page/section.php');

// ---- Section 3: Multi Widget ----
$content3 = '
<p>' . rex_i18n::msg('mediaplace_demo_multi_widget_intro') . '</p>

<div class="form-group">
    <label>' . rex_i18n::msg('mediaplace_demo_gallery_label_empty') . '</label>
    <input class="mp-widget form-control" name="demo_gallery" data-mp-multiple="true" value="">
</div>

<div class="form-group">
    <label>' . rex_i18n::msg('mediaplace_demo_gallery_label_prefilled') . '</label>
    <input class="mp-widget form-control" name="demo_downloads" data-mp-multiple="true"
        value="' . rex_escape($demoMultiVal) . '">
</div>

<h4 style="margin-top:25px;">' . rex_i18n::msg('mediaplace_demo_usage') . '</h4>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>&lt;!-- data-mp-multiple="true" für Mehrfachauswahl --&gt;
&lt;input class="mp-widget" name="REX_INPUT_VALUE[2]"
       data-mp-multiple="true"
       value="REX_VALUE[2]"&gt;

&lt;!-- Wert: kommaseparierte Dateinamen --&gt;
&lt;!-- z.B. "bild1.jpg,bild2.png,dokument.pdf" --&gt;</code></pre>

<p style="margin-top:10px;"><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_gallery_hint') . ' <i class="fa-solid fa-xmark"></i> ' . rex_i18n::msg('mediaplace_demo_gallery_hint_remove') . '</small></p>
';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_demo_section_widget_multi_title') . ' <code>data-mp-multiple="true"</code>', false);
$fragment->setVar('body', $content3, false);
echo $fragment->parse('core/page/section.php');

// ---- Section 3b: Upload Widget ----
$content3b = '
<p>' . rex_i18n::msg('mediaplace_demo_upload_widget_intro') . '</p>

<div class="form-group">
    <label>' . rex_i18n::msg('mediaplace_demo_gallery_label_upload') . '</label>
    <input class="mp-widget form-control" name="demo_upload_gallery" data-mp-multiple="true" data-mp-upload="true" data-mp-types="image/*" value="">
</div>

<h4 style="margin-top:25px;">' . rex_i18n::msg('mediaplace_demo_usage') . '</h4>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>&lt;!-- data-mp-upload="true" fuer Direkt-Upload per Drag&amp;Drop/Klick,
     data-mp-types schraenkt die erlaubten Dateitypen ein (wie &lt;input accept&gt;) --&gt;
&lt;input class="mp-widget" name="REX_INPUT_VALUE[3]"
       data-mp-multiple="true"
       data-mp-upload="true"
       data-mp-types="image/*"
       value="REX_VALUE[3]"&gt;</code></pre>

<p style="margin-top:10px;"><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_upload_widget_hint') . '</small></p>
';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_demo_section_widget_upload_title') . ' <code>data-mp-upload="true"</code>', false);
$fragment->setVar('body', $content3b, false);
echo $fragment->parse('core/page/section.php');

// ---- Section 4: API Reference ----
$content4 = '
<table class="table table-striped">
<thead><tr><th>' . rex_i18n::msg('mediaplace_demo_th_attribute') . '</th><th>' . rex_i18n::msg('mediaplace_demo_th_description') . '</th><th>' . rex_i18n::msg('mediaplace_demo_th_example') . '</th></tr></thead>
<tbody>
<tr>
    <td><code>class="mp-widget"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_widget_class') . '</td>
    <td><code>&lt;input class="mp-widget" name="bild"&gt;</code></td>
</tr>
<tr>
    <td><code>data-mp-multiple="true"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_multiple') . '</td>
    <td><code>&lt;input class="mp-widget" data-mp-multiple="true"&gt;</code></td>
</tr>
<tr>
    <td><code>value="datei.jpg"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_value') . '</td>
    <td><code>value="a.jpg,b.png"</code></td>
</tr>
<tr>
    <td><code>data-mp-upload="true"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_upload') . '</td>
    <td><code>&lt;input class="mp-widget" data-mp-upload="true"&gt;</code></td>
</tr>
<tr>
    <td><code>data-mp-types="image/*"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_types') . '</td>
    <td><code>data-mp-types=".jpg,.png"</code></td>
</tr>
<tr>
    <td><code>data-mp-max="5"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_max') . '</td>
    <td><code>&lt;input class="mp-widget" data-mp-multiple="true" data-mp-max="5"&gt;</code></td>
</tr>
<tr>
    <td><code>data-mp-view="list"</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_attr_view') . '</td>
    <td><code>data-mp-view="grid"</code> ' . rex_i18n::msg('mediaplace_demo_or') . ' <code>data-mp-view="list"</code></td>
</tr>
</tbody>
</table>

<h4>JavaScript API</h4>
<table class="table table-striped">
<thead><tr><th>' . rex_i18n::msg('mediaplace_demo_th_method') . '</th><th>' . rex_i18n::msg('mediaplace_demo_th_description') . '</th></tr></thead>
<tbody>
<tr>
    <td><code>MP.open()</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_api_open') . '</td>
</tr>
<tr>
    <td><code>MP.open(callback)</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_api_open_callback') . '</td>
</tr>
<tr>
    <td><code>MP.open(callback, { multiple: true })</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_api_open_multiple') . '</td>
</tr>
<tr>
    <td><code>MP.close()</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_api_close') . '</td>
</tr>
<tr>
    <td><code>MPWidget.init()</code></td>
    <td>' . rex_i18n::msg('mediaplace_demo_api_widget_init') . '</td>
</tr>
</tbody>
</table>

<h4>' . rex_i18n::msg('mediaplace_demo_modules_heading') . '</h4>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>&lt;!-- Modul-Eingabe: Einzelbild --&gt;
&lt;div class="form-group"&gt;
    &lt;label&gt;Bild&lt;/label&gt;
    &lt;input class="mp-widget" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]"&gt;
&lt;/div&gt;

&lt;!-- Modul-Eingabe: Galerie --&gt;
&lt;div class="form-group"&gt;
    &lt;label&gt;Galerie&lt;/label&gt;
    &lt;input class="mp-widget" name="REX_INPUT_VALUE[2]"
           data-mp-multiple="true" value="REX_VALUE[2]"&gt;
&lt;/div&gt;

&lt;!-- Modul-Ausgabe --&gt;
&lt;?php
$image = "REX_VALUE[1]";
if ($image) {
    echo \'&lt;img src="\' . rex_url::media($image) . \'"&gt;\';
}

$gallery = explode(",", "REX_VALUE[2]");
foreach ($gallery as $file) {
    echo \'&lt;img src="\' . rex_url::media(trim($file)) . \'"&gt;\';
}
?&gt;</code></pre>

<h4 style="margin-top:25px;">' . rex_i18n::msg('mediaplace_demo_yform_heading') . '</h4>

<p><strong>' . rex_i18n::msg('mediaplace_demo_yform_native_heading') . '</strong></p>
<p><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_yform_native_intro') . '</small></p>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>' . rex_escape('mediaplace|gallery|Galerie|') . '</code></pre>
<p style="margin-top:10px;"><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_yform_native_json_intro') . '</small></p>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>' . rex_escape('{
    "table_name": "meine_tabelle",
    "prio": 1,
    "type_id": "value",
    "type_name": "mediaplace",
    "name": "gallery",
    "label": "Galerie",
    "multiple": 1,
    "upload": 1,
    "types": "image/*"
}') . '</code></pre>

<p style="margin-top:25px;"><strong>' . rex_i18n::msg('mediaplace_demo_yform_html_heading') . '</strong></p>
<p><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_yform_intro') . '</small></p>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>' . rex_escape('html|gallery||<input class="mp-widget" data-mp-multiple="true" data-mp-upload="true">') . '</code></pre>

<p style="margin-top:10px;"><small class="text-muted">' . rex_i18n::msg('mediaplace_demo_yform_json_intro') . '</small></p>
<pre style="font-size:12px;background:#f5f5f5;padding:12px;border-radius:4px;"><code>' . rex_escape('{
    "table_name": "meine_tabelle",
    "prio": 1,
    "type_id": "value",
    "type_name": "html",
    "name": "gallery",
    "label": "<input class=\"mp-widget\" data-mp-multiple=\"true\" data-mp-upload=\"true\">"
}') . '</code></pre>
';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_demo_section_api_title'), false);
$fragment->setVar('body', $content4, false);
echo $fragment->parse('core/page/section.php');
