<?php

namespace App\Http\Middleware;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Queries\CompanyDirectory;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

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
     * WS-07 and AU-06: the page data in browser history is encrypted for every authenticated page, so no page saved
     * under an ended session can be read back (TM-28; H7-12); the client clears history at each authentication
     * boundary (TASK-016).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            Inertia::encryptHistory();
        }

        return parent::handle($request, $next);
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
            // The interface language, so that the page loads its language's strings (LOCALIZATION §3.8; WS-07).
            'locale' => app()->getLocale(),
            // The shell's hints (WS-07; INFORMATION_ARCHITECTURE §6.3, §7): the account's display name, its own
            // effective capabilities — which controls are present (IG-01), the server deciding every request
            // (AZ-01) — and the companies of its scope by code and name; nothing else of the account, no count and
            // no business figure (TASK-016).
            'auth' => fn (): array => $this->account($request),
        ];
    }

    /** @return array{name: ?string, capabilities: list<string>, companies: list<array{id: string, code: string, name: string}>} */
    private function account(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ['name' => null, 'capabilities' => [], 'companies' => []];
        }

        $authority = app(EffectiveCapabilities::class)->for($user);

        return [
            'name' => $user->name,
            'capabilities' => $authority->capabilities,
            'companies' => $authority->active ? app(CompanyDirectory::class)->inScope($authority->scope) : [],
        ];
    }
}
