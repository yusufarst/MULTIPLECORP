<?php

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CompanyScope;
use App\Modules\IAM\Data\CrossCompanyLink;
use App\Modules\IAM\Data\Projection;
use App\Modules\IAM\Data\ReadClass;
use App\Modules\IAM\Data\Sensitivity;
use App\Modules\IAM\Data\TableClassification;
use Tests\Support\DataPaths;

// TL-03 (TASK-012): the class and entitlement resolution of PERMISSIONS_MATRIX §7.1–§7.4 and §8, held to the
// document where it lists them — §7.2's 125 tables, §7.4's read classes, §8's links, §11's data paths.

/**
 * The rows of a numbered table of PERMISSIONS_MATRIX between two headings, by their first cell.
 *
 * @return list<list<string>>
 */
function matrixRows(string $from, string $to, string $firstCell): array
{
    $lines = explode("\n", (string) file_get_contents(base_path('docs/05-security/PERMISSIONS_MATRIX.md')));
    $start = array_search($from, $lines, true);
    $rows = [];

    foreach (array_slice($lines, (int) $start + 1) as $line) {
        if ($line === $to) {
            break;
        }

        if (preg_match("/^\\| {$firstCell} \\|/", $line) === 1) {
            $rows[] = array_map('trim', array_slice(explode('|', $line), 1, -1));
        }
    }

    return $rows;
}

function viewer(array $capabilities, array $companies = [], bool $all = false, int $id = 1, bool $active = true): Authority
{
    return new Authority($id, $active, $capabilities, $all ? CompanyScope::everyCompany() : CompanyScope::of($companies));
}

it('classifies every table of §7.2 as the document does, 125 tables in its counts', function () {
    $document = [];

    foreach (matrixRows('### 7.2 Table classification', '### 7.3 Special projections', '\d+') as [$number, $table, $scope, $class]) {
        $document[trim($table, '`')] = [(int) $number, $scope, $class];
    }

    $single = array_filter(array_keys(TableClassification::TABLES), fn (string $table): bool => count(TableClassification::classesOf($table)) === 1);
    $counts = array_count_values(array_map(fn (string $table): string => TableClassification::classesOf($table)[0]->value, $single));
    ksort($counts);

    expect(TableClassification::TABLES)->toBe($document)
        ->and(TableClassification::TABLES)->toHaveCount(125)
        ->and($counts)->toBe(['S0' => 7, 'S1' => 11, 'S2' => 35, 'S3' => 47, 'S4' => 2])
        ->and(count(array_filter(array_keys(TableClassification::TABLES), fn (string $table): bool => ! TableClassification::followsOwner($table) && count(TableClassification::classesOf($table)) > 1)))->toBe(19)
        ->and(array_values(array_filter(array_keys(TableClassification::TABLES), TableClassification::followsOwner(...))))->toBe(['document_renditions', 'file_objects', 'audit_events'])
        ->and(TableClassification::classesOf('command_log'))->toBe([]);
});

it('lists the read classes of §7.4', function () {
    $ids = array_map(fn (array $row): string => $row[0], matrixRows('### 7.4 Read classes', '## 8. Cross-company link authorization', 'RD-\d+'));

    expect(array_map(fn (ReadClass $class): string => $class->value, ReadClass::cases()))->toBe($ids);
});

it('lists the cross-company links of §8 and the data paths of §11', function () {
    $links = array_map(fn (array $row): string => $row[0], matrixRows('## 8. Cross-company link authorization', '## 9. Owner-only denials', 'XL-\d'));
    $paths = array_map(fn (array $row): string => $row[0], matrixRows('## 11. Data-path coverage matrix', '## 12. Design decisions', 'DP-\d+'));

    expect(array_map(fn (CrossCompanyLink $link): string => $link->value, CrossCompanyLink::cases()))->toBe($links)
        ->and(array_keys(DataPaths::PATHS))->toBe($paths);
});

it('proves each existing data path by its tests and names the Tasks that build the rest (§11)', function () {
    foreach (DataPaths::PATHS as $path => [$tests, $tasks]) {
        expect($tests !== [] || $tasks !== [])->toBeTrue($path);

        foreach ($tests as $test) {
            expect(is_file(base_path($test)))->toBeTrue("{$path}: {$test}");
        }

        foreach ($tasks as $task) {
            expect(is_file(base_path("docs/11-tasks/{$task}.md")))->toBeTrue("{$path}: {$task}");
        }
    }
});

it('admits each audience as §7.2 writes it', function () {
    $admin = viewer(['projects.view', 'stock.view'], [10]);
    $owner = viewer(['audit.view', 'scope.all_companies', 'projects.view'], all: true, id: 2);

    expect(Audience::all()->admits($admin))->toBeTrue()
        ->and(Audience::pool()->admits($admin))->toBeTrue()
        ->and(Audience::family('projects.view', 10)->admits($admin))->toBeTrue()
        ->and(Audience::family('projects.view', 11)->admits($admin))->toBeFalse()
        ->and(Audience::family('projects.view')->admits($admin))->toBeTrue()
        ->and(Audience::family('finance.view')->admits($admin))->toBeFalse()
        ->and(Audience::owner()->admits($admin))->toBeFalse()
        ->and(Audience::owner('audit.view')->admits($owner))->toBeTrue()
        ->and(Audience::family('projects.view', 11)->admits($owner))->toBeTrue()
        ->and(Audience::self(1)->admits($admin))->toBeTrue()
        ->and(Audience::self(1)->admits($owner))->toBeFalse()
        ->and(Audience::none()->admits($owner))->toBeFalse()
        ->and(Audience::anyOf(Audience::none(), Audience::self(2))->admits($owner))->toBeTrue()
        ->and(Audience::all()->admits(viewer(['stock.view'], [10], active: false)))->toBeFalse();
});

it('refuses an audience naming no catalogue capability, or OWN without an OWNER_ONLY one', function (Closure $audience) {
    $audience();
})->with([
    'an unknown family' => [fn () => Audience::family('stock.teleport')],
    'OWN through an Admin capability' => [fn () => Audience::owner('projects.view')],
])->throws(InvalidArgumentException::class);

it('entitles each class as §7.1 states', function () {
    $pool = viewer(['stock.view']);
    $finance = viewer(['finance.view'], [10]);

    expect(Sensitivity::S0->admits($pool))->toBeTrue()
        ->and(Sensitivity::S1->admits($pool))->toBeTrue()
        ->and(Sensitivity::S1->admits($finance))->toBeFalse()
        ->and(Sensitivity::S3->admits($finance, 'finance.view', 10))->toBeTrue()
        ->and(Sensitivity::S3->admits($finance, 'cost.view', 10))->toBeFalse()
        ->and(Sensitivity::S3->admits($finance, 'finance.view', 11))->toBeFalse()
        ->and(Sensitivity::S2->admits($finance))->toBeFalse()
        // A family field without a named company still needs a company in scope, stock.view's included (CS-05).
        ->and(Sensitivity::S3->admits($pool, 'stock.view'))->toBeFalse()
        ->and(Sensitivity::S3->admits(viewer(['stock.view'], [10]), 'stock.view'))->toBeTrue()
        ->and(Projection::admitsDerived($pool, ['stock.view']))->toBeFalse();
});

it('opens each read class by its capabilities, a company-scoped one within scope only', function () {
    $admin = viewer(['stock.view', 'finance.view'], [10]);

    expect(ReadClass::MasterLookup->admits(viewer([])))->toBeTrue()
        ->and(ReadClass::PooledPhysical->admits($admin, 11))->toBeTrue()
        ->and(ReadClass::SalesReceivablesCash->admits($admin, 10))->toBeTrue()
        ->and(ReadClass::SalesReceivablesCash->admits($admin, 11))->toBeFalse()
        ->and(ReadClass::ProcurementCost->admits($admin, 10))->toBeFalse()
        ->and(ReadClass::Consolidated->admits($admin))->toBeFalse()
        ->and(ReadClass::AuditSecurity->admits(viewer(['security_log.view', 'scope.all_companies'], all: true)))->toBeTrue();
});

it('authorizes each cross-company link as §8 states', function () {
    $admin = viewer(['stock.view', 'stock.allocate_intercompany', 'projects.view', 'projects.manage', 'finance.view', 'cashin.record'], [10, 20]);
    $one = viewer(['stock.view', 'stock.allocate_intercompany', 'projects.view', 'projects.manage', 'finance.view', 'cashin.record'], [10]);

    expect(CrossCompanyLink::mayAllocate($one, 10))->toBeTrue()
        ->and(CrossCompanyLink::mayAllocate($one, 20))->toBeFalse()
        ->and(CrossCompanyLink::maySetCaseSide($one, 20))->toBeFalse()
        ->and(CrossCompanyLink::mayLinkProjects($admin, 10, 20))->toBeTrue()
        ->and(CrossCompanyLink::mayLinkProjects($one, 10, 20))->toBeFalse()
        ->and(CrossCompanyLink::mayCiteTransfer($admin, 'cashin.record', 10, 20))->toBeTrue()
        ->and(CrossCompanyLink::mayCiteTransfer($one, 'cashin.record', 10, 20))->toBeFalse()
        ->and(CrossCompanyLink::mayCiteTransfer($admin, 'finance.view', 10, 20))->toBeFalse();
});

it('masks another company and each allocation side outside scope (PJ-04, PJ-05, PJ-06)', function () {
    $bearer = viewer(['cost.view'], [20]);
    $source = viewer(['cost.view'], [10]);
    $both = viewer(['cost.view'], [10, 20]);
    $allocation = fn (Authority $viewer) => Projection::allocation($viewer, 10, 20, ['quantity' => '2.000', 'cost' => '150000.00'], ['company' => 'CV A', 'purchase' => 'PO-1'], ['company' => 'CV B'], ['number' => 'PRJ-9']);

    expect(Projection::company($bearer, 10, fn () => throw new LogicException('built outside scope')))->toBe(Projection::ANOTHER_COMPANY)
        ->and(Projection::company($source, 10, fn () => ['name' => 'CV A']))->toBe(['name' => 'CV A'])
        ->and($allocation($bearer))->toBe(['quantity' => '2.000', 'cost' => '150000.00', 'source' => Projection::ANOTHER_COMPANY, 'bearer' => ['company' => 'CV B']])
        ->and($allocation(viewer(['cost.view', 'projects.view'], [20]))['bearer'])->toBe(['company' => 'CV B', 'project' => ['number' => 'PRJ-9']])
        ->and($allocation(viewer(['cost.view'], [10, 20]))['bearer'])->toBe(['company' => 'CV B'])
        ->and($allocation($source))->toBe(['quantity' => '2.000', 'cost' => '150000.00', 'source' => ['company' => 'CV A', 'purchase' => 'PO-1'], 'bearer' => Projection::ANOTHER_COMPANY])
        ->and($allocation($both)['source'])->toBe(['company' => 'CV A', 'purchase' => 'PO-1'])
        ->and($allocation(viewer(['finance.view'], [10, 20])))->toBeNull();
});

it('shows a derived figure only to a viewer of every input family (PJ-22)', function () {
    expect(Projection::admitsDerived(viewer(['finance.view', 'cost.view', 'profit.view'], [10]), ['finance.view', 'cost.view'], 10))->toBeTrue()
        ->and(Projection::admitsDerived(viewer(['stock.view', 'finance.view'], [10]), ['stock.view', 'cost.view'], 10))->toBeFalse();
});
