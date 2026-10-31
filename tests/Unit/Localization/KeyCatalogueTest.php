<?php

use Illuminate\Support\Facades\App;

// TL-03/TL-10 (TASK-014; acceptance criteria 1 and 3): the 897 keys of LOCALIZATION §6 in both languages, each
// with the text §6 gives, read the way §6 states — `{name}` the placeholder `:name`, several words joined by
// `_`, the number of a counted string `:count`, its English forms separated by `|`, `\\|` a literal bar —; no
// key missing or empty in either language, the two languages with the same keys and placeholders; and the
// framework resolves each key, never showing it as itself (§3.6).

/**
 * §6's rows as the catalogue holds them.
 *
 * @return array<string, array{id: string, en: string}>
 */
function inventory(): array
{
    $document = (string) file_get_contents(base_path('docs/07-ux-design/LOCALIZATION.md'));
    $section = substr($document, (int) strpos($document, "\n## 6. Key inventory"), (int) strpos($document, "\n## 7. Counts") - (int) strpos($document, "\n## 6. Key inventory"));
    $rows = [];

    foreach (explode("\n", $section) as $line) {
        if (! str_starts_with($line, '| `')) {
            continue;
        }

        $cells = array_map('trim', preg_split('/(?<!\\\\)\|/', substr($line, 1, -1)) ?: []);
        $counted = preg_match('/(?<!\\\\)\\\\\|/', str_replace('\\\\|', '', $cells[2])) === 1;
        $text = fn (string $cell): string => (string) preg_replace_callback(
            '/\{([^}]+)\}/',
            fn (array $match): string => $counted && $match[1] === 'n' ? ':count' : ':'.trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '_', $match[1]), '_'),
            str_replace(['\\\\|', '\\|'], '|', $cell),
        );

        $rows[trim($cells[0], '`')] = ['id' => $text($cells[1]), 'en' => $text($cells[2])];
    }

    return $rows;
}

/**
 * @return array<string, string>
 */
function catalogue(string $locale): array
{
    return json_decode((string) file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
}

it('holds every key of §6 in both languages with the text §6 gives (criterion 1)', function () {
    $inventory = inventory();

    expect($inventory)->toHaveCount(897)
        ->and(array_intersect_key(catalogue('id'), $inventory))->toBe(array_map(fn (array $row): string => $row['id'], $inventory))
        ->and(array_intersect_key(catalogue('en'), $inventory))->toBe(array_map(fn (array $row): string => $row['en'], $inventory));
});

it('adds a key after §6 only through the registry, with its Task, source and kind (§3.2; DIR-081 OB-04; OQ-24 (a))', function () {
    $registry = json_decode((string) file_get_contents(lang_path('registry/added-keys.json')), true, flags: JSON_THROW_ON_ERROR)['keys'];
    $added = array_keys(array_diff_key(catalogue('id'), inventory()));

    expect($added)->toBe(array_keys($registry));

    foreach ($registry as $key => $entry) {
        expect($entry)->toHaveKeys(['task', 'source', 'indonesian', 'english'])
            ->and($entry['indonesian'])->toBeIn(['canonical', 'proposed'], $key)
            ->and($entry['english'])->toBe('proposed', $key);
    }
});

it('misses no string and no placeholder in either language (§3.6; criterion 3)', function () {
    $id = catalogue('id');
    $en = catalogue('en');
    $placeholders = function (string $text): array {
        preg_match_all('/:([\p{L}_][\p{L}\p{N}_]*)/u', $text, $match);

        return array_values(array_unique(array_map('mb_strtolower', $match[1])));
    };

    expect(array_keys($en))->toBe(array_keys($id))
        ->and(array_filter([...$id, ...$en], fn (string $text): bool => trim($text) === ''))->toBe([]);

    foreach ($id as $key => $text) {
        expect($placeholders($en[$key]))->toEqualCanonicalizing($placeholders($text), $key);

        foreach (explode('|', $en[$key]) as $form) {
            expect(trim($form))->not->toBe('', $key);
        }
    }
});

it('resolves each key in each language, never as itself, and counts English in two forms (§3.3, §3.6)', function () {
    foreach (['id', 'en'] as $locale) {
        App::setLocale($locale);

        foreach (array_keys(catalogue($locale)) as $key) {
            expect(__($key))->not->toBe($key);
        }
    }

    App::setLocale('en');
    expect(trans_choice('messages.msg_12', 1))->toBe('Check the 1 marked field.')
        ->and(trans_choice('messages.msg_12', 3))->toBe('Check the 3 marked fields.')
        ->and(trans_choice('messages.msg_20', 1))->toBe('Too many attempts. Try again in 1 minute.');

    App::setLocale('id');
    expect(trans_choice('messages.msg_12', 3))->toBe('Periksa 3 isian yang ditandai.')
        ->and(__('messages.msg_08', ['jenis' => 'kuitansi', 'nomor' => 'ARJ/KWT/2026/10/0001', 'n' => 2]))
        ->toBe('Kuitansi sudah terbit dengan nomor ARJ/KWT/2026/10/0001. PDF gagal dibuat dan akan dicoba lagi otomatis (percobaan 2 dari 5).');
});

it('falls back to Indonesian, the canonical text, never to a key, for a locale it does not know (§2)', function () {
    App::setLocale('fr');

    expect(__('pages.pg_001.heading'))->toBe('Masuk ke MultipleCorp');
});
