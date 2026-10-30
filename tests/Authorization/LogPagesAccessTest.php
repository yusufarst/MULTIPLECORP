<?php

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Database\Factories\OPS\AuditEventFactory;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-061; OD-14; AZ-11; LG-03; PJ-17; PJ-21; criteria 1, 4): only the Owner reaches PG-063 and PG-064 — an
// Admin's request for either address answers exactly as an unknown page —, and neither page carries a raw login
// identifier, address, provider subject or a key LG-03 keeps out of every record.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

it('answers an Admin\'s request for either page exactly as an unknown page (OD-14; AZ-11; criterion 1)', function () {
    $admin = Authorization::admin([], ['projects.view', 'users.manage', 'permissions.manage', 'companies.manage']);
    $unknown = $this->actingAs($admin)->get('/aktivitas/tidak-ada');

    foreach (['/aktivitas/riwayat', '/aktivitas/log-keamanan'] as $path) {
        $answer = $this->actingAs($admin)->get($path);
        $answer->assertNotFound();
        expect($answer->viewData('page')['component'] ?? null)->toBe($unknown->viewData('page')['component'] ?? null);
    }

    $this->actingAs($this->owner)->get('/aktivitas/riwayat')->assertOk();
    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan')->assertOk();
});

it('carries no raw identifier, address, provider subject or forbidden key on either page (LG-03; PJ-17; PJ-21; criterion 4)', function () {
    $recorder = app(SecurityEventRecorder::class);
    $recorder->record(SecurityEventKind::LoginFailed, reasonCode: 'CREDENTIALS', routeName: 'login', identifier: 'rani@contoh.test', ipAddress: '203.0.113.7', userAgent: 'Mozilla/5.0 Chrome/140');
    $recorder->record(SecurityEventKind::GoogleSigninFailed, reasonCode: 'NO_LINK', providerSubject: '100000000000000000007', ipAddress: '203.0.113.8');
    AuditEventFactory::new()->createOne(['action' => 'IAM.UserUpdated', 'authority_class' => 'ADM', 'metadata' => ['token' => 'rahasia-token', 'note' => 'aman']]);

    $security = $this->actingAs($this->owner)->get('/aktivitas/log-keamanan')->getContent();
    $history = $this->actingAs($this->owner)->get('/aktivitas/riwayat')->getContent();

    expect($security)->not->toContain('rani@contoh.test')->not->toContain('203.0.113.7')->not->toContain('203.0.113.8')->not->toContain('100000000000000000007')
        ->and($history)->not->toContain('rahasia-token')->not->toContain('"metadata"');
});
