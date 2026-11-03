<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\InvoiceWorld;

/**
 * TL-09–TL-12 (TASK-055; J-PRJ-03; UXS-37; CJ-17's completion steps): over InvoiceFixture's confirmed service project, its remainder cancelled through TASK-034's command when the
 * run asks for a project ready to complete; otherwise its unmet fulfilment stands for the Owner's Force Complete. Synthetic data only.
 */
final class CompletionFixture
{
    public static function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $project = DB::table('projects')->where('public_id', (string) $request->input('project'))->value('id');

        if (! $user instanceof User || ! is_numeric($project)) {
            return response()->json(['error' => 'unknown'], 422);
        }

        if ($request->boolean('ready')) {
            // The signed-in Owner cancels the remainder: a fixture's command runs as the session's account.
            InvoiceWorld::readyToComplete((int) $project, false, $user->id);
        }

        return response()->json(['ready' => $request->boolean('ready')]);
    }
}
