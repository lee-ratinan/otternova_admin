<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
<?php
/** One person on the chart and, under them, everyone who reports to them */
$renderNode = static function (array $node) use (&$renderNode): string {
    $html = '<li><a class="org-node" href="' . base_url('admin/public-profile/' . rawurlencode($node['uid'])) . '">'
        . '<img src="' . esc($node['avatar'], 'attr') . '" alt="" class="org-node-avatar" loading="lazy" width="56" height="56">'
        . '<span class="org-node-name">' . esc($node['full_name']) . '</span>'
        . ('' !== $node['job_title'] ? '<span class="org-node-title">' . esc($node['job_title']) . '</span>' : '')
        . ('' !== $node['department'] ? '<span class="org-node-department">' . esc($node['department']) . '</span>' : '')
        . '</a>';
    if ([] !== $node['reports']) {
        $html .= '<ul>';
        foreach ($node['reports'] as $report) {
            $html .= $renderNode($report);
        }
        $html .= '</ul>';
    }

    return $html . '</li>';
};
?>
    <div class="main-content page-org-chart">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Org Chart</h5>
                <div class="small text-secondary"><?= $count ?> active staff member<?= 1 === $count ? '' : 's' ?>, under their line managers</div>
            </div>
            <div class="card-body">
                <?php if ([] === $tree) : ?>
                    <p class="text-secondary mb-0">There are no active staff to show yet.</p>
                <?php else : ?>
                    <div class="org-chart-scroll">
                        <?php foreach ($tree as $root) : ?>
                            <ul class="org-chart"><?= $renderNode($root) ?></ul>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php $this->endSection() ?>
