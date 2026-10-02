<?php

/*
| Csillám's weather report. The parent picks a city in Settings (stored on the device only);
| our server asks Open-Meteo (free, no key) for it, so the child's own location and IP
| never leave the app. Keep the list in step with resources/js/modules/beszed/config/cities.js.
*/

return [
    'url' => env('WEATHER_URL', 'https://api.open-meteo.com/v1/forecast'),
    'default' => 'budapest',
    'cache_minutes' => 30,
    'timeout' => 4,

    'cities' => [
        'budapest' => ['name' => 'Budapest', 'lat' => 47.4979, 'lon' => 19.0402],
        'debrecen' => ['name' => 'Debrecen', 'lat' => 47.5316, 'lon' => 21.6273],
        'szeged' => ['name' => 'Szeged', 'lat' => 46.2530, 'lon' => 20.1414],
        'miskolc' => ['name' => 'Miskolc', 'lat' => 48.1035, 'lon' => 20.7784],
        'pecs' => ['name' => 'Pécs', 'lat' => 46.0727, 'lon' => 18.2323],
        'gyor' => ['name' => 'Győr', 'lat' => 47.6875, 'lon' => 17.6504],
        'nyiregyhaza' => ['name' => 'Nyíregyháza', 'lat' => 47.9554, 'lon' => 21.7167],
        'kecskemet' => ['name' => 'Kecskemét', 'lat' => 46.9062, 'lon' => 19.6913],
        'szekesfehervar' => ['name' => 'Székesfehérvár', 'lat' => 47.1860, 'lon' => 18.4221],
        'szombathely' => ['name' => 'Szombathely', 'lat' => 47.2307, 'lon' => 16.6218],
        'eger' => ['name' => 'Eger', 'lat' => 47.9025, 'lon' => 20.3772],
        'veszprem' => ['name' => 'Veszprém', 'lat' => 47.0933, 'lon' => 17.9115],
    ],
];
