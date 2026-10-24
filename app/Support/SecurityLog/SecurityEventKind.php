<?php

namespace App\Support\SecurityLog;

/**
 * The 39 security-event kinds of SECURITY LG-02, those of the `security_events_kind` CHECK (DATABASE §4.13).
 */
enum SecurityEventKind: string
{
    case LoginSucceeded = 'LOGIN_SUCCEEDED';
    case LoginFailed = 'LOGIN_FAILED';
    case Throttled = 'THROTTLED';
    case Logout = 'LOGOUT';
    case SessionInvalidated = 'SESSION_INVALIDATED';
    case PasswordResetRequested = 'PASSWORD_RESET_REQUESTED';
    case PasswordResetCompleted = 'PASSWORD_RESET_COMPLETED';
    case AccountSetupLinkIssued = 'ACCOUNT_SETUP_LINK_ISSUED';
    case AccountSetupCompleted = 'ACCOUNT_SETUP_COMPLETED';
    case OwnerRecoveryLinkIssued = 'OWNER_RECOVERY_LINK_ISSUED';
    case CredentialLinkRejected = 'CREDENTIAL_LINK_REJECTED';
    case PasswordChanged = 'PASSWORD_CHANGED';
    case PasswordConfirmationFailed = 'PASSWORD_CONFIRMATION_FAILED';
    case AccessDenied = 'ACCESS_DENIED';
    case RequestForgeryRejected = 'REQUEST_FORGERY_REJECTED';
    case UploadRejected = 'UPLOAD_REJECTED';
    case ExportRequested = 'EXPORT_REQUESTED';
    case ExportDownloaded = 'EXPORT_DOWNLOADED';
    case AuthorizationAnomaly = 'AUTHORIZATION_ANOMALY';
    case EvidenceDuplicateUndisclosed = 'EVIDENCE_DUPLICATE_UNDISCLOSED';
    case ImportCollisionUndisclosed = 'IMPORT_COLLISION_UNDISCLOSED';
    case TwoFactorFailed = 'TWO_FACTOR_FAILED';
    case TwoFactorCooldownStarted = 'TWO_FACTOR_COOLDOWN_STARTED';
    case TwoFactorCooldownRefused = 'TWO_FACTOR_COOLDOWN_REFUSED';
    case OwnerRestored = 'OWNER_RESTORED';
    case TotpEnrolled = 'TOTP_ENROLLED';
    case TotpReplaced = 'TOTP_REPLACED';
    case TotpReset = 'TOTP_RESET';
    case TotpDisabled = 'TOTP_DISABLED';
    case CredentialsVoided = 'CREDENTIALS_VOIDED';
    case RecoveryCodeUsed = 'RECOVERY_CODE_USED';
    case RecoveryCodesViewed = 'RECOVERY_CODES_VIEWED';
    case RecoveryCodesRegenerated = 'RECOVERY_CODES_REGENERATED';
    case GoogleSigninFailed = 'GOOGLE_SIGNIN_FAILED';
    case GoogleLinked = 'GOOGLE_LINKED';
    case GoogleUnlinked = 'GOOGLE_UNLINKED';
    case GoogleLinkConflict = 'GOOGLE_LINK_CONFLICT';
    case PasswordCheckSkipped = 'PASSWORD_CHECK_SKIPPED';
    case TotpSecretsReencrypted = 'TOTP_SECRETS_REENCRYPTED';

    /**
     * The two kinds that are the stored source of two Owner-review items (TECH-022; GAP-034), written only
     * after their command's outcome is durable, once per command and matched record.
     */
    public function isUndisclosed(): bool
    {
        return $this === self::EvidenceDuplicateUndisclosed || $this === self::ImportCollisionUndisclosed;
    }
}
