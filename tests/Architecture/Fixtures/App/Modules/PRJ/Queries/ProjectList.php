<?php

namespace Tests\Architecture\Fixtures\App\Modules\PRJ\Queries;

use Tests\Architecture\Fixtures\App\Modules\PTY\Models\Supplier;

// A query reading another module's model instead of its query class: refused (ARCHITECTURE §3).
final class ProjectList
{
    public function __construct(private Supplier $supplier) {}
}
