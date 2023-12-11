<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\ValidationErrors;

use function Pest\Laravel\from;
use function Pest\Laravel\post;

it('captures session based validation errors', function () {
    Pulse::register([ValidationErrors::class => []]);
    Route::post('users', fn () => Request::validate([
        'email' => 'required',
    ]))->middleware('web');

    $response = post('users');

    $response->assertInvalid('email');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","email"]');

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","email"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});

it('captures one entry when a single field as multiple errors', function () {
    Pulse::register([ValidationErrors::class => []]);
    Route::post('users', fn () => Request::validate([
        'email' => 'string|min:5',
    ]))->middleware('web');

    $response = post('users', [
        'email' => 4,
    ]);

    $response->assertStatus(302);
    $response->assertInvalid([
        'email' => [
            'The email field must be a string.',
             'The email field must be at least 5 characters.',
        ],
    ]);
    $response->assertInvalid(['email' => 'The email field must be at least 5 characters.']);

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->whereType('validation_error')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('["POST","\/users","Closure","email"]');

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereType('validation_error')->orderBy('period')->get());
    expect($aggregates->pluck('key')->all())->toBe(array_fill(0, 4, '["POST","\/users","Closure","email"]'));
    expect($aggregates->pluck('aggregate')->all())->toBe(array_fill(0, 4, 'count'));
    expect($aggregates->pluck('value')->all())->toBe(array_fill(0, 4, '1.00'));
});
