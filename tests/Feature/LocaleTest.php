<?php

use App\Support\Localization\Locales;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-014; acceptance criteria 4 and 5): the request's language (LOCALIZATION §2) — before sign-in the
// whitelisted address key `bahasa`, nothing stored, an unknown value ignored for Indonesian (INT-001); after
// sign-in the session's, else the account's `users.locale`, else Indonesian, a choice made before sign-in never
// reaching the account —; the locale as WS-07's shared prop, the strings never shared; messages written in it;
// documents and business data in Indonesian whatever it is (§3.4).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);

    Route::middleware('web')->get('/__test/bahasa', fn () => Inertia::render('Fixtures/LanguageSwitch', [
        'message' => __('messages.msg_19'),
    ]));

    Authorization::catalogue();
});

afterEach(function () {
    @unlink($this->hotFile);
});

it('takes the address key before sign-in, an unknown value as Indonesian, and stores nothing (INT-001)', function (string $query, string $locale) {
    $response = $this->get('/__test/bahasa'.$query);

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('locale', $locale));
    $cookies = array_map(fn ($cookie): string => $cookie->getName(), $response->headers->getCookies());

    expect(session()->has(Locales::SESSION_KEY))->toBeFalse()
        ->and(preg_grep('/bahasa|locale|lang/i', $cookies))->toBe([]);
})->with([
    'no choice' => ['', 'id'],
    'Indonesian' => ['?bahasa=id', 'id'],
    'English' => ['?bahasa=en', 'en'],
    'an unknown value' => ['?bahasa=fr', 'id'],
    'a value that is not one' => ['?bahasa[]=en', 'id'],
]);

it('takes the session\'s language after sign-in, else the account\'s, else Indonesian — never the address (§2)', function (?string $account, ?string $session, string $locale) {
    $user = Authorization::owner();
    DB::table('users')->where('id', $user->id)->update(['locale' => $account]);
    $request = $this->actingAs($user->refresh());

    if ($session !== null) {
        $request = $request->withSession([Locales::SESSION_KEY => $session]);
    }

    $request->get('/__test/bahasa?bahasa='.($locale === 'id' ? 'en' : 'id'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', $locale));

    expect(DB::table('users')->where('id', $user->id)->value('locale'))->toBe($account);
})->with([
    'an account that never chose' => [null, null, 'id'],
    'an account in English' => ['en', null, 'en'],
    'the session over the account' => ['en', 'id', 'id'],
    'an unknown session value' => ['en', 'fr', 'en'],
]);

it('shares the locale and no string table; writes the page\'s message in its language (§3.8; WS-07)', function () {
    $this->get('/__test/bahasa?bahasa=en')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('locale', 'en')
        ->where('message', 'Incorrect email or password.')
        ->missing('translations')
        ->missing('strings'));

    $this->get('/__test/bahasa')->assertInertia(fn (AssertableInertia $page) => $page->where('message', 'E-mail atau kata sandi salah.'));
});

it('serves the browser suite\'s fixture page in either language with no session and no query (routes/fixtures.php)', function () {
    require base_path('routes/fixtures.php');
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get('/__fixtures/bahasa?bahasa=en')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Fixtures/LanguageSwitch', false)->where('locale', 'en'));

    expect($queries)->toBe(0)
        ->and(app('router')->getRoutes()->match(request()->create('/__fixtures/bahasa'))->gatherMiddleware())->not->toContain('web');
});

it('keeps documents and business data in Indonesian whatever the interface language (§3.4; criterion 5)', function () {
    App::setLocale('en');

    $document = Locales::forDocuments(fn () => [__('documents.doc_06'), __('pages.pg_001.heading')]);

    expect($document)->toBe([trans('documents.doc_06', locale: 'id'), 'Masuk ke MultipleCorp'])
        ->and(App::getLocale())->toBe('en')
        // A value inside a string is data: never translated (DESIGN_SYSTEM §16).
        ->and(__('messages.msg_15', ['daftar_catatan_yang_cocok' => 'Pembayaran PT Maju Jaya']))
        ->toBe('Possible duplicate record. Pembayaran PT Maju Jaya. If this really is a different record, give a reason and continue.');
});
