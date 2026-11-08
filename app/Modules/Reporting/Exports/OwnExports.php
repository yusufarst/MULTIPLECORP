<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\IAM\Queries\AccountAuthority;
use App\Support\Search\Row;
use App\Support\Status\StatusRead;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-053 *Ekspor Saya*'s read and ROUTE-285's export status (H6-04; DP-07; DIR-088; IP-12; UXS-23; UXS-36): a request is
 * found by its UUIDv7 only within its requester's own requests, so another account's identifier answers as not found
 * and reveals nothing (AZ-11). Its shown state is IP-12's — *Diminta*, *Diproses*, *Siap Diunduh*, *Gagal* and *Tidak
 * Tersedia* —, a READY file shown ready only while unexpired and still covered by the requester's current authority
 * (RV-08), otherwise *Tidak Tersedia* with no download. Newest first by keyset, its tab and cursor in the address.
 */
final class OwnExports implements StatusRead
{
    public const PAGE = 30;

    public const TABS = ['semua', 'berjalan', 'siap', 'gagal'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const COLUMNS = ['id', 'report_code', 'filters', 'company_ids', 'capability_codes', 'masking_context', 'state', 'claim_count', 'file_object_id', 'expires_at', 'recorded_at'];

    public function __construct(private readonly AccountAuthority $capabilities) {}

    public function kind(): string
    {
        return 'ekspor';
    }

    /** The requester's own request by its identifier, or nothing. */
    public function find(int $userId, string $id): ?object
    {
        return preg_match(self::UUID, $id) === 1 ? DB::table('export_requests')->where('requested_by', $userId)->where('id', strtolower($id))->first(self::COLUMNS) : null;
    }

    public function read(Authenticatable $viewer, string $id): array
    {
        $userId = $viewer->getAuthIdentifier();
        $userId = is_numeric($userId) ? (int) $userId : 0;
        $request = $this->capabilities->for($userId)->has('reports.export') ? $this->find($userId, $id) : null;

        if ($request === null) {
            throw new NotFoundHttpException;
        }

        $state = $this->shown($userId, $request);

        return [
            'state' => $state,
            'attempt' => (int) Row::text($request, 'claim_count'),
            'attempts' => GenerateExport::MAXIMUM_CLAIMS,
            'exhausted' => in_array($state, ['FAILED', 'WITHHELD'], true),
            'file' => $state === 'READY' ? self::href(Row::text($request, 'id')) : null,
        ];
    }

    /**
     * One page of the requester's requests, newest first.
     *
     * @return array{rows: list<array<string, mixed>>, more: ?string, tab: string}
     */
    public function page(int $userId, ?string $tab, ?string $after): array
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
        $cursor = is_string($after) && preg_match(self::UUID, $after) === 1 ? strtolower($after) : null;
        $found = DB::table('export_requests')->where('requested_by', $userId)
            ->when($tab === 'berjalan', fn (Builder $where) => $where->whereIn('state', ['PENDING', 'RUNNING']))
            ->when($tab === 'siap', fn (Builder $where) => $where->where('state', 'READY'))
            ->when($tab === 'gagal', fn (Builder $where) => $where->whereIn('state', ['FAILED', 'WITHHELD']))
            ->when($cursor !== null, fn (Builder $where) => $where->where('id', '<', $cursor))
            ->orderByDesc('id')->limit(self::PAGE + 1)->get(self::COLUMNS)->all();
        $page = array_slice($found, 0, self::PAGE);
        $codes = self::codes($page);
        $rows = [];

        foreach ($page as $request) {
            $filters = json_decode(Row::text($request, 'filters'), true);
            $filters = is_array($filters) ? $filters : [];
            $state = $this->shown($userId, $request);
            $rows[] = [
                'id' => Row::text($request, 'id'),
                'report' => Row::text($request, 'report_code'),
                'format' => is_string($filters['bentuk'] ?? null) ? $filters['bentuk'] : 'xlsx',
                'from' => is_string($filters['dari'] ?? null) ? $filters['dari'] : null,
                'to' => is_string($filters['sampai'] ?? null) ? $filters['sampai'] : null,
                'companies' => is_string($filters['perusahaan'] ?? null) ? [$filters['perusahaan']] : array_values(array_filter(array_map(fn (string $id): ?string => $codes[(int) $id] ?? null, self::array($request->company_ids ?? null)))),
                'state' => $state,
                'requested_at' => CarbonImmutable::parse(Row::text($request, 'recorded_at'))->toIso8601ZuluString(),
                'expires_at' => $state === 'READY' && Row::nullable($request, 'expires_at') !== null ? CarbonImmutable::parse(Row::text($request, 'expires_at'))->toIso8601ZuluString() : null,
                'href' => $state === 'READY' ? self::href(Row::text($request, 'id')) : null,
                'again' => array_filter(['laporan' => Row::text($request, 'report_code'), ...array_intersect_key($filters, array_flip(['dari', 'sampai', 'perusahaan', 'bentuk'])), 'periode' => 'rentang'], is_string(...)),
            ];
        }

        $last = $page === [] ? null : $page[count($page) - 1];

        return ['rows' => $rows, 'more' => count($found) > self::PAGE && $last !== null ? Row::text($last, 'id') : null, 'tab' => $tab];
    }

    /**
     * The state IP-12 shows: READY only while unexpired and covered by the requester's authority now (RV-08).
     */
    public function shown(int $userId, object $request): string
    {
        $state = Row::text($request, 'state');

        if ($state !== 'READY') {
            return $state;
        }

        $expires = Row::nullable($request, 'expires_at');
        $current = $this->capabilities->for($userId);
        $covered = $current->has('reports.export') && ExportContext::stored($request->company_ids ?? null, $request->capability_codes ?? null, $request->masking_context ?? null)->coveredBy($current);

        return $covered && Row::nullable($request, 'file_object_id') !== null && ($expires === null || CarbonImmutable::parse($expires)->isFuture()) ? 'READY' : 'WITHHELD';
    }

    public static function href(string $id): string
    {
        return '/laporan/ekspor/'.$id.'/unduh';
    }

    /**
     * The codes of the companies the page's requests recorded.
     *
     * @param  list<object>  $requests
     * @return array<int, string>
     */
    private static function codes(array $requests): array
    {
        $ids = [];

        foreach ($requests as $request) {
            foreach (self::array($request->company_ids ?? null) as $id) {
                $ids[(int) $id] = (int) $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $codes = [];

        foreach (DB::table('companies')->whereIn('id', array_values($ids))->get(['id', 'code']) as $company) {
            $codes[(int) Row::text($company, 'id')] = Row::text($company, 'code');
        }

        return $codes;
    }

    /** @return list<string> */
    private static function array(mixed $value): array
    {
        if ($value !== null && ! is_string($value)) {
            throw new LogicException('An array column reads as text.');
        }

        return $value === null || trim($value, '{}') === '' ? [] : array_map(fn (string $item): string => trim($item, '"'), explode(',', trim($value, '{}')));
    }
}
