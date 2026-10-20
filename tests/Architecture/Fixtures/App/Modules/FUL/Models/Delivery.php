<?php

namespace Tests\Architecture\Fixtures\App\Modules\FUL\Models;

use Tests\Architecture\Fixtures\App\Modules\PRJ\Models\Project;

// A model's relationship to another module's model: accepted, as PF-03's eager loads need it.
final class Delivery
{
    public function project(): string
    {
        return Project::class;
    }
}
