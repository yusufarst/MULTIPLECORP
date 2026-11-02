<?php

namespace Tests\Support;

use App\Modules\FUL\Actions\CloseDelivery;
use App\Modules\FUL\Actions\CorrectDelivery;
use App\Modules\FUL\Actions\RecordDelivery;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-045's world (TL-04–TL-08): ROUTE-201's, ROUTE-203's and ROUTE-204's actions as their own commands, each answering its word, over a shipment DispatchWorld dispatched to a project line.
 */
final class DeliveryWorld
{
    public const CAPABILITIES = [...DispatchWorld::CAPABILITIES, 'delivery.record', 'delivery.close', 'fulfillment.correct', 'stock.adjust', 'documents.issue', 'evidence.view'];

    /** The project of a project line as the commands name it. */
    public static function project(int $line): ProjectRef
    {
        $project = DB::table('project_lines as l')->join('projects as p', 'p.id', '=', 'l.project_id')->where('l.id', $line)->first(['p.id', 'p.company_id', 'p.public_id']);

        return new ProjectRef((int) ($project->id ?? 0), (int) ($project->company_id ?? 0), (string) ($project->public_id ?? ''));
    }

    /** A delivery proof of the company (L-19). */
    public static function proof(Company $company): int
    {
        return DispatchWorld::evidence($company, ['evidence_type' => 'DELIVERY_PROOF']);
    }

    /**
     * ROUTE-201's action: its word.
     *
     * @param  list<array{0: int, 1: string}>  $lines  each shipment line with its quantity
     * @param  list<string>  $acknowledged
     * @param  array{inspector: string, result: string}|null  $inspection
     */
    public static function record(int $actor, int $line, array $lines, int $evidence, ?string $commandId = null, array $acknowledged = [], ?string $reason = null, ?string $discrepancy = null, ?array $inspection = null, string $recipient = 'Bu Sari'): string
    {
        $entered = array_map(fn (array $one): array => ['shipment' => $one[0], 'line' => $line, 'quantity' => $one[1]], $lines);
        $command = $commandId ?? (string) Str::uuid();

        return InvoiceWorld::word(app(RecordDelivery::class)->run($command, $actor, self::project($line), BusinessDate::today(), false, $recipient, $discrepancy, $discrepancy, $inspection, $entered, $evidence, $acknowledged, $reason,
            ['lines' => $lines, 'evidence' => $evidence, 'acknowledged' => $acknowledged, 'recipient' => $recipient, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /**
     * ROUTE-203's action: its word.
     *
     * @param  list<int>  $serials
     * @param  list<string>  $acknowledged
     */
    public static function close(int $actor, int $line, int $shipment, string $kind, string $qty, array $serials = [], ?int $supersedes = null, ?string $commandId = null, array $acknowledged = [], ?string $reason = null): string
    {
        return InvoiceWorld::word(app(CloseDelivery::class)->run($commandId ?? (string) Str::uuid(), $actor, self::project($line), $shipment, $kind, $qty, $serials, $supersedes, 'Klien menolak barang', BusinessDate::today(), false,
            ['kind' => $kind, 'qty' => $qty, 'serials' => $serials, 'supersedes' => $supersedes, 'acknowledged' => $acknowledged, 'run' => $commandId ?? (string) Str::uuid()], $acknowledged, $reason));
    }

    /** The newest closure's handle, as the likely-duplicate warning names it. */
    public static function closureHandle(): string
    {
        return RowHandle::of('delivery_closures', (int) DB::table('delivery_closures')->max('id'));
    }

    /** ROUTE-204's action on the delivery's line: its word. */
    public static function correct(int $actor, int $line, int $delivery, int $deliveryLine, ?string $qty, ?string $commandId = null): string
    {
        return InvoiceWorld::word(app(CorrectDelivery::class)->run($commandId ?? (string) Str::uuid(), $actor, self::project($line), $delivery, $deliveryLine, $qty, 'Jumlah salah ketik', BusinessDate::today(), false,
            ['qty' => $qty, 'run' => $commandId ?? (string) Str::uuid()]));
    }

    /** The project line's net delivered. */
    public static function delivered(int $line): string
    {
        return (string) DB::table('project_line_balances')->where('project_line_id', $line)->value('delivered_net_qty_base');
    }

    /** The newest delivery and its first line. */
    public static function lastDelivery(): object
    {
        return DB::table('deliveries as d')->join('delivery_lines as l', 'l.delivery_id', '=', 'd.id')->orderByDesc('d.id')->orderBy('l.id')->first(['d.id as delivery', 'd.public_id', 'l.id as line']) ?? (object) [];
    }
}
