<?php

namespace Tests\Architecture\Fixtures\App\Modules\ORG\Actions;

use Tests\Architecture\Fixtures\App\Modules\PRJ\Actions\CreateProject;

// Tier 1 writing through tier 2: refused by the tier rule (ARCHITECTURE §3).
final class CreateCompany
{
    public function __construct(private CreateProject $createProject) {}
}
