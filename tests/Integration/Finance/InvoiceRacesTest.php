<?php

use App\Modules\FIN\Actions\VoidInvoice;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\ORG\Enums\SequenceType;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\DOC\DocumentFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\DocumentVersionFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;

// TL-05 (TASK-049; AX-16; AX-22; M-15–M-19; M-37; M-38; C-16; C-31; criteria 3, 9) on real PostgreSQL sessions: two issues racing draw distinct numbers within the cap, the second issue of one draft a CONFLICT; two saves of one draft, the later a CONFLICT; a void racing an application:
// the first commits, the void after a new reduction a CONFLICT with the current list, an application after the void REJECTED; the reconciliation suite clean after each.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    // The truncation between tests removes the seeded catalogue.
    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
});

function invoiceRaceDifferences(): array
{
    return [...(new Reconciler)->differences(DB::connection(), Families::named('invoice_balances') ?? throw new RuntimeException('invoice guard')),
        ...(new Reconciler)->differences(DB::connection(), Families::named('project_commercial_balances') ?? throw new RuntimeException('commercial guard'))];
}

it('draws two distinct numbers for two invoices issued at once (M-15; M-16; AX-16; criterion 3)', function () {
    $first = $this->world->draft($this->project, '1000000');
    $second = $this->world->draft($this->project, '1000000');
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $goLive = substr(BusinessDate::today(), 0, 4).'-01-01';

    $results = Sessions::run([
        'first' => static function () use ($actor, $project, $first, $goLive): string {
            Numbering::goLive($goLive);

            return InvoiceWorld::hold(static fn (): string => InvoiceWorld::issue($actor, $project, $first), 'the first issue holds the project');
        },
        'second' => static function () use ($actor, $project, $second, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the first issue holds the project');

            return InvoiceWorld::issue($actor, $project, $second);
        },
    ]);

    $numbers = DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->whereIn('d.invoice_id', [$first, $second])->pluck('v.formatted_number')->all();
    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])->and(array_unique($numbers))->toHaveCount(2)->and(invoiceRaceDifferences())->toBe([]);
});

it('lets one of two issues past the cap, the other REJECTED with what remains (C-31; AX-16; LK-20)', function () {
    $first = $this->world->draft($this->project, '2000000');
    $second = $this->world->draft($this->project, '2000000');
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $goLive = substr(BusinessDate::today(), 0, 4).'-01-01';

    $results = Sessions::run([
        'first' => static function () use ($actor, $project, $first, $goLive): string {
            Numbering::goLive($goLive);

            return InvoiceWorld::hold(static fn (): string => InvoiceWorld::issue($actor, $project, $first), 'the first issue holds the cap');
        },
        'second' => static function () use ($actor, $project, $second, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the first issue holds the cap');

            return InvoiceWorld::issue($actor, $project, $second);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('REJECTED C-31')
        ->and(DB::table('project_commercial_balances')->where('project_id', $project)->value('invoiced_sales_value'))->toBe('2000000.00')->and(invoiceRaceDifferences())->toBe([]);
});

it('issues one draft once when pressed twice at once, the second a CONFLICT with the number (M-19; BD-06; criterion 3)', function () {
    $id = $this->world->draft($this->project, '1000000');
    $actor = $this->world->actorId;
    $project = $this->project->id;
    $goLive = substr(BusinessDate::today(), 0, 4).'-01-01';

    $results = Sessions::run([
        'first' => static function () use ($actor, $project, $id, $goLive): string {
            Numbering::goLive($goLive);

            return InvoiceWorld::hold(static fn (): string => InvoiceWorld::issue($actor, $project, $id), 'the first press holds the draft');
        },
        'second' => static function () use ($actor, $project, $id, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the first press holds the draft');

            return InvoiceWorld::issue($actor, $project, $id);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toStartWith('CONFLICT')
        ->and(DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.invoice_id', $id)->where('v.state', 'ISSUED')->count())->toBe(1)->and(invoiceRaceDifferences())->toBe([]);
});

it('answers the later of two saves of one draft CONFLICT, even on different lines (M-38; ST-01; criterion 3)', function () {
    $id = $this->world->draft($this->project, '1000');
    $lock = (int) DB::table('invoices')->where('id', $id)->value('lock_version');
    $actor = $this->world->actorId;
    $project = $this->project->id;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::saveInCommand($actor, $project, $id, $lock, '2000'), 'the first save holds the draft'),
        'second' => static function () use ($actor, $project, $id, $lock): string {
            Barrier::await('the first save holds the draft');

            return InvoiceWorld::saveInCommand($actor, $project, $id, $lock, '3000');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('CONFLICT ST-01')->and(DB::table('invoice_versions')->where('invoice_id', $id)->value('sales_value'))->toBe('2000.00');
});

it('leaves one of a void and an application committed, whichever holds the invoice first (M-37; ST-07; C-15; criterion 9)', function (string $holder) {
    $id = $this->world->issued($this->project, '1000000');
    $payment = InvoiceWorld::payment($id, '400000');
    $actor = $this->world->actorId;
    $paymentId = $payment->id;
    $goLive = substr(BusinessDate::today(), 0, 4).'-01-01';

    $results = $holder === 'void' ? Sessions::run([
        'void' => static function () use ($actor, $id, $goLive): string {
            Numbering::goLive($goLive);

            return InvoiceWorld::hold(static fn (): string => InvoiceWorld::voidInCommand($actor, $id), 'the first command holds the invoice');
        },
        'application' => static function () use ($actor, $id, $paymentId): string {
            Barrier::await('the first command holds the invoice');

            return InvoiceWorld::applyInCommand($actor, $id, $paymentId, '400000');
        },
    ]) : Sessions::run([
        'application' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::applyInCommand($actor, $id, $paymentId, '400000'), 'the first command holds the invoice'),
        'void' => static function () use ($actor, $id, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the first command holds the invoice');

            return InvoiceWorld::voidInCommand($actor, $id);
        },
    ]);

    if ($holder === 'void') {
        expect($results['void'])->toBe('COMMITTED')->and($results['application'])->toBe('REJECTED C-15')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('VOIDED');
    } else {
        expect($results['application'])->toBe('COMMITTED')->and($results['void'])->toBe('CONFLICT ST-07')->and(DB::table('invoices')->where('id', $id)->value('state'))->toBe('ISSUED')
            ->and(DB::table('invoice_balances')->where('invoice_id', $id)->value('applied_amount'))->toBe('400000.00');
    }

    expect(invoiceRaceDifferences())->toBe([]);
})->with(['the void first' => 'void', 'the application first' => 'application']);

it('rolls an issue that fails after its allocation back with its number: the draft still a draft, the next issue taking that number (M-17; NM-04; L-41)', function () {
    $id = $this->world->draft($this->project, '1000000');
    $year = substr(BusinessDate::today(), 0, 4);
    // The rendition's row is written after the number is drawn; the listener fails the command there.
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed): void {
        if ($armed && str_contains($query->sql, 'insert into "document_renditions"')) {
            throw new RuntimeException('forced after the allocation');
        }
    });

    expect(fn () => InvoiceWorld::issue($this->world->actorId, $this->project->id, $id))->toThrow(RuntimeException::class, 'forced after the allocation');
    $armed = false;

    expect(DB::table('invoices')->where('id', $id)->value('state'))->toBe('DRAFT')
        ->and(Numbering::next($this->world->company, SequenceType::Doc06, $year))->toBeNull()
        ->and(DB::table('project_commercial_balances')->where('project_id', $this->project->id)->value('invoiced_sales_value'))->toBe('0.00')
        ->and(DB::table('invoice_balances')->where('invoice_id', $id)->exists())->toBeFalse();

    expect(InvoiceWorld::issue($this->world->actorId, $this->project->id, $id))->toBe('COMMITTED')
        ->and(InvoiceSubjects::number($id))->toEndWith('/0001')
        ->and(invoiceRaceDifferences())->toBe([]);
});

it('holds the Kuitansi cap for two Kuitansi of different numbering periods racing: the invoice\'s guard meets them, one REJECTED (AX-31; C-43; LK-25; criterion 10)', function () {
    $year = (int) substr(BusinessDate::today(), 0, 4);
    Numbering::scheme($this->world->company, SequenceType::Doc05, ($year - 1).'-01-01', 'YEAR', 'LAMA/KW/{tahun}/{nomor:4}');
    Numbering::seed($this->world->company, SequenceType::Doc05, (string) ($year - 1), 1);
    $id = $this->world->issued($this->project, '1000000');
    $actor = $this->world->actorId;
    $thisYear = InvoiceWorld::prepareKuitansi($actor, $id, '700000');
    // ROUTE-221 keeps one open draft per invoice; a second one stands beside it the way a Kuitansi's revision does.
    $lastYear = DocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'document_type_code' => 'DOC-05', 'mode' => 'PRE_PAYMENT', 'project_id' => null, 'invoice_id' => $id])->id;
    DocumentVersionFactory::new()->createOne(['document_id' => $lastYear, 'company_id' => $this->world->company->id, 'document_type_code' => 'DOC-05', 'version_no' => 1, 'state' => 'DRAFT', 'business_date' => BusinessDate::today(), 'kuitansi_amount' => '700000.00']);
    $today = BusinessDate::today();
    $old = ($year - 1).'-06-15';
    $goLive = "{$year}-01-01";

    $results = Sessions::run([
        'first' => static function () use ($actor, $id, $thisYear, $today, $goLive): string {
            Numbering::goLive($goLive);

            return InvoiceWorld::hold(static fn (): string => InvoiceWorld::issueKuitansi($actor, $id, $thisYear, $today), 'the first Kuitansi holds the invoice');
        },
        'second' => static function () use ($actor, $id, $lastYear, $old, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the first Kuitansi holds the invoice');

            return InvoiceWorld::issueKuitansi($actor, $id, $lastYear, $old);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-43'])
        ->and(DB::table('document_versions')->where('document_id', $lastYear)->value('state'))->toBe('DRAFT')
        ->and(Numbering::next($this->world->company, SequenceType::Doc05, (string) ($year - 1)))->toBe(1)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(invoiceRaceDifferences())->toBe([]);
});

it('commits a replacement with its inherited act at a real commit, and refuses an entry-error void without one there (C-17; C-56; L-30; deferred triggers)', function () {
    $id = $this->world->issued($this->project, '1000000');
    $act = InvoiceWorld::bill($id, BusinessDate::today(), '2099-12-31');
    $payment = InvoiceWorld::payment($id, '400000');
    $application = InvoiceWorld::apply($id, $payment, '400000');
    $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->world->company->id, 'evidence_type' => 'OTHER']);
    $invoice = Invoice::query()->findOrFail($id);
    $input = new InvoiceCorrection('ENTRY_ERROR_REPLACED', 'Salah catat nilai', BusinessDate::today(), false, evidenceId: $evidence->id, content: InvoiceWorld::valueOnly('900000'), applications: [$application => InvoiceCorrection::RETARGET]);

    expect(InvoiceWorld::word(app(VoidInvoice::class)->run((string) Str::uuid(), $this->world->actorId, $invoice, $invoice->lock_version, $input, ['invoice' => $id])))->toBe('COMMITTED');
    $replacement = DB::table('invoices')->where('replaces_invoice_id', $id)->sole();
    $inherited = DB::table('invoice_billing_acts')->where('invoice_id', $replacement->id)->sole();
    expect($replacement->state)->toBe('ISSUED')->and($inherited->kind)->toBe('INHERITED')->and($inherited->inherited_from_act_id)->toBe($act)->and(invoiceRaceDifferences())->toBe([]);

    // The deferred trigger itself: an entry-error void that names no replacement never reaches a commit.
    $other = $this->world->issued($this->project, '100000');
    InvoiceWorld::bill($other, BusinessDate::today(), '2099-12-31');
    expect(fn () => DB::transaction(fn () => DB::table('invoices')->where('id', $other)->update(['state' => 'VOIDED', 'voided_by' => $this->world->actorId, 'voided_at' => now(), 'voided_reason' => 'Salah catat', 'void_basis' => 'ENTRY_ERROR_REPLACED'])))->toThrow(PDOException::class, 'commits only with the replacement');
    expect(DB::table('invoices')->where('id', $other)->value('state'))->toBe('ISSUED');
});
