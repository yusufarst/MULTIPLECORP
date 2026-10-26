<?php

namespace App\Http\Controllers;

use App\Support\Status\StatusRead;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ROUTE-285 (INT-285): the current state of a deferred group a page polls — renditions, exports, the import
 * batch —, a background request by its route that reads the session and never extends it (RB-05; RV-06). Each
 * kind is read by its page's query class, registered under {@see StatusRead::TAG}.
 */
final class StatusController extends Controller
{
    public function __invoke(Request $request, Application $app, string $kind, string $id): JsonResponse
    {
        $user = $request->user() ?? throw new NotFoundHttpException;

        foreach ($app->tagged(StatusRead::TAG) as $read) {
            if ($read instanceof StatusRead && $read->kind() === $kind) {
                return new JsonResponse($read->read($user, $id));
            }
        }

        throw new NotFoundHttpException;
    }
}
