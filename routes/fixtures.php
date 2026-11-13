<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SessionDeadline;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\Support\Fixtures\AccountFixture;
use Tests\Support\Fixtures\AdjustmentFixture;
use Tests\Support\Fixtures\AdministrationFixture;
use Tests\Support\Fixtures\CaseFixture;
use Tests\Support\Fixtures\ClientReturnFixture;
use Tests\Support\Fixtures\CompletionFixture;
use Tests\Support\Fixtures\ConfirmationFixture;
use Tests\Support\Fixtures\CountFixture;
use Tests\Support\Fixtures\CreditFixture;
use Tests\Support\Fixtures\DeliveryFixture;
use Tests\Support\Fixtures\DispatchFixture;
use Tests\Support\Fixtures\DocumentFixture;
use Tests\Support\Fixtures\DropshipFixture;
use Tests\Support\Fixtures\EvidenceFixture;
use Tests\Support\Fixtures\ExportFixture;
use Tests\Support\Fixtures\FilesFixture;
use Tests\Support\Fixtures\GoogleFixture;
use Tests\Support\Fixtures\GuardsFixture;
use Tests\Support\Fixtures\HandoverFixture;
use Tests\Support\Fixtures\ImportFixture;
use Tests\Support\Fixtures\InvoiceFixture;
use Tests\Support\Fixtures\JourneyCompletionFixture;
use Tests\Support\Fixtures\JourneyDocumentFixture;
use Tests\Support\Fixtures\JourneyFinanceFixture;
use Tests\Support\Fixtures\JourneyFixture;
use Tests\Support\Fixtures\JourneyStockFixture;
use Tests\Support\Fixtures\LogsFixture;
use Tests\Support\Fixtures\NumberingFixture;
use Tests\Support\Fixtures\OtherCashFixture;
use Tests\Support\Fixtures\ProjectFixture;
use Tests\Support\Fixtures\PurchaseFixture;
use Tests\Support\Fixtures\QuotationFixture;
use Tests\Support\Fixtures\ReauthFixture;
use Tests\Support\Fixtures\ReceivingFixture;
use Tests\Support\Fixtures\ReservationFixture;
use Tests\Support\Fixtures\ReturnFixture;
use Tests\Support\Fixtures\ShellFixture;
use Tests\Support\Fixtures\StockFixture;

// The local stack's fixture pages for the browser suite (TEST_STRATEGY §7), loaded only in the `local`
// environment (bootstrap/app.php): no ROUTE_CONTRACTS row, never in testing, staging or production. Each serves
// a layer's check before the pages that will carry it exist. They run without the web group's session, cookies
// and CSRF token — a page before sign-in stores nothing (INT-001) —, so they touch no database — but for the
// sign-in journeys' account, which the migrated stack keeps (scripts/verify.sh).

// SessionDeadline gives the shell stub's answers the account key a signed-in page carries (TASK-093; UXH-02); with no
// session and no account it adds nothing to the other pages here.
Route::middleware([SetLocale::class, HandleInertiaRequests::class, SessionDeadline::class])->group(function (): void {
    // TL-09–TL-12 (TASK-014): INT-001's switch on a page with a field, before sign-in, so the switch is shown to
    // keep the unsent input and the layout (LOCALIZATION §3.7).
    Route::get('__fixtures/bahasa', fn () => Inertia::render('Fixtures/LanguageSwitch'));

    // TL-09–TL-12 and TL-14 (TASK-015): the design system's component gallery in both languages, before the
    // pages that compose it exist; its pages enter only a local build (VITE_FIXTURE_PAGES; TL-13).
    Route::get('__fixtures/komponen', fn () => Inertia::render('Fixtures/Components'));

    // TL-09–TL-12 (TASK-016): the shell with a persona's hints — the page props stand in for the shared ones of a
    // signed-in account —, and a command whose session the test ends, so the re-authentication in place and the
    // step-up are proven against a stub of the sign-in (acceptance criteria 7, 8). The stub's state lives in the
    // cache under the run's key, never in a session or the database; its password is a test value of this file.
    Route::get('__fixtures/shell', fn (Request $request) => Inertia::render('Fixtures/Shell', ShellFixture::props($request)));
    Route::post('__fixtures/shell/kedaluwarsa', fn (Request $request) => ShellFixture::expire($request));
    Route::post('__fixtures/shell/masuk', fn (Request $request) => ShellFixture::signIn($request));
    Route::post('__fixtures/shell/konfirmasi', fn (Request $request) => ShellFixture::confirm($request));
    Route::post('__fixtures/shell/perintah', fn (Request $request) => ShellFixture::command($request, stepUp: false));
    Route::post('__fixtures/shell/akses', fn (Request $request) => ShellFixture::command($request, stepUp: true));
});

// TL-09–TL-12 (TASK-017): a synthetic account and its reset link for one run, so the sign-in, reset and logout
// journeys run against the real routes of PG-001 and PG-003.
Route::post('__fixtures/akun', [AccountFixture::class, 'create']);
// TL-09 (TASK-093): an account's session aged past the idle limit; the recorded outcome of commands by command_id.
Route::post('__fixtures/reauth/usang', [ReauthFixture::class, 'age']);
Route::get('__fixtures/reauth/perintah', [ReauthFixture::class, 'commands']);
Route::post('__fixtures/reauth/lingkup', [ReauthFixture::class, 'scope']);

// TL-09–TL-12 (TASK-019): the faked Google — sign-in on with a synthetic client and the token exchange answered
// locally — for the Google journey, armed and disarmed by the run.
// Never in the console: package discovery loads these routes, after a `--no-dev` install too, without the fixture classes.
if (! app()->runningInConsole()) {
    GoogleFixture::apply();
}
Route::post('__fixtures/google', [GoogleFixture::class, 'arm']);

// TL-09–TL-12 (TASK-021): PG-061's company for the run and go-live, whose store is TASK-077's, recorded for the
// requests while the run has armed it.
if (! app()->runningInConsole()) {
    NumberingFixture::apply();
}
Route::post('__fixtures/penomoran', [NumberingFixture::class, 'arm']);

// TL-09–TL-12 (TASK-029): the synthetic subjects of the issuing Tasks for the requests of an armed run.
if (! app()->runningInConsole()) {
    DocumentFixture::apply();
}

// TL-09 (TASK-063): CJ-14's row bound lowered for a run's own requests that name one (X-Fixture-Export-Bound), never raised.
if (! app()->runningInConsole()) {
    JourneyDocumentFixture::apply();
}

// TL-09–TL-12 (TASK-030): PG-038's company for the run, with a reference-less client order for IP-05's warning.
Route::post('__fixtures/bukti', [EvidenceFixture::class, 'create']);

// TL-09–TL-12 (TASK-022): a sample page for an upload through the pipeline, its download by ROUTE-226 and a
// rendition's state by ROUTE-285, for a signed-in account; the upload route and the evidence registration are TASK-030's.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('__fixtures/berkas', [FilesFixture::class, 'page']);
    // The run's own requests carry no form token; the application's routes keep the check (RequestForgeryTest).
    Route::post('__fixtures/berkas', [FilesFixture::class, 'upload'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/berkas/rendisi', [FilesFixture::class, 'rendition'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-026): stand-ins of PG-013's and PG-017's party fields, for J-MD-02's and J-MD-03's inline
    // creation through the real ROUTE-141 and ROUTE-143, before the project and purchase forms exist.
    Route::get('__fixtures/formulir-pihak', fn () => Inertia::render('Fixtures/PartyForms'));

    // TL-09–TL-12 (TASK-029; J-DOC-01; CJ-15): a draft prepared from a synthetic project through ROUTE-221's action, and
    // its rendition rendered or failed as the absent queue worker would, before the issuing pages exist.
    Route::post('__fixtures/dokumen', [DocumentFixture::class, 'draft'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dokumen/rendisi', [DocumentFixture::class, 'rendition'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-031): two correction cases of the run's company, one closable and one carrying a residual.
    Route::post('__fixtures/kasus', [CaseFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-032): the run's company with its PROJECT numbering started or not, a client, goods and a service,
    // the account's grants on it, and optionally a project created through ROUTE-151's action.
    Route::post('__fixtures/proyek', [ProjectFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-033): the run's company with its PROJECT and DOC-01 numbering started, a project of `lines` goods
    // and, by `stage`, its quotation drafted with a validity and prices or also issued through the quotation's actions.
    Route::post('__fixtures/penawaran', [QuotationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-034): a mixed project with the client's SPK registered as order evidence, confirmed when asked.
    Route::post('__fixtures/konfirmasi', [ConfirmationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-035): that confirmed project with its default invoice item, a required SPK and the client's SPK uploaded.
    Route::post('__fixtures/administrasi', [AdministrationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-038): a confirmed project of one warehouse line, an opening lot of its product, reserved when asked.
    Route::post('__fixtures/reservasi', [ReservationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-047): a confirmed service-only project, the BAST's numbering, a delivery proof, a handover recorded when asked.
    Route::post('__fixtures/serah-terima', [HandoverFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-049): a confirmed service project, the layouts' numbering, an evidence, a draft or issued invoice when asked.
    Route::post('__fixtures/invoice', [InvoiceFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09 (TASK-062): ReconcileGuards' differences for a journey's companies, read-only (TS-11).
    Route::get('__fixtures/rekonsiliasi', [GuardsFixture::class, 'differences']);
    // TL-09 (TASK-062): CJ-01's run — its numbering, client, supplier, product and rack —, and the project's read-only trace.
    Route::post('__fixtures/jurnal', [JourneyFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::get('__fixtures/jurnal/jejak', [JourneyFixture::class, 'trace']);
    // TL-09 (TASK-062): CJ-05's stock at two racks, CJ-07's ambiguous code, CJ-08's SIPLAH preconditions.
    Route::post('__fixtures/journey-c/stok', [JourneyStockFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/journey-c/kembar', [JourneyStockFixture::class, 'twin'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/journey-c/siplah', [JourneyStockFixture::class, 'siplah'])->withoutMiddleware(PreventRequestForgery::class);
    // TL-09 (TASK-063): CJ-06's backdating — a company whose previous month's invoice period is not live and has no counter, and its one-company invoice Admin.
    Route::post('__fixtures/journey-b/periode', [JourneyFinanceFixture::class, 'period'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/journey-b/admin', [JourneyFinanceFixture::class, 'admin'])->withoutMiddleware(PreventRequestForgery::class);
    // TL-09 (TASK-063): CJ-14's older receivable billed past its due date; CJ-15's sweeper tick for a failed rendition and its issued paper's text.
    Route::post('__fixtures/journey-b2/piutang', [JourneyDocumentFixture::class, 'olderReceivable'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/journey-b2/rendisi-ulang', [JourneyDocumentFixture::class, 'retry'])->withoutMiddleware(PreventRequestForgery::class);
    Route::get('__fixtures/journey-b2/isi', [JourneyDocumentFixture::class, 'paper']);
    // TL-09 (TASK-063): CJ-17's cancellation world — TASK-038's reserved project with its product named for PG-019.
    Route::post('__fixtures/journey-b3/batal', [JourneyCompletionFixture::class, 'cancellation'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-060): the signed-in account's PENDING exports generated by the job, as a queue worker would.
    Route::post('__fixtures/ekspor/jalankan', [ExportFixture::class, 'run'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/credit', [CreditFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-054): that company's account and project, an open purchase, a transfer proof and a supplier bill to cite on PG-049.
    Route::post('__fixtures/kas-lain', [OtherCashFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-057): a company's schemes and seeds for the cutoff, an Admin's sign-off, a batch's owed job run here, a committed twin of another company.
    Route::post('__fixtures/impor', [ImportFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/impor/setujui', [ImportFixture::class, 'signOff'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/impor/kerjakan', [ImportFixture::class, 'work'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/impor/kembar', [ImportFixture::class, 'twin'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-055): an invoice run's project made ready to complete, or left with its unmet fulfilment for the Force Complete.
    Route::post('__fixtures/penyelesaian', [CompletionFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-037): a company with its PURCHASE and PO numbering, suppliers, goods, a bill and a confirmed project with its shortage.
    Route::post('__fixtures/purchase', [PurchaseFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-039): over the purchase fixture, the Nota's numbering, a rack, a serialized purchase and a purchase of the project's demand.
    Route::post('__fixtures/receiving', [ReceivingFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-040): the four quantities at a rack, a movement to reverse, another company's lot, a pending case, a Company-A-only Admin.
    Route::post('__fixtures/stock', [StockFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/stock/pending', [StockFixture::class, 'pending'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/stock/grant', [StockFixture::class, 'grant'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-041): DOC-03's numbering; a Company-A-only Admin without the inter-company authority.
    Route::post('__fixtures/adjustments', [AdjustmentFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/adjustments/plain', [AdjustmentFixture::class, 'plain'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/counts/admin', [CountFixture::class, 'admin'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/returns', [ReturnFixture::class, 'grant'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dispatch', [DispatchFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dispatch/plain', [DispatchFixture::class, 'plain'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dispatch/serials', [DispatchFixture::class, 'serials'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dispatch/kembar', [DispatchFixture::class, 'twin'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/deliveries', [DeliveryFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dropship', [DropshipFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/client-returns', [ClientReturnFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-061): a page and more of the run's company's audit events, one without a company, the security events a notice opens.
    Route::post('__fixtures/log', [LogsFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
});
