<?php

namespace App\Modules\ImportOrchestration\Data;

/** An import row as its commit reads it: its batch, number, identity, typed company and normalized payload. */
final readonly class ImportRowData
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public int $id,
        public int $batchId,
        public string $batchPublicId,
        public string $cutoff,
        public int $rowNumber,
        public string $kind,
        public string $identity,
        public ?int $companyId,
        public array $payload,
    ) {}

    /** The row's deterministic command identity, `import:{batch}:{row}:{identity}`, a later step's with its number (CI-01). */
    public function name(int $step): string
    {
        return "{$this->batchPublicId}:{$this->rowNumber}:{$this->identity}".($step === 0 ? '' : ':'.($step + 1));
    }

    public function text(string $key): string
    {
        $value = $this->payload[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    public function nullable(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        $lines = [];

        foreach (is_array($this->payload['lines'] ?? null) ? $this->payload['lines'] : [] as $line) {
            if (is_array($line)) {
                $row = [];

                foreach ($line as $key => $value) {
                    $row[(string) $key] = $value;
                }

                $lines[] = $row;
            }
        }

        return $lines;
    }
}
