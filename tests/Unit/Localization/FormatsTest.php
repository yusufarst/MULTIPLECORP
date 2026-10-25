<?php

use App\Support\Localization\Formats;
use Illuminate\Support\Facades\App;

// TL-03 (TASK-014; acceptance criterion 5): the formats of LOCALIZATION §3.3 and DESIGN_SYSTEM §16, one way in
// both languages, held to the cases the interface's formatter is held to (tests/Fixtures/Localization).

it('formats each value one way whatever the language (§3.3; OQ-04 (a))', function (string $locale) {
    App::setLocale($locale);
    $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/Localization/formats.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach ($fixture['cases'] as [$function, $arguments, $expected]) {
        expect(Formats::{$function}(...$arguments))->toBe($expected, $function.'('.implode(', ', array_map('json_encode', $arguments)).')');
    }
})->with(['id', 'en']);

it('refuses a value that is not exact, never rounding it', function (Closure $format) {
    expect($format)->toThrow(InvalidArgumentException::class);
})->with([
    'sen beyond two decimals' => [fn () => Formats::money('1250000.505')],
    'a comma decimal' => [fn () => Formats::money('1,5')],
    'a float written in exponent form' => [fn () => Formats::quantity('1e3', 'kg')],
    'a day that does not exist' => [fn () => Formats::date('2026-02-30')],
    'a month that does not exist' => [fn () => Formats::period('2026-13')],
]);
