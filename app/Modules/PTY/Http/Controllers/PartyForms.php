<?php

namespace App\Modules\PTY\Http\Controllers;

use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What PG-054's and PG-055's commands share at the route (TASK-026): the named fields validated with the
 * catalogue's messages (WS-01; IP-14), a unit's taken name at its field, and the answer in place as flash data
 * (IP-01; IG-02) — the duplicate list of a search-first creation with it (PF-21; IP-05) — before the page it goes
 * back to: the record, or, for an inline creation, the form it was opened from with the new value chosen (IP-19).
 */
final class PartyForms
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function validated(Request $request, array $rules): array
    {
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('parties.validation.required'),
            'required_if' => __('parties.validation.required'),
            'max' => __('parties.validation.too_long'),
            'in' => __('parties.validation.choice'),
            'email' => __('parties.validation.email'),
            'regex' => __('parties.validation.format'),
        ])->validate();

        return $valid;
    }

    /** BD-16: a unit's name its organization already holds, at the unit's name field. */
    public static function fieldRefusal(Outcome $outcome): void
    {
        $field = $outcome->state['field'] ?? null;

        if ($outcome->kind === Outcome::CONFLICT && $outcome->code === 'SQ-08') {
            throw ValidationException::withMessages([is_string($field) ? $field : 'unit_name' => __('parties.validation.unit_taken')]);
        }
    }

    /**
     * @param  list<array{id: string, label: string, href: string}>  $matches  the duplicate list, already resolved to names
     * @param  array<string, mixed>  $created  what an inline creation chose
     */
    public static function answer(Outcome $outcome, string $done, string $to, array $matches = [], array $created = []): RedirectResponse
    {
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __($done)],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches)],
            Outcome::CONFLICT => $outcome->code === 'ST-01' ? ['stale', __('messages.msg_03')] : ['recorded', __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text,
            'state' => array_filter(['matches' => $matches, 'created' => $created], fn (array $value): bool => $value !== [])]);

        return redirect()->to($to, 303);
    }

    public static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** @return list<string> */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /** @param  list<array{id: string, label: string, href: string}>  $matches */
    private static function refusal(Outcome $outcome, array $matches): string
    {
        if ($outcome->code === 'PF-21') {
            return __('messages.msg_15', ['daftar_catatan_yang_cocok' => implode(', ', array_column($matches, 'label'))]);
        }

        $reason = $outcome->state['reason'] ?? null;
        $key = 'parties.refusal.'.strtolower(str_replace('-', '_', (string) $outcome->code)).(is_string($reason) ? '_'.$reason : '');

        return __($key) !== $key ? __($key) : __('messages.msg_04');
    }
}
