## Installation

You can install the package via composer:

```bash
composer require organi/translatables
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Organi\Translatables\TranslatablesServiceProvider" --tag="translatables-migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Organi\Translatables\TranslatablesServiceProvider" --tag="translatables-config"
```

This is the contents of the published config file:

```php
return [
];
```

