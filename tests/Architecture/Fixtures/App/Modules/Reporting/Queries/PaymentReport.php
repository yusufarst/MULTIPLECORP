<?php

namespace Tests\Architecture\Fixtures\App\Modules\Reporting\Queries;

use Tests\Architecture\Fixtures\App\Modules\FIN\Actions\RecordPayment;

// Reporting writing through an action: refused, as Reporting only reads (ARCHITECTURE §3).
final class PaymentReport
{
    public function __construct(private RecordPayment $recordPayment) {}
}
