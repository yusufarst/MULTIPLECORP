<?php

namespace App\Modules\ORG\Services;

/**
 * Go-live until TASK-077 binds the store P11Q-04 defines (DIR-085 OF-04; DIR-098 reading 2): not recorded.
 */
final class UnrecordedGoLive implements NumberingGoLive
{
    public function day(): ?string
    {
        return null;
    }
}
