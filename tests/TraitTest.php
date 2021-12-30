<?php

use Organi\Translatables\Tests\Product;

beforeEach(function () {
    $this->model = new Product();
});

it('can set all locales at once for a single attribute', function () {
    $title = 'test';

    $expected = array_fill_keys(config('translatables.accepted_locales'), $title);

    $this->model->setAllLocales('title', $title);

    expect($this->model->title->translations())->toMatchArray($expected);
});
