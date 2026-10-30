<?php

use App\Modules\OPS\Queries\LogCursor;
use App\Support\SecurityLog\SecurityEventKind;
use Symfony\Component\Finder\Finder;

// TL-03 (TASK-061; PT-18; LG-02; LOCALIZATION §3): every audit action the application records reads as one plain sentence
// on PG-063, and every security event kind as its label on PG-064, in both languages; the keyset cursor reads back what
// it wrote and refuses one this application did not sign (MSG-26; SM-01).

/** @return array<string, string> */
function logCatalogue(string $locale): array
{
    return json_decode((string) file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The audit actions written in `app/`: every quoted module code in the past tense — a command's own type, its
 * `TYPE` constant, excluded.
 *
 * @return list<string>
 */
function recordedActions(): array
{
    $codes = [];
    $types = [];

    foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
        $source = $file->getContents();
        preg_match_all("/'([A-Z]{2,4}\\.[A-Z][A-Za-z]+)'/", $source, $found);
        preg_match_all("/TYPE = '([A-Z]{2,4}\\.[A-Za-z]+)'/", $source, $declared);
        $codes = [...$codes, ...$found[1]];
        $types = [...$types, ...$declared[1]];
    }

    $actions = array_filter(array_diff(array_unique($codes), $types), fn (string $code): bool => preg_match('/(ed|Set|Sweep|Overridden|Reset|Sent)$/', $code) === 1);
    sort($actions);

    return array_values($actions);
}

it('gives every recorded audit action its sentence in both languages (PT-18; BR-XC-01)', function () {
    $actions = recordedActions();

    expect($actions)->not->toBeEmpty()->toContain('INV.ReservationCreated', 'PRJ.ProjectCreated', 'IAM.UserCreated');

    foreach (['id', 'en'] as $locale) {
        $catalogue = logCatalogue($locale);

        foreach ($actions as $action) {
            expect($catalogue)->toHaveKey("activity.action.{$action}");
        }
    }
});

it('gives every security event kind its label in both languages (LG-02)', function () {
    foreach (['id', 'en'] as $locale) {
        $catalogue = logCatalogue($locale);

        foreach (SecurityEventKind::cases() as $kind) {
            expect($catalogue)->toHaveKey("security_log.kind.{$kind->value}");
        }
    }
});

it('reads back the cursor it wrote and refuses one it did not sign (PF-15; MSG-26; SM-01)', function () {
    $cursor = LogCursor::of('2026-10-28 03:00:00.123456+00', 42);

    expect(LogCursor::read($cursor))->toBe(['2026-10-28 03:00:00.123456+00', 42]);

    [$payload] = explode('.', $cursor);
    $forged = rtrim(strtr(base64_encode((string) json_encode(['2026-10-28 03:00:00+00', 43])), '+/', '-_'), '=');

    foreach (["{$forged}.".explode('.', $cursor)[1], "{$payload}.", $payload, 'x.y.z', str_repeat('a', 201)] as $tampered) {
        expect(fn () => LogCursor::read($tampered))->toThrow(InvalidArgumentException::class);
    }
});
