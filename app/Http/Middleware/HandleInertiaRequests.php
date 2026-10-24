<?php

namespace App\Http\Middleware;

use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default; only what SECURITY WS-07 allows.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // The account's own effective capabilities, a hint for which controls are present (IG-01): the
            // server decides every request (AZ-01; WS-07).
            'auth' => fn (): array => [
                'capabilities' => $request->user() === null ? [] : app(EffectiveCapabilities::class)->for($request->user())->capabilities,
            ],
        ];
    }
}
