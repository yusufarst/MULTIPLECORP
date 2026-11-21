<?php

// The monitoring of INFRASTRUCTURE §6 (TASK-075). Every value is a placeholder the operator sets (TASK-080): the channel
// is OPI-05's — e-mail through the Owner's existing Cloudflare Email Service to verified destinations only, at no cost
// (DIR-129; COST_POLICY §4C.4) —, its token a secret of SX-01 read from the environment, never the repository.
return [

    'channel' => [
        // The sending endpoint of the channel's account, as its own documentation names it; unset, nothing is sent
        // and the floor stays the in-app notice and the operator's daily look (DIR-079 CB-06).
        'url' => env('ALERT_CHANNEL_URL'),
        'token' => env('ALERT_CHANNEL_TOKEN'),
        'from' => env('ALERT_FROM'),
        'timeout_seconds' => 5,
    ],

    // The named operator, the alternate once TASK-083 names one, and the Owner — verified addresses only (OPI-05).
    'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('ALERT_RECIPIENTS', ''))))),

    // One message per signal per window while it stays failing, so a stuck signal never floods the channel.
    'throttle_minutes' => (int) env('ALERT_THROTTLE_MINUTES', 60),

    'heartbeat' => [
        'queue_max' => (int) env('ALERT_QUEUE_MAX', 1000),
        'oldest_minutes' => (int) env('ALERT_OLDEST_JOB_MINUTES', 15),
        'owed_minutes' => (int) env('ALERT_RENDITION_OWED_MINUTES', 15),
        'disk_warn_pct' => (int) env('BACKUP_DISK_WARN_PCT', 80),
        'disk_critical_pct' => (int) env('BACKUP_DISK_CRIT_PCT', 90),
    ],

    // SZ-06: a statement slower than this is logged, its text without its values.
    'slow_statement_ms' => (int) env('SLOW_STATEMENT_MS', 500),

    // PFM-18 and GT-07's monthly report: crossing half of a threshold opens a measured review, never a change.
    'report' => [
        'mean_ms' => 500,
        'table_bytes' => 10 * 1024 ** 3,
    ],
];
