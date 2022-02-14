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
    'accepted_locales' => [
        'nl', 'en', 'fr', 'de',
    ],
];
```

## Usage

### Make your eloquent model translatable.

Create a `_translations` table for your model.
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('code');
    $table->timestamps();
});

Schema::create('products_translations', function (Blueprint $table) {
    $table->translations('translations');
    $table->string('name');
    $table->text('description');
});
```

Add the `HasTranslations` trait to your model and a `localizable` array containing the translatable fields.
```php
use Organi\Translatables\Traits\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    /**
     * The attributes that should be translatable.
     */
    protected array $localizable = [
        'name', 'description'
    ];
}
```

### Setting translatable fields

Now you can add a translation to the name attribute of your model.
```php
use Organi\Translatables\Models\Translation;
...
$product->name = Translation::make([
    'nl' => 'Lorem ipsum dolor sit amet',
    'en' => 'Lorem ipsum dolor sit amet',
    'fr' => 'Lorem ipsum dolor sit amet',
]);
```

You can also set an array and the model will turn it in a translation:
```php
$product->name = [
    'nl' => 'Lorem ipsum dolor sit amet',
    'en' => 'Lorem ipsum dolor sit amet',
    'fr' => 'Lorem ipsum dolor sit amet',
];
```

You can also set the property to anything other than an array.
In this case the default locale of the application will be set with the value.
```php
$product->name = 'Lorem ipsum dolor sit amet';

/* returns:
Organi\Translatables\Models\Translation {
  translations: array:4 [
    "nl" => "Lorem ipsum dolor sit amet"
    "en" => ""
    "fr" => ""
  ]
}
*/

```

Or set multiple translations for a single locale
```php
$product->setTranslations('nl', [
    'name' => 'Lorem ipsum dolor sit amet',
    'description' => 'Lorem ipsum dolor sit amet',
]);
```

Or set a single translation for a single locale
```php
$product->setTranslation('nl', 'name', 'Lorem ipsum dolor sit amet');
```

Or set a single string for all locales
```php
$product->setAllLocales('title', 'Lorem ipsum dolor sit amet');
```

### Getting translatable fields
Getting a translatable fields will return a `Translation` object.

Converting it to a string will automatically take the value of the active locale.
Some options are:
```php
echo $product->name;

$dt->title->__toString(),

(string) $dt->title
```

Or you can get a specific locale:
```php
echo $product->name->get('en');
```

### Filtering on a translatable fields
This package provides a `whereTranslation` function.
```php
$product = Product::whereTranslation('title', 'Lorem ipsum dolor sit amet')->first();
```

### Sorting on a translatable fields
This package provides a `sortByTranslation` function.
The function has 3 parameters:
- `field`: field that should be used for the sorting
- `locale`: locale that should be used for the sorting
- `direction` (optional defaults to `asc`): `asc` or `desc`
```php
$product = Product::sortByTranslation('title')->get();
```


