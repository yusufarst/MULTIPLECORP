<?php

namespace App\Modules\DOC\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The guard of an evidence document or a bank statement line (DATABASE §4.10 `evidence_balances`, GB;
 * §3.1): posted only by EvidencePosting with the facts that cite it, so what is cited or expensed never
 * exceeds what is proven (C-18–C-20, C-23). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19), and a test writes the row as the posting would.
 *
 * @property int $id
 * @property int|null $evidence_document_id
 * @property int|null $bank_statement_line_id
 * @property string $proven_amount
 * @property string $cited_amount
 * @property string $expensed_amount
 * @property CarbonImmutable $recorded_at
 */
class EvidenceBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<BankStatementLine, $this> */
    public function bankStatementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_document_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'proven_amount' => 'decimal:2',
            'cited_amount' => 'decimal:2',
            'expensed_amount' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
