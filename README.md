# System Text Provider
Laravel package that retrieves translations strings from Bonnier microsoervice and extends the laravel __() localization function.

## Setup
- ```composer require bonnier/system-text-provider```
- Register the provider in ```config/app.php```
```php
    ...
    'providers' => [
        ...
       Bonnier\SystemText\SystemTextProvider::class, 
    ],
```
- Setup configuration in ```config/services.php```
```php
    'systemtext' => [
        'sitemanager' => '{url/to/sitemanager}',
        'microservice' => '{url/to/system-text-service}',
    ]
```

## Get translations
- run ```php artisan bonnier:fetch [--force]```

## Use translations
Use Laravels own translation helper functions ( ``__('key')`` and ``trans('key')`` ) with the following structure:

``echo __('bonnier::{appcode}/{brandcode}/messages.{translation_key}');`` => ``echo __('bonnier::brand_site/gds/messages.welcome');``