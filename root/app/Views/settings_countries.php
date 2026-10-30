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
                        <button type="button" id="btnShowAll" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Show all
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="mainTable" class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th></th>
                                <th>#</th>
                                <th>Country Name</th>
                                <th>Region</th>
                                <th>System Support</th>
                                <th>Timezones</th>
                                <th>Currencies</th>
                                <th>Subdivisions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($allCountries as $countryCode => $countryName): ?>
                            <tr>
                                <td></td>
                                <td><?= $countryCode ?></td>
                                <td><?= $countryName ?></td>
                                <td>
                                    <?php foreach ($regions as $regionName => $listCountries): ?>
                                        <?php if (in_array($countryCode, $listCountries)): ?>
                                            <?= $regionName ?>
                                            <?php break; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if (isset($supportedCountries[$countryCode])): ?>
                                        Support <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($timezones[$countryCode])): ?>
                                        <?php foreach ($timezones[$countryCode] as $timezoneKey => $timezoneValue): ?>
                                            <code><?= $timezoneKey ?></code><br/>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($currencies[$countryCode])): ?>
                                        <?php foreach ($currencies[$countryCode] as $currencyKey): ?>
                                            <code><?= $currencyKey ?></code><br/>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($subdivisions[$countryCode])): ?>
                                        <div class="content-collapse">
                                        <?php foreach ($subdivisions[$countryCode] as $subCode => $subName): ?>
                                            <?= $subName ?><br/>
                                        <?php endforeach; ?>
                                        </div>
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
                    { orderable: false, targets: 0 },
                    { orderable: false, targets: 5 },
                    { orderable: false, targets: 6 },
                    { orderable: false, targets: 7 }
                ]
            });
            // search() also writes the term into the search box (#dt-search-0)
            document.getElementById('btnSupportedOnly').addEventListener('click', function () {
                table.search('support').draw();
            });
            document.getElementById('btnShowAll').addEventListener('click', function () {
                table.search('').draw();
            });
        });
    </script>
<?php $this->endSection() ?>