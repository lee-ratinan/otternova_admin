<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><code><?= $country_code ?></code> <?= $country_name ?></h5>
                    <p class="card-subtitle">View country’s information</p>
                </div>
                <div class="card-body px-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th class="text-end">Key</th>
                                <th>Information</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="text-end">Country</td>
                                <td><i class="fi fi-<?= strtolower($country_code) ?>"></i> <?= $country_name ?></td>
                            </tr>
                            <tr>
                                <td class="text-end">Official Name</td>
                                <td><?= $full_name ?></td>
                            </tr>
                            <tr>
                                <td class="text-end">Country Code</td>
                                <td><?= $country_code ?></td>
                            </tr>
                            <tr>
                                <td class="text-end">Region</td>
                                <td><?= $region ?></td>
                            </tr>
                            <tr>
                                <td class="text-end">Languages</td>
                                <td>
                                    <?php if (!empty($languages)): ?>
                                        <?php foreach ($languages as $language): ?>
                                            <code><?= $language ?></code> <?= $all_lang[$language] ?? '' ?><br/>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        n/a
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-end">Timezones</td>
                                <td>
                                    <?php foreach ($timezones as $key => $timezone): ?>
                                        <code><?= $key ?></code> <?= $timezone ?>
                                        <?php
                                        try {
                                            $tz = new DateTimeZone($key);
                                            $tz_now = new DateTime('now', $tz);
                                            echo ' (' . $tz_now->format('P') . ')';
                                        } catch (DateInvalidTimeZoneException|DateMalformedStringException $e) {
                                            log_message('error', $e->getMessage());
                                            echo $e->getMessage();
                                        }
                                        ?>
                                        <br/>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-end">Currencies</td>
                                <td>
                                    <?php foreach ($currencies as $currency): ?>
                                        <code><?= $currency ?></code> <?= mapCurrencyCode($currency) ?><br/>
                                        Format: <?= formatCurrency(1999.99, $currency) ?><br/>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-end">Subdivisions</td>
                                <td>
                                    <?php foreach ($subdivisions as $key => $subdivision): ?>
                                        <code><?= $key ?></code> <?= $subdivision ?><br/>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $this->endSection() ?>