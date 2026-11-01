<?php
/**
 * Account form (auth_master + its staff_master or client_master profile), one card per section.
 *
 * Expects from the controller (Admin::accountsEdit):
 *   $formConfig  Form Builder config; 'fields' holds EVERY field of every section, so fb_script() validates them all
 *   $sections    [['title' => ..., 'subtitle' => ..., 'fields' => [<field>, ...]], ...] in display order;
 *                the controller only includes the profile section matching the account type
 *
 * Built from the small form_helper.php functions instead of fb_render(), because fb_render() puts
 * everything in a single card. The edit/view panes must stay single elements: fb_script() swaps the
 * first [data-fb-pane="edit"] and [data-fb-pane="view"] it finds.
 */
$this->extend('_layout');
helper('form');
$cfg   = fb_defaults($formConfig);
$panes = [];
if ($cfg['mode'] !== 'view' || $cfg['editable']) {
    $panes[] = 'edit';
}
if ($cfg['mode'] === 'view') {
    $panes[] = 'view';
}
$last = array_key_last($sections);
?>
<?= $this->section('content') ?>
    <section class="section">
        <?= fb_form_open($cfg) ?>
        <?= fb_hidden_fields($cfg) ?>

        <?php foreach ($panes as $pane): ?>
            <?php // in view mode the edit pane starts hidden; the Edit button reveals it without reloading ?>
            <div data-fb-pane="<?= $pane ?>" class="row g-4<?= $pane === 'edit' && $cfg['mode'] === 'view' ? ' d-none' : '' ?>">
                <?php foreach ($sections as $i => $section): ?>
                    <div class="col-12">
                        <div class="card"<?= $i === 0 ? ' id="' . fb_esc($cfg['form_id'], 'attr') . '-card"' : '' ?>>
                            <?= $i === 0 ? fb_header($cfg) : fb_header($cfg, $section['title'], $section['subtitle'] ?? '') ?>
                            <div class="card-body">
                                <?= fb_rows($cfg, $pane, $section['fields']) ?>
                                <?php if ($i === $last): ?>
                                    <?= $pane === 'edit' ? fb_buttons_edit($cfg) : fb_buttons_view($cfg) ?>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endforeach ?>

        </form>
    </section>

<?php // validation on change, submit via fetch, toastr messages, view <-> edit switching, delete confirmation ?>
<?= fb_script($cfg) ?>
<?= $this->endSection() ?>