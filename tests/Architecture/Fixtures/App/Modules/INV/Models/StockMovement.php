<?php

namespace Tests\Architecture\Fixtures\App\Modules\INV\Models;

use Illuminate\Http\Request;

// A domain class reading the request: refused by the layer rule (ARCHITECTURE §2).
final class StockMovement
{
    public function fromRequest(Request $request): string
    {
        return $request->path();
    }
}
