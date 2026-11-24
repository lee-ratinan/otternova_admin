<?php
/**
 * The email log: generic_list plus a script that keeps ?type=&status=&search= in the address bar, so the page as
 * filtered can be linked to.
 *
 * @var array $listConfig See App\Controllers\Logs::emailListConfig()
 */
$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <?= lb_render($listConfig) ?>
            </div>
        </div>
    </section>

<?= lb_script($listConfig) ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';
        const tableId = <?= json_encode($listConfig['table_id']) ?>;
        const handle = window.TableBuilder && window.TableBuilder[tableId];
        const form = document.getElementById(tableId + '-filters');
        if (!handle || !form) {
            return;
        }
        const dt = handle.table;
        const typeSelect = form.querySelector('[data-lb-filter="email_type"]');
        const statusSelect = form.querySelector('[data-lb-filter="status"]');
        const searchInput = dt.table().container().querySelector('.dt-search input, input[type="search"]');
        if (searchInput) {
            searchInput.placeholder = 'Email address or subject';
        }

        function sync() {
            const params = new URLSearchParams();
            if (typeSelect.value !== '') {
                params.set('type', typeSelect.value);
            }
            if (statusSelect.value !== '') {
                params.set('status', statusSelect.value);
            }
            if (dt.search() !== '') {
                params.set('search', dt.search());
            }
            const query = params.toString();
            history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
        }

        form.addEventListener('change', sync);
        form.addEventListener('reset', function () { setTimeout(sync, 0); }); // the values change after the event
        $(dt.table().node()).on('search.dt', sync);
        sync();
    });
</script>
<?= $this->endSection() ?>
