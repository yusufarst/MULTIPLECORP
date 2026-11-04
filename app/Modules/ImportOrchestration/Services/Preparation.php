<?php

namespace App\Modules\ImportOrchestration\Services;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Kinds\ImportKind;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Kinds\SeedRows;
use Illuminate\Support\Facades\DB;

/**
 * A source's rows as a batch's import rows (WF-MIG-01; IMP-04; C-47; DP-18): each row read by its kind into its
 * normalized payload with its import identity — consecutive rows sharing an identity one record for a grouped kind —,
 * the seed rows apart; malformed rows INVALID with every reason, a repeated identity DUPLICATE, a row of a company
 * outside the batch INVALID; a committed twin in the preparer's scope DUPLICATE, one outside it INVALID with MSG-18's
 * reason alone and listed for the Owner (UXS-19). Nothing here writes; nothing runs inside a transaction.
 */
final class Preparation
{
    public const UNDISCLOSED = 'undisclosed';

    public function __construct(private readonly Kinds $kinds) {}

    /**
     * @param  list<array{number: int, cells: array<string, string>}>  $table
     * @param  list<int>  $scope  the batch's companies
     * @param  array<string, int>  $codes  every company's code => id
     * @param  callable(int|null): bool  $visible  whether a committed twin's company lies in the preparer's scope
     * @return array{rows: list<array{row_number: int, import_kind: string, import_identity: string, company_id: int|null, normalized_payload: array<string, mixed>, validation_state: string, errors: list<array<string, mixed>>}>, hidden: list<array{identity: string, company: int|null, batch: string}>}
     */
    public function rows(ImportKind $kind, array $table, array $scope, array $codes, callable $visible): array
    {
        $records = [];
        $previous = null;

        foreach ($table as $line) {
            $cells = new Cells($line['cells']);
            $seed = SeedRows::isSeed($cells);
            $identity = $seed ? null : ($line['cells']['id'] ?? '');
            $key = $seed ? null : "{$identity}";

            if ($kind->grouped() && ! $seed && $previous !== null && $key !== '' && $records[$previous]['key'] === $key) {
                $records[$previous]['lines'][] = $cells;

                continue;
            }

            $records[] = ['number' => $line['number'], 'seed' => $seed, 'key' => $key, 'head' => $cells, 'lines' => [$cells]];
            $previous = $seed ? null : array_key_last($records);
        }

        $rows = [];
        $hidden = [];
        $seen = [];

        foreach ($records as $record) {
            $head = $record['head'];
            $head->checkShape();
            $handler = $record['seed'] ? $this->kinds->of(SeedRows::PREFIX) : $kind;
            $company = $this->company($head, $handler, $scope, $codes);

            foreach ($record['lines'] as $line) {
                if ($line !== $head) {
                    $line->checkShape();
                }
            }

            $payload = $handler->normalize($head, $record['lines']);

            if ($record['seed']) {
                $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
                $importKind = SeedRows::PREFIX.$type;
                $identity = ($company ?? 0).'|'.(is_string($payload['period'] ?? null) ? $payload['period'] : '');
            } else {
                $importKind = $kind->code();
                $identity = trim((string) $record['key']);

                if ($identity === '') {
                    $head->fail('id', 'required');
                } elseif (mb_strlen($identity) > 100) {
                    $head->fail('id', 'too_long');
                }
            }

            $state = $head->errors === [] ? 'VALID' : 'INVALID';
            $errors = $head->errors;
            $seenKey = "{$importKind}\u{0}{$identity}";

            if ($state === 'VALID' && isset($seen[$seenKey])) {
                [$state, $errors] = ['DUPLICATE', [['field' => 'id', 'reason' => 'repeat', 'row' => $seen[$seenKey]]]];
            }

            if ($state === 'VALID') {
                $seen[$seenKey] = $record['number'];
                $twin = DB::table('import_rows as r')->join('import_batches as b', 'b.id', '=', 'r.import_batch_id')->where('r.import_kind', $importKind)->where('r.import_identity', $identity)
                    ->where('r.validation_state', 'COMMITTED')->first(['r.company_id', 'b.public_id']);

                if ($twin !== null) {
                    $twinCompany = is_numeric($twin->company_id) ? (int) $twin->company_id : null;

                    if ($visible($twinCompany)) {
                        [$state, $errors] = ['DUPLICATE', [['field' => 'id', 'reason' => 'committed']]];
                    } else {
                        // UXS-19: a twin outside scope is INVALID with MSG-18 alone, never DUPLICATE.
                        [$state, $errors] = ['INVALID', [['field' => '', 'reason' => self::UNDISCLOSED]]];
                        $hidden[] = ['identity' => $identity, 'company' => $twinCompany, 'batch' => is_string($twin->public_id) ? $twin->public_id : ''];
                    }
                }
            }

            $rows[] = [
                'row_number' => $record['number'], 'import_kind' => $importKind, 'import_identity' => mb_substr($identity, 0, 200), 'company_id' => $company,
                'normalized_payload' => $payload, 'validation_state' => $state, 'errors' => $errors,
            ];
        }

        return ['rows' => $rows, 'hidden' => $hidden];
    }

    /**
     * The row's typed company, inside the batch's scope (CS-03; DP-18); a kind without one, and a row naming none, has none.
     *
     * @param  list<int>  $scope
     * @param  array<string, int>  $codes
     */
    private function company(Cells $head, ImportKind $kind, array $scope, array $codes): ?int
    {
        $code = $head->raw('perusahaan');

        if (! $kind->companyBound()) {
            return null;
        }

        if ($code === null) {
            return $head->fail('perusahaan', 'required');
        }

        $company = $codes[mb_strtolower($code)] ?? null;

        if ($company === null) {
            return $head->fail('perusahaan', 'reference');
        }

        return in_array($company, $scope, true) ? $company : $head->fail('perusahaan', 'company_scope');
    }
}
