<?php

namespace Tests\Support;

use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Models\User;
use App\Modules\ImportOrchestration\Actions\AbortImport;
use App\Modules\ImportOrchestration\Actions\CommitImport;
use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Actions\PrepareImport;
use App\Modules\ImportOrchestration\Actions\SignOffImport;
use App\Modules\ImportOrchestration\Jobs\CommitImportRows;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-057's world (FX-09; TL-16): a company whose PROJECT and PURCHASE schemes cover the cutoff — the last day of the
 * previous year — and go-live recorded the day after, so the seed rows each batch carries seed the cutoff's year; the
 * Owner and an Admin preparing in the company; synthetic sources stored as the preparer's private import-source uploads;
 * and each batch command as its own call, answering its word. Synthetic data only.
 */
final class ImportWorld
{
    public const HEADER = ['id', 'perusahaan', 'nomor_jenis', 'nomor_periode', 'nomor_terakhir'];

    public readonly string $cutoff;

    public readonly string $year;

    private function __construct(public readonly Company $company, public readonly User $owner, public readonly User $admin)
    {
        $this->year = (string) ((int) CarbonImmutable::now()->format('Y') - 1);
        $this->cutoff = "{$this->year}-12-31";
    }

    public static function make(?Company $company = null): self
    {
        Authorization::catalogue();
        $company ??= Company::factory()->create();
        $year = (string) ((int) CarbonImmutable::now()->format('Y') - 1);
        Numbering::goLive(((int) $year + 1).'-01-01');

        Numbering::scheme($company, SequenceType::Project, "{$year}-01-01", 'YEAR', 'PRJ/{tahun}/{nomor:4}');
        Numbering::scheme($company, SequenceType::Purchase, "{$year}-01-01", 'YEAR', 'PO/{tahun}/{nomor:4}');
        $owner = User::query()->findOrFail(Authorization::owner()->id);
        $admin = User::query()->findOrFail(Authorization::admin([$company->id], ['import.prepare', 'projects.view', 'stock.view', 'finance.view', 'cost.view', 'evidence.upload', 'evidence.view'])->id);

        return new self($company, $owner, $admin);
    }

    /** The seed rows a batch of the company carries: PROJECT and PURCHASE for the cutoff's year, after the last legacy number `$last` — the same in every batch (HO-32). */
    public function seeds(int $last = 20): array
    {
        return [
            ['id' => '', 'perusahaan' => $this->company->code, 'nomor_jenis' => 'PROJECT', 'nomor_periode' => $this->year, 'nomor_terakhir' => (string) $last],
            ['id' => '', 'perusahaan' => $this->company->code, 'nomor_jenis' => 'PURCHASE', 'nomor_periode' => $this->year, 'nomor_terakhir' => (string) $last],
        ];
    }

    /**
     * A CSV of the rows, the seed rows appended, stored as the uploader's import source.
     *
     * @param  list<array<string, string>>  $rows
     */
    public function source(array $rows, ?int $uploader = null, bool $seeds = true): FileObject
    {
        $all = [...$rows, ...($seeds ? $this->seeds() : [])];
        $columns = array_values(array_unique([...array_merge(...array_map('array_keys', $all)), ...self::HEADER]));
        $lines = [implode(',', $columns)];

        foreach ($all as $row) {
            $lines[] = implode(',', array_map(fn (string $column): string => self::cell($row[$column] ?? ''), $columns));
        }

        return self::store(implode("\n", $lines)."\n", 'sumber.csv', $uploader ?? $this->admin->id);
    }

    /** Raw bytes stored as an import source of the uploader. */
    public static function store(string $bytes, string $name, int $uploader): FileObject
    {
        $key = 'impor/'.Str::uuid7();
        app(FileStore::class)->write($key, $bytes);

        return FileObject::factory()->create([
            'storage_disk' => app(FileStore::class)->diskName(), 'storage_key' => $key, 'original_filename' => $name, 'mime_type' => str_ends_with($name, '.xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'company_id' => null, 'sensitivity' => 'S4', 'purpose' => 'IMPORT_SOURCE', 'uploaded_by' => $uploader,
        ]);
    }

    /** ROUTE-272's action: the outcome. */
    public function prepare(string $kind, FileObject $file, ?int $actor = null, ?array $companies = null, ?string $commandId = null): Outcome
    {
        return app(PrepareImport::class)->run($commandId ?? (string) Str::uuid(), $actor ?? $this->admin->id, $kind, $file->public_id, $this->cutoff, $companies ?? [$this->company->public_id]);
    }

    /** The batch a prepare created. */
    public static function batch(Outcome $outcome): ImportBatch
    {
        return ImportBatch::query()->findOrFail($outcome->resultId);
    }

    public function dryRun(ImportBatch $batch, ?int $actor = null): Outcome
    {
        return app(DryRunImport::class)->run((string) Str::uuid(), $actor ?? $this->admin->id, $batch->refresh());
    }

    /** One account's sign-off with its own pool upload as the sign-off evidence. */
    public function signOff(ImportBatch $batch, int $actor): Outcome
    {
        $upload = FileObject::factory()->create(['company_id' => null, 'purpose' => 'UPLOAD', 'uploaded_by' => $actor]);

        return app(SignOffImport::class)->run((string) Str::uuid(), $actor, $batch->refresh(), $upload->public_id);
    }

    public function commit(ImportBatch $batch, ?int $actor = null, ?string $commandId = null): Outcome
    {
        return app(CommitImport::class)->run($commandId ?? (string) Str::uuid(), $actor ?? $this->owner->id, $batch->refresh());
    }

    public function abort(ImportBatch $batch, ?int $actor = null): Outcome
    {
        return app(AbortImport::class)->run((string) Str::uuid(), $actor ?? $this->owner->id, $batch->refresh(), 'Sumber keliru');
    }

    /**
     * Prepare, dry run, both sign-offs and the commit: the batch.
     *
     * @param  list<array<string, string>>  $rows
     */
    public function import(string $kind, array $rows, int $seedAfter = 20): ImportBatch
    {
        $batch = self::batch($this->prepare($kind, $this->source([...$rows, ...$this->seeds($seedAfter)], seeds: false)));
        $this->dryRun($batch);
        $this->signOff($batch, $this->admin->id);
        $this->signOff($batch, $this->owner->id);
        $this->commit($batch);

        return $batch->refresh();
    }

    /** JB-07's chunk run in this process, as a racing session runs it: its word. */
    public static function chunk(int $batchId): string
    {
        CommitImportRows::dispatchSync($batchId);

        return 'done';
    }

    /** @return array<string, int> the batch's rows by state */
    public static function counts(ImportBatch $batch): array
    {
        return array_map('intval', DB::table('import_rows')->where('import_batch_id', $batch->id)->groupBy('validation_state')->selectRaw('validation_state, count(*) as total')->pluck('total', 'validation_state')->all());
    }

    private static function cell(string $value): string
    {
        return str_contains($value, ',') || str_contains($value, '"') ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
