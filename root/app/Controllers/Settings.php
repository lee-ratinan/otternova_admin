<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Settings extends BaseController
{

    /**
     * List languages
     * @return string
     */
    public function languages(): string
    {
        $data = [
            'page_title'         => 'Languages',
            'slug'               => 'settings-languages',
            'allLanguages'       => listLanguages(),
            'supportedLanguages' => listSupportedLanguages(),
        ];
        return view('settings_languages', $data);
    }

    /**
     * List countries and their supported subdivisions, currencies, and timezones
     * @return string
     */
    public function countries(): string
    {
        $data = [
            'page_title'         => 'Countries',
            'slug'               => 'settings-countries',
            'allCountries'       => listCountries(),
            'supportedCountries' => listSupportedCountries(),
            'timezones'          => listSupportedTimezones(),
            'currencies'         => listSupportedCurrencies(),
            'subdivisions'       => listSupportedSubdivisions(),
            'regions'            => splitCountryListByRegion(),
        ];
        return view('settings_countries', $data);
    }

    /**
     * Show country details
     * @param string $countryCode
     * @return string|RedirectResponse
     */
    public function countriesView(string $countryCode): string|RedirectResponse
    {
        $countryCode        = strtoupper($countryCode);
        $supportedCountries = listSupportedCountries();
        if (!isset($supportedCountries[$countryCode])) {
            return redirect()->back();
        }
        $regions = splitCountryListByRegion();
        $region  = '';
        foreach ($regions as $regionName => $listCountries) {
            if (in_array($countryCode, $listCountries)) {
                $region = $regionName;
                break;
            }
        }
        $full_data = [
            "ID" => [
                "full_name" => "Republic of Indonesia",
                "languages" => ["id", "jv", "su"]
            ],
            "MY" => [
                "full_name" => "Malaysia",
                "languages" => ["ms"]
            ],
            "PH" => [
                "full_name" => "Republic of the Philippines",
                "languages" => ["tl", "en"]
            ],
            "SG" => [
                "full_name" => "Republic of Singapore",
                "languages" => ["en-SG", "zh-SG", "ms", "ta"]
            ],
            "VN" => [
                "full_name" => "Socialist Republic of Viet Nam",
                "languages" => ["vi"]
            ],
            "TH" => [
                "full_name" => "Kingdom of Thailand",
                "languages" => ["th"]
            ],
            "TW" => [
                "full_name" => "Republic of China (Taiwan)",
                "languages" => ["zh-TW"]
            ],
            "JP" => [
                "full_name" => "Japan",
                "languages" => ["ja"]
            ],
            "KR" => [
                "full_name" => "Republic of Korea",
                "languages" => ["ko"]
            ],
        ];
        $data = [
            'page_title'   => 'Supported Country: ' . $supportedCountries[$countryCode],
            'slug'         => 'settings-countries-view',
            'country_code' => $countryCode,
            'country_name' => $supportedCountries[$countryCode],
            'full_name'    => @$full_data[$countryCode]['full_name'] ?? '??',
            'languages'    => @$full_data[$countryCode]['languages'] ?? [],
            'all_lang'     => listLanguages(),
            'region'       => $region,
            'timezones'    => listSupportedTimezones($countryCode),
            'currencies'   => listSupportedCurrencies($countryCode),
            'subdivisions' => listSupportedSubdivisions($countryCode),
            'breadcrumbs'  => [
                base_url('admin/settings/countries') => 'Countries'
            ]
        ];
        return view('settings_countries_view', $data);
    }
}