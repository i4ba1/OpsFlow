<?php

use App\Models\Organization;
use App\Support\RequestNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('starts each organization sequence at 000001', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $generator = app(RequestNumberGenerator::class);

    $year = now()->format('Y');

    expect($generator->next($orgA->id))->toBe("REQ-{$year}-000001");
    expect($generator->next($orgA->id))->toBe("REQ-{$year}-000002");
    expect($generator->next($orgB->id))->toBe("REQ-{$year}-000001");
    expect($generator->next($orgB->id))->toBe("REQ-{$year}-000002");
});

it('never hands out a duplicate number across twenty sequential allocations', function () {
    $org = Organization::factory()->create();

    $generator = app(RequestNumberGenerator::class);

    $numbers = [];

    for ($i = 0; $i < 20; $i++) {
        $numbers[] = $generator->next($org->id);
    }

    expect($numbers)->toHaveCount(20);
    expect(count(array_unique($numbers)))->toBe(20);
    expect($numbers[0])->toBe('REQ-'.now()->format('Y').'-000001');
    expect($numbers[19])->toBe('REQ-'.now()->format('Y').'-000020');

    $sequence = DB::table('request_sequences')
        ->where('organization_id', $org->id)
        ->where('year', now()->format('Y'))
        ->first();

    expect($sequence)->not->toBeNull();
    expect((int) $sequence->last_value)->toBe(20);
});

it('keeps sequences independent across years and organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $generator = app(RequestNumberGenerator::class);

    // Simulate a row that already exists from a previous year.
    DB::table('request_sequences')->insert([
        'organization_id' => $orgA->id,
        'year' => (int) now()->format('Y') - 1,
        'last_value' => 42,
    ]);

    expect($generator->next($orgA->id))->toBe('REQ-'.now()->format('Y').'-000001');
    expect($generator->next($orgB->id))->toBe('REQ-'.now()->format('Y').'-000001');

    $rows = DB::table('request_sequences')
        ->where('organization_id', $orgA->id)
        ->orderBy('year')
        ->get();

    expect($rows)->toHaveCount(2);
});
