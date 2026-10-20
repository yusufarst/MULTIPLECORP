<?php

namespace Tests\Architecture\Fixtures\App\Modules\FIN\Actions;

use Tests\Architecture\Fixtures\App\Modules\PTY\Models\Supplier;

// An action writing another module's model: refused by the tier rule (ARCHITECTURE §3, §19).
final class RecordPayment
{
    public function __construct(private Supplier $supplier) {}
}
