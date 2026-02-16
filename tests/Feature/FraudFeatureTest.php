<?php

use Frolax\Payment\Models\BlocklistEntry;
use Frolax\Payment\Models\RiskAssessment;

// -------------------------------------------------------
// Blocklist
// -------------------------------------------------------

test('blocklist entry is created and checked', function () {
    BlocklistEntry::create([
        'type' => 'email',
        'value' => 'fraud@example.com',
        'reason' => 'Confirmed chargeback',
    ]);

    expect(BlocklistEntry::isBlocked('email', 'fraud@example.com'))->toBeTrue();
    expect(BlocklistEntry::isBlocked('email', 'legit@example.com'))->toBeFalse();
});

test('blocklist respects expiry', function () {
    BlocklistEntry::create([
        'type' => 'ip',
        'value' => '1.2.3.4',
        'reason' => 'Velocity abuse',
        'expires_at' => now()->subHour(),
    ]);

    // Expired entry should NOT block
    expect(BlocklistEntry::isBlocked('ip', '1.2.3.4'))->toBeFalse();
});

test('blocklist entry without expiry stays blocked', function () {
    BlocklistEntry::create([
        'type' => 'card_fingerprint',
        'value' => 'fp_abc123',
        'reason' => 'Stolen card',
        'expires_at' => null,
    ]);

    expect(BlocklistEntry::isBlocked('card_fingerprint', 'fp_abc123'))->toBeTrue();
});

// -------------------------------------------------------
// Risk Assessment
// -------------------------------------------------------

test('risk assessment is persisted', function () {
    $assessment = RiskAssessment::create([
        'payment_id' => 'PAY-001',
        'score' => 75,
        'decision' => 'review',
        'factors' => [
            ['name' => 'high_amount', 'score' => 40],
            ['name' => 'velocity', 'score' => 35],
        ],
        'metadata' => ['ip' => '1.2.3.4'],
    ]);

    expect($assessment->exists)->toBeTrue();
    expect($assessment->score)->toBe(75);
    expect($assessment->decision)->toBe('review');
    expect($assessment->factors)->toHaveCount(2);
});

test('risk assessment scopes by decision', function () {
    RiskAssessment::create(['payment_id' => 'PAY-ALLOW', 'score' => 10, 'decision' => 'allow', 'factors' => []]);
    RiskAssessment::create(['payment_id' => 'PAY-BLOCK', 'score' => 95, 'decision' => 'block', 'factors' => []]);
    RiskAssessment::create(['payment_id' => 'PAY-REVIEW', 'score' => 60, 'decision' => 'review', 'factors' => []]);

    expect(RiskAssessment::where('decision', 'block')->count())->toBe(1);
    expect(RiskAssessment::where('decision', 'review')->count())->toBe(1);
    expect(RiskAssessment::where('decision', 'allow')->count())->toBe(1);
});
