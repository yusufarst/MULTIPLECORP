<?php

use App\Modules\IAM\Authentication\AccountEvents;
use App\Support\Localization\Locales;
use App\Support\SecurityLog\SecurityEventKind;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// TL-03 (TASK-023; RB-10; AU-13; AU-18): the display language's value rule — `id`, `en` or the default, nothing else —,
// a stored language read back only when supported, and AU-13's list of the account's own credential and factor events.

it('admits id, en and the default as the display language, and refuses any other value (RB-10; INT-002)', function () {
    $rule = ['locale' => ['required', Rule::in(['id', 'en', 'default'])]];

    foreach (['id', 'en', 'default'] as $value) {
        expect(Validator::make(['locale' => $value], $rule)->passes())->toBeTrue();
    }

    foreach (['fr', 'ID', '', 'id-ID', 'en; drop'] as $value) {
        expect(Validator::make(['locale' => $value], $rule)->passes())->toBeFalse();
    }

    expect(Locales::supported('en'))->toBe('en')->and(Locales::supported('fr'))->toBeNull()->and(Locales::DEFAULT)->toBe('id');
});

it('lists every credential and factor event AU-13 names, and never a sign-in or a denial (AU-13; H7-14)', function () {
    $kinds = array_map(fn (SecurityEventKind $kind): string => $kind->value, AccountEvents::KINDS);

    expect($kinds)->toContain('PASSWORD_CHANGED', 'TOTP_ENROLLED', 'TOTP_REPLACED', 'TOTP_RESET', 'TOTP_DISABLED', 'CREDENTIALS_VOIDED',
        'RECOVERY_CODE_USED', 'RECOVERY_CODES_VIEWED', 'RECOVERY_CODES_REGENERATED', 'TWO_FACTOR_COOLDOWN_STARTED', 'GOOGLE_LINKED',
        'GOOGLE_UNLINKED', 'ACCOUNT_SETUP_LINK_ISSUED', 'PASSWORD_RESET_REQUESTED', 'OWNER_RECOVERY_LINK_ISSUED')
        ->and($kinds)->not->toContain('LOGIN_SUCCEEDED')->not->toContain('LOGIN_FAILED')->not->toContain('ACCESS_DENIED');

    foreach ($kinds as $kind) {
        expect(__("account.events.{$kind}"))->not->toBe("account.events.{$kind}");
    }
});
