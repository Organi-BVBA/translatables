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

## Usage

```php
$translatables = new Organi\Translatables();
echo $translatables->echoPhrase('Hello, Spatie!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Rob Van Keilegom](https://github.com/.)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
