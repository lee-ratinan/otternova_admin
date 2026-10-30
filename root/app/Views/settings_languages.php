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
                    <?php
                    $allLanguages       = listLanguages();
                    $supportedLanguages = listSupportedLanguages();
                    ?>
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
            new DataTable('#mainTable');
        });
    </script>
<?php $this->endSection() ?>