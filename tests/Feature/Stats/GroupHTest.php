<?php

namespace Tests\Feature\Stats;

use App\Enums\JobStatus;
use App\Models\BenchmarkReference;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupHTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_h_returns_correct_benchmark_comparison()
    {
        config(['stats.min_sample.average' => 1]);

        $user = User::factory()->create([
            'job_search_started_at' => now()->subDays(60),
        ]);

        BenchmarkReference::create([
            'metric_key' => BenchmarkReference::JOB_SEARCH_DURATION_MONTHS,
            'value' => 5.5,
            'source' => 'Test',
            'source_year' => 2024,
        ]);
        
        BenchmarkReference::create([
            'metric_key' => 'time_to_hire_days',
            'value' => 43,
            'source' => 'Test',
            'source_year' => 2024,
        ]);

        // PERBAIKAN: Gunakan current_status
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'applied_date' => now()->subDays(20),
            'current_status' => JobStatus::Offer, 
        ]);

        $job->statusHistory()->create([
            'status' => JobStatus::Offer,
            'changed_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user)->getJson('/stats/H');
        $response->assertStatus(200);

        $response->assertJsonPath('cards.search_duration_vs_market.status', 'ok');
        $this->assertEquals(2.0, $response->json('cards.search_duration_vs_market.value'));
        
        $response->assertJsonPath('charts.time_to_hire_vs_market.status', 'ok');
        $chartSeries = $response->json('charts.time_to_hire_vs_market.series');
        $this->assertEquals(15, $chartSeries[0]['data'][0]);
        $this->assertEquals(43, $chartSeries[1]['data'][0]);
    }

    public function test_group_h_handles_insufficient_data()
    {
        $user = User::factory()->create();
        
        // PERBAIKAN: Gunakan current_status
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'current_status' => JobStatus::Offer,
        ]);
        
        $job->statusHistory()->create(['status' => JobStatus::Offer, 'changed_at' => now()]);

        $response = $this->actingAs($user)->getJson('/stats/H');
        $response->assertStatus(200);

        $response->assertJsonPath('charts.time_to_hire_vs_market.status', 'insufficient');
    }
}