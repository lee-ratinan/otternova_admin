<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Countries</h5>
                    <p class="card-subtitle">This page lists both supported and unsupported countries. It ignores the sanctioned countries.</p>
                </div>
                <div class="card-body p-0">
                    <div class="text-end pe-3 pt-3">
                        <button type="button" id="btnSupportedOnly" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-check-circle"></i> Supported only
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="mainTable" class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Language Name</th>
                                <th>System Support</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($allLanguages as $languageCode => $languageName): ?>
                            <tr>
                                <td><?= $languageCode ?></td>
                                <td><?= $languageName ?></td>
                                <td>
                                    <?php if (isset($supportedLanguages[$languageCode])): ?>
                                        Support <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = new DataTable('#mainTable', {
                order: [[1, 'asc']],
                columnDefs: [
                    { orderable: false, targets: 2 }
                ]
            });
            // search() also writes the term into the search box (#dt-search-0)
            document.getElementById('btnSupportedOnly').addEventListener('click', function () {
                table.search('support').draw();
            });
        });
    </script>
<?php $this->endSection() ?>