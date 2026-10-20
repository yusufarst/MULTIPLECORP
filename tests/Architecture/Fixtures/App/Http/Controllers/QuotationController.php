<?php

namespace Tests\Architecture\Fixtures\App\Http\Controllers;

use Illuminate\Support\Facades\DB;

// A controller doing database work: refused by the thin-controller rule (ARCHITECTURE §2).
final class QuotationController
{
    public function approve(int $id): int
    {
        return DB::table('quotation_revisions')->where('id', $id)->update(['state' => 'APPROVED']);
    }
}
