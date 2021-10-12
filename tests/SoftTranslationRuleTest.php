<?php

use function Pest\Faker\faker;
use Organi\Translatables\Rules\Translatable;

beforeEach(function () {
    $this->rule = new Translatable();
});

it('passes with single locale in array', function () {
    $v = $this->rule->passes('test', [
        'nl' => faker()->name,
    ]);

    expect($v)->toBeTrue();
});

it('passes with all accepted locales in array', function () {
    $value = [];

    foreach (config('translatables.accepted_locales') as $locale) {
        $value[$locale] = faker()->name;
    }

    $v = $this->rule->passes('test', $value);

    expect($v)->toBeTrue();
});

it('doesn\'t pass with invalid locale', function () {
    $v = $this->rule->passes('test', [
        'nl' => faker()->name,
        'es' => faker()->name,
    ]);

    expect($v)->toBeFalse();
});
