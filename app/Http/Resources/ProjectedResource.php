<?php

namespace App\Http\Resources;

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The base of every prop resource (PERMISSIONS_MATRIX §7.1; ARCHITECTURE §8; SECURITY WS-07): a resource lists
 * each field with the audience that may see it, and a field the viewer is not entitled to is not sent at all
 * — never sent and hidden on the client. A value is computed only for a viewer it admits, so a field can
 * never leak through its own evaluation. A resource never falls back to the model's attributes.
 */
abstract class ProjectedResource extends JsonResource
{
    /**
     * Each field with its audience and the closure that computes it.
     *
     * @return array<string, array{0: Audience, 1: Closure(): mixed}>
     */
    abstract protected function projection(Authority $viewer): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = self::viewer($request);
        $fields = [];

        foreach ($this->projection($viewer) as $name => [$audience, $value]) {
            if ($audience->admits($viewer)) {
                $fields[$name] = $value();
            }
        }

        return $fields;
    }

    /**
     * A field: its audience and its value, computed only when admitted.
     *
     * @return array{0: Audience, 1: Closure(): mixed}
     */
    protected static function field(Audience $audience, Closure $value): array
    {
        return [$audience, $value];
    }

    /** The request's account as the resolver sees it; a guest sees nothing. */
    public static function viewer(Request $request): Authority
    {
        $user = $request->user();

        return $user === null ? Authority::none(0) : app(EffectiveCapabilities::class)->for($user);
    }

    /**
     * Page props gated before evaluation (WS-07; DP-15; PF-33): a prop the viewer may not see is not defined at
     * all, so a partial reload naming it obtains nothing, and an admitted one stays a closure, evaluated only
     * when the page or a partial reload asks for it.
     *
     * @param  array<string, array{0: Audience, 1: Closure(): mixed}>  $props
     * @return array<string, Closure(): mixed>
     */
    public static function props(Authority $viewer, array $props): array
    {
        $gated = [];

        foreach ($props as $name => [$audience, $value]) {
            if ($audience->admits($viewer)) {
                $gated[$name] = $value;
            }
        }

        return $gated;
    }
}
