<?php

use function Pest\Faker\faker;
use Illuminate\Support\Facades\DB;
use Organi\Translatables\Tests\Product;

beforeEach(function () {
    $this->model = new Product();
});

it('can set all locales at once for a single attribute', function () {
    $title = faker()->sentence();

    $expected = array_fill_keys(config('translatables.accepted_locales'), $title);

    $this->model->setAllLocales('title', $title);

    expect($this->model->title->translations())->toMatchArray($expected);
});

it('translations get deleted when deleting product', function () {
    $title = faker()->sentence();

    $locales = config('translatables.accepted_locales');

    // Keep track of current translation count
    $count = DB::table($this->model->getTranslationsTable())->count();

    // Save the title translation to the database
    $this->model->setAllLocales('title', $title);
    $this->model->save();

    // We expect the count to be increased with the total amount of locales
    $newCount = DB::table($this->model->getTranslationsTable())->count();
    expect($newCount)->toBe($count + count($locales));

    // Delete the model
    $this->model->delete();

    // We expect the new count to be equal to the count before we created the modal
    $newCount = DB::table($this->model->getTranslationsTable())->count();
    expect($newCount)->toBe($count);
});

it('can use whereTranslation', function () {
    $product = new Product();
    $product->setAllLocales('title', faker()->sentence());
    $product->save();

    $search = Product::whereTranslation('title', $product->title->get('nl'))->first();

    expect($search->id)->toBe($product->id);
});

it('can use whereTranslation with a specific locale', function () {
    $product = new Product();

    // Generate a separate string for every locale
    foreach ($product->locales() as $locale) {
        $title = faker()->sentence();

        $product->setTranslation($locale, 'title', $title);
    }

    $product->save();

    // Different locales, no result should be returned
    $search = Product::whereTranslation('title', $product->title->get('en'), 'nl')->first();
    expect($search)->toBeNull();

    // Same locales. We should be able to find the product
    $search = Product::whereTranslation('title', $product->title->get('nl'), 'nl')->first();
    expect($search->id)->toBe($product->id);
});
