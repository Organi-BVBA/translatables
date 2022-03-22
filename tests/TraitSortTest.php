<?php

use Organi\Translatables\Tests\Product;

beforeEach(function () {
    $this->first = new Product();
    $this->first->setAllLocales('title', 'aaa');
    $this->first->save();

    $this->second = new Product();
    $this->second->setAllLocales('title', 'bbb');
    $this->second->save();
});

it('sorts asc by default', function () {
    $products = Product::orderByTranslation('title')->get();

    expect($products)
        ->not->toBeEmpty
        ->toHaveCount(2)
        ->sequence(
            fn ($product) => $product->title->toEqual($this->first->title),
            fn ($product) => $product->title->toEqual($this->second->title)
        );
});

it('sorts correctly by asc', function () {
    $products = Product::orderByTranslation('title', \App::getLocale(), 'asc')->get();

    expect($products)
        ->not->toBeEmpty
        ->toHaveCount(2)
        ->sequence(
            fn ($product) => $product->title->toEqual($this->first->title),
            fn ($product) => $product->title->toEqual($this->second->title)
        );
});

it('sorts correctly by desc', function () {
    $products = Product::orderByTranslation('title', \App::getLocale(), 'desc')->get();

    expect($products)
        ->not->toBeEmpty
        ->toHaveCount(2)
        ->sequence(
            fn ($product) => $product->title->toEqual($this->second->title),
            fn ($product) => $product->title->toEqual($this->first->title)
        );
});
