<?php

namespace Tests\Architecture\Fixtures\App\Modules\PRJ\Actions;

use Tests\Architecture\Fixtures\App\Http\Controllers\QuotationController;

// An action depending on a controller: refused by the layer rule (ARCHITECTURE §2).
final class ApproveQuotation
{
    public function __construct(private QuotationController $controller) {}
}
