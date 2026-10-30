<?php $this->extend('_layout'); ?>
<?= $this->section('content') ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><code><?= $country_code ?></code> <?= $country_name ?></h5>
                    <p class="card-subtitle">View country’s information</p>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="row">
                        <div class="col-12 col-lg-6">
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
                        <div class="col-12 col-lg-6">
                            <div class="map" id="countryMap" style="width:80%; height:500px; margin: 0 auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/data/countries2.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Responsive.js"></script>
    <script>
        am5.ready(function () {
            const countryCode  = <?= json_encode($country_code) ?>;
            const countryName  = <?= json_encode($country_name, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
            const subdivisions = <?= json_encode((object) $subdivisions, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>; // ISO 3166-2 code => name
            const root = am5.Root.new("countryMap");
            root.setThemes([
                am5themes_Animated.new(root),
                am5themes_Responsive.new(root)
            ]);
            const chart = root.container.children.push(am5map.MapChart.new(root, {
                panX: "translateX",
                panY: "translateY",
                wheelY: "zoom",
                projection: am5map.geoMercator()
            }));
            chart.set("zoomControl", am5map.ZoomControl.new(root, {}));
            const polygonSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {}));
            polygonSeries.mapPolygons.template.setAll({
                tooltipText: "{name}",
                interactive: true,
                fill: am5.color(0xc97232),
                stroke: am5.color(0xffffff),
                strokeWidth: 0.5
            });
            polygonSeries.mapPolygons.template.states.create("hover", {
                fill: am5.color(0x994202),
            });

            // countries2 maps the country code to its available subdivision map files
            const countryInfo = am5geodata_data_countries2[countryCode];
            if (!countryInfo || !countryInfo.maps || !countryInfo.maps.length) {
                chart.children.push(am5.Label.new(root, {
                    text: "No subdivision map available for " + countryName,
                    x: am5.percent(50),
                    y: am5.percent(50),
                    centerX: am5.percent(50),
                    centerY: am5.percent(50)
                }));
                return;
            }
            am5.net.load("https://cdn.amcharts.com/lib/5/geodata/json/" + countryInfo.maps[0] + ".json", chart).then(function (result) {
                polygonSeries.set("geoJSON", am5.JSONParser.parse(result.response));
                // Data last: polygons are matched by id, so our names replace the geodata names in the tooltip
                polygonSeries.data.setAll(Object.keys(subdivisions).map(function (id) {
                    return { id: id, name: subdivisions[id] };
                }));
            });
        });
    </script>
<?php $this->endSection() ?>