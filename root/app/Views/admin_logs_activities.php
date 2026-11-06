<?php
/**
 * The activity log: generic_list plus a script that
 *  - turns the Record ID box on only once a table is chosen (an ID means nothing without its table),
 *  - turns the DataTables search box on only once both are set, so it looks within one record's history and never
 *    matches text across the whole log (the API ignores the search otherwise, too),
 *  - keeps ?table=&id=&search= in the address bar, so the page as filtered can be linked to.
 *
 * @var array $listConfig See App\Controllers\Logs::listConfig()
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
        const tableSelect = form.querySelector('[data-lb-filter="table_involved"]');
        const idInput = form.querySelector('[data-lb-filter="table_id_updated"]');
        const searchInput = dt.table().container().querySelector('.dt-search input, input[type="search"]');

        function recordChosen() {
            return tableSelect.value !== '' && /^[1-9]\d*$/.test(idInput.value.trim());
        }

        // the API ignores the search without a record; strip it here too so the request says what is shown
        $(dt.table().node()).on('preXhr.dt', function (e, settings, data) {
            if (!recordChosen() && data.search) {
                data.search.value = '';
            }
        });

        function sync() {
            const hasTable = tableSelect.value !== '';
            idInput.disabled = !hasTable;
            idInput.placeholder = hasTable ? 'Any record' : 'Choose a table first';
            if (!hasTable) {
                idInput.value = '';
            }
            const searchable = recordChosen();
            if (searchInput) {
                searchInput.disabled = !searchable;
                searchInput.placeholder = searchable ? 'Within this record' : 'Choose a table and record ID';
                searchInput.title = searchable ? '' : 'The search looks within one record\'s history: choose a table and a record ID first';
            }
            if (!searchable && dt.search() !== '') {
                dt.search(''); // the next request (already on its way, or the one below) goes without it
            }

            const params = new URLSearchParams();
            if (hasTable) {
                params.set('table', tableSelect.value);
            }
            if (searchable) {
                params.set('id', idInput.value.trim());
                if (dt.search() !== '') {
                    params.set('search', dt.search());
                }
            }
            const query = params.toString();
            history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
        }

        form.addEventListener('change', sync);
        form.addEventListener('input', sync);
        form.addEventListener('reset', function () { setTimeout(sync, 0); }); // the values change after the event
        $(dt.table().node()).on('search.dt', sync);
        sync();
    });
</script>
<?= $this->endSection() ?>
