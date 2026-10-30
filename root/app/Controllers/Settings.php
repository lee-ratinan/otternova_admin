<?php

namespace App\Controllers;

class Settings extends BaseController
{

    /**
     * List languages
     * @return string
     */
    public function languages(): string
    {
        $data = [
            'page_title' => 'Languages',
            'slug'       => 'settings-languages',
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
            'page_title' => 'Countries',
            'slug'       => 'settings-countries',
        ];
        return view('settings_countries', $data);
    }
}