<?php

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\FIN\Models\Invoice;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use App\Modules\Reporting\Queries\Search;
use App\Support\Search\NameSearch;
use App\Support\Search\Term;
use Database\Factories\DOC\DocumentTypeFactory;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-016; acceptance criteria 1–3): ROUTE-281's query classes over seeded rows in and out of scope —
// a code exactly first, several rows sharing a key listed and none chosen, names equal to or beginning with the
// term before similar ones, a term without a trigram by prefix only, the 200-candidate cut, and a record
// outside the viewer's scope or capability changing no row, cut or notice (PF-19–PF-23; CS-07; PJ-10; DP-06).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    [$this->a, $this->b] = [Company::factory()->create(['code' => 'ARJ']), Company::factory()->create(['code' => 'BTN'])];
    $this->search = app(Search::class);
    $this->run = fn ($user, string $term, array $types = []) => $this->search->run(app(EffectiveCapabilities::class)->fresh($user->id), new Term($term), $types);
});

it('looks a barcode or an SKU up exactly first, one hit for one product (PF-19; criterion 1)', function () {
    $paper = Product::factory()->create(['sku' => 'KRT-A4-80', 'name' => 'Kertas A4 80 gsm']);
    ProductBarcode::factory()->create(['product_id' => $paper->id, 'code' => '8991 2345 6789 0']);
    Product::factory()->create(['sku' => 'KRT-F4', 'name' => 'Kertas F4 80 gsm KRT-A4-80']);
    $viewer = Authorization::admin([$this->a->id]);

    expect(($this->run)($viewer, 'krt-a4-80')['exact'])->toBe([['type' => 'product', 'id' => $paper->public_id, 'code' => 'KRT-A4-80', 'label' => 'Kertas A4 80 gsm']])
        ->and(array_column(($this->run)($viewer, '8991234567890')['exact'], 'id'))->toBe([$paper->public_id])
        ->and(($this->run)($viewer, '8991234567890')['groups'])->toBe([]);
});

it('lists every project sharing a number within scope and chooses none; another company\'s changes nothing (PF-19, PF-22; criteria 1, 3)', function () {
    $mine = Project::factory()->create(['company_id' => $this->a->id, 'project_number' => 'PRJ/2026/0007']);
    $other = Project::factory()->create(['company_id' => $this->b->id, 'project_number' => 'PRJ/2026/0007']);
    $both = Authorization::admin([$this->a->id, $this->b->id], ['projects.view']);
    $one = Authorization::admin([$this->a->id], ['projects.view']);
    $none = Authorization::admin([$this->a->id]);

    expect(array_column(($this->run)($both, 'prj/2026/0007')['exact'], 'id'))->toBe([$mine->public_id, $other->public_id])
        ->and(array_column(($this->run)($both, 'PRJ/2026/0007')['exact'], 'company'))->toBe(['ARJ', 'BTN'])
        ->and(array_column(($this->run)($one, 'PRJ/2026/0007')['exact'], 'id'))->toBe([$mine->public_id])
        ->and(($this->run)($none, 'PRJ/2026/0007'))->toBe(['exact' => [], 'groups' => [], 'notice' => 'unknown_code']);
});

it('answers an out-of-scope project like a missing one: the same rows, cut and notice (DP-06; criterion 3)', function () {
    Project::factory()->create(['company_id' => $this->a->id, 'title' => 'Pengadaan meja SMAN 1 Bantul']);
    $viewer = Authorization::admin([$this->a->id], ['projects.view']);
    $before = ($this->run)($viewer, 'SMAN 1');

    Project::factory()->count(3)->create(['company_id' => $this->b->id, 'title' => 'Pengadaan kursi SMAN 1 Sleman']);
    Project::factory()->create(['company_id' => $this->b->id, 'project_number' => 'BTN/PRJ/2026/0001']);

    expect(($this->run)($viewer, 'SMAN 1'))->toBe($before)
        ->and(($this->run)($viewer, 'BTN/PRJ/2026/0001'))->toBe(['exact' => [], 'groups' => [], 'notice' => 'unknown_code']);
});

it('puts names equal to or beginning with the term before similar ones, at most twenty (PF-21; criterion 2)', function () {
    ClientOrganization::factory()->create(['name' => 'Yayasan SMAN Harapan']);
    ClientOrganization::factory()->create(['name' => 'Harapan Bangsa']);
    $prefix = ClientOrganization::factory()->create(['name' => 'SMAN Harapan Jaya']);
    $equal = ClientOrganization::factory()->create(['name' => 'SMAN Harapan']);
    ClientOrganization::factory()->count(25)->sequence(fn ($row) => ['name' => 'Dinas SMAN Harapan '.$row->index])->create();
    $viewer = Authorization::admin([$this->a->id]);

    $rows = ($this->run)($viewer, 'sman harapan', ['client'])['groups'][0]['rows'];

    expect(array_column(array_slice($rows, 0, 2), 'id'))->toBe([$equal->public_id, $prefix->public_id])
        ->and(count($rows))->toBe(Search::ROWS)
        ->and(array_filter(array_column($rows, 'label'), fn (string $label): bool => ! str_contains($label, 'SMAN Harapan')))->toBe([]);
});

it('answers a term without a trigram by prefix only, with MSG-24 (PF-20, PF-23; criterion 2)', function () {
    $prefix = Supplier::factory()->create(['name' => 'CV Mitra']);
    Supplier::factory()->create(['name' => 'PT Aneka CV Mitra']);
    $viewer = Authorization::admin([$this->a->id]);

    $answer = ($this->run)($viewer, 'cv', ['supplier']);

    expect($answer['notice'])->toBe('short')
        ->and(array_column($answer['groups'][0]['rows'], 'id'))->toBe([$prefix->public_id]);
});

it('cuts a name search past 200 candidates and says so with MSG-23 (PF-21; HO-10; criterion 2)', function () {
    Supplier::factory()->count(NameSearch::CANDIDATES + 1)->sequence(fn ($row) => ['name' => 'Toko Sinar '.$row->index])->create();
    $viewer = Authorization::admin([$this->a->id]);

    $answer = ($this->run)($viewer, 'sinar', ['supplier']);

    expect($answer['notice'])->toBe('cut')
        ->and($answer['groups'][0]['rows'])->toHaveCount(Search::ROWS);
});

it('takes LIKE wildcards in the term literally (WS-03)', function () {
    Supplier::factory()->create(['name' => 'A_B Teknik']);
    Supplier::factory()->create(['name' => 'AXB Teknik']);
    Supplier::factory()->create(['name' => '50% Diskon']);
    Supplier::factory()->create(['name' => '500 Diskon']);
    $viewer = Authorization::admin([$this->a->id]);

    expect(array_column(($this->run)($viewer, 'a_b', ['supplier'])['groups'][0]['rows'], 'label'))->toBe(['A_B Teknik'])
        ->and(array_column(($this->run)($viewer, '50%', ['supplier'])['groups'][0]['rows'], 'label'))->toBe(['50% Diskon']);
});

it('shows a client unit and a PIC only to projects.view with a company in scope (CS-06; PJ-10; criterion 3)', function () {
    $client = ClientOrganization::factory()->create(['name' => 'Dinas Pendidikan']);
    $unit = ClientUnit::factory()->create(['client_organization_id' => $client->id, 'name' => 'Bidang Sarana Prasarana']);
    ClientContact::factory()->create(['client_unit_id' => $unit->id, 'name' => 'Rahmat Hidayat']);
    $allowed = Authorization::admin([$this->a->id], ['projects.view']);
    $denied = Authorization::admin([$this->a->id]);

    expect(($this->run)($allowed, 'bidang sarana', ['unit'])['groups'][0]['rows'][0])->toBe(['type' => 'unit', 'id' => $client->public_id, 'code' => null, 'label' => 'Bidang Sarana Prasarana', 'detail' => 'Dinas Pendidikan'])
        ->and(($this->run)($allowed, 'rahmat', ['pic'])['groups'][0]['rows'][0]['detail'])->toBe('Bidang Sarana Prasarana')
        ->and(($this->run)($denied, 'bidang sarana', ['unit', 'pic'])['groups'])->toBe([]);
});

it('finds an invoice by its formatted number to finance.view within scope only (PF-19; DP-06; criterion 3)', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->a->id]);
    $document = Document::factory()->create(['company_id' => $this->a->id, 'document_type_code' => DocumentTypeFactory::ensure('DOC-06'), 'project_id' => null, 'invoice_id' => $invoice->id]);
    DocumentVersion::factory()->issued()->create(['document_id' => $document->id, 'formatted_number' => 'ARJ/INV/2026/0042']);
    $finance = Authorization::admin([$this->a->id], ['finance.view']);
    $elsewhere = Authorization::admin([$this->b->id], ['finance.view', 'projects.view']);
    $projects = Authorization::admin([$this->a->id], ['projects.view']);
    $both = Authorization::admin([$this->a->id], ['projects.view', 'finance.view']);

    expect(($this->run)($finance, 'arj/inv/2026/0042')['exact'])->toBe([['type' => 'invoice', 'id' => $invoice->public_id, 'code' => 'ARJ/INV/2026/0042', 'label' => 'ARJ/INV/2026/0042', 'company' => 'ARJ']])
        ->and(($this->run)($elsewhere, 'ARJ/INV/2026/0042')['exact'])->toBe([])
        // A document's number is metadata, `projects.view`'s (PJ-15): the invoice's document, opened as a document.
        ->and(array_column(($this->run)($projects, 'ARJ/INV/2026/0042')['exact'], 'type'))->toBe(['document'])
        // To a viewer who sees it as an invoice, it is shown once, as the invoice.
        ->and(array_column(($this->run)($both, 'ARJ/INV/2026/0042')['exact'], 'type'))->toBe(['invoice']);
});

it('finds any document by its formatted number to projects.view within its company only (PF-19; PJ-15; DP-06; criteria 1, 3)', function () {
    $ours = Document::factory()->create(['company_id' => $this->a->id]);
    DocumentVersion::factory()->issued()->create(['document_id' => $ours->id, 'formatted_number' => 'BAST/2026/0007']);
    $theirs = Document::factory()->create(['company_id' => $this->b->id]);
    DocumentVersion::factory()->issued()->create(['document_id' => $theirs->id, 'formatted_number' => 'BAST/2026/0007']);
    $admin = Authorization::admin([$this->a->id], ['projects.view']);
    $none = Authorization::admin([$this->a->id]);

    $found = ($this->run)($admin, 'bast/2026/0007');

    expect(array_map(fn (array $row): array => [$row['type'], $row['id'], $row['company']], $found['exact']))->toBe([['document', $ours->public_id, 'ARJ']])
        ->and(array_column(($this->run)(Authorization::owner(), 'BAST/2026/0007')['exact'], 'company'))->toBe(['ARJ', 'BTN'])
        ->and(($this->run)($none, 'BAST/2026/0007')['exact'])->toBe([])
        ->and(($this->run)($none, 'BAST/2026/0007')['notice'])->toBe('unknown_code');
});

it('lists at most twenty rows sharing a code and says the list is cut (PF-19; MSG-23; criterion 2)', function () {
    SerialUnit::factory()->count(Search::ROWS + 1)->create(['serial_number' => 'SN-CUT', 'serial_key' => 'sn-cut']);
    $stock = Authorization::admin([$this->a->id], ['stock.view']);

    $found = ($this->run)($stock, 'SN-CUT', ['serial']);

    expect($found['exact'])->toHaveCount(Search::ROWS)
        ->and($found['notice'])->toBe('cut');
});

it('finds nothing for an inactive account, S0 names included (ROUTE-281: every active account)', function () {
    Product::factory()->create(['sku' => 'KRT-A4', 'name' => 'Kertas A4']);
    $admin = Authorization::admin([$this->a->id], ['projects.view']);
    $admin->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    expect(($this->run)($admin, 'KRT-A4'))->toBe(['exact' => [], 'groups' => [], 'notice' => null])
        ->and(($this->run)($admin, 'Kertas'))->toBe(['exact' => [], 'groups' => [], 'notice' => null]);
});

it('finds a serial exactly to stock.view, listing the products that share it (PF-19; PJ-03; criterion 1)', function () {
    $first = SerialUnit::factory()->create(['serial_number' => 'SN 0001', 'serial_key' => 'sn0001']);
    $second = SerialUnit::factory()->create(['serial_number' => 'SN0001', 'serial_key' => 'sn0001']);
    $stock = Authorization::admin([$this->a->id], ['stock.view']);
    $none = Authorization::admin([$this->a->id]);

    expect(array_column(($this->run)($stock, 'SN 0001')['exact'], 'code'))->toEqualCanonicalizing(['SN 0001', 'SN0001'])
        ->and(array_column(($this->run)($stock, 'sn0001')['exact'], 'type'))->toBe(['serial', 'serial'])
        ->and(($this->run)($none, 'SN0001')['exact'])->toBe([])
        ->and([$first->product_id, $second->product_id])->not->toContain(null);
});
