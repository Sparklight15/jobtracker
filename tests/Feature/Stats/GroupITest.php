<?php

namespace Tests\Feature\Stats;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupITest extends TestCase
{
    use RefreshDatabase;

    public function test_group_i_calculates_prediction_score_accurately()
    {
        config(['stats.min_sample.prediction' => 1]);
        config(['stats.min_sample.breakdown' => 1]);

        $user = User::factory()->create();

        // PERBAIKAN: Gunakan current_status
        $successJob = Job::factory()->create(['user_id' => $user->id, 'current_status' => JobStatus::Rejected]);
        $successJob->statusHistory()->create(['status' => JobStatus::Interview, 'changed_at' => now()->subDays(10)]);
        
        $failedJob = Job::factory()->create(['user_id' => $user->id, 'current_status' => JobStatus::Rejected]);
        $failedJob->statusHistory()->create(['status' => JobStatus::Rejected, 'changed_at' => now()->subDays(10)]);

        $activeJob = Job::factory()->create([
            'user_id' => $user->id,
            'current_status' => JobStatus::Applied,
            'cv_customization' => CvCustomization::Tailored,
            'channel' => Channel::Referral,
            'applied_date' => now(),
        ]);
        
        $activeJob->statusHistory()->create(['status' => JobStatus::Applied, 'changed_at' => now()]);

        $response = $this->actingAs($user)->getJson('/stats/I');
        $response->assertStatus(200);

        $response->assertJsonPath('cards.prediction_score.status', 'ok');
        $this->assertEquals(75, $response->json('cards.prediction_score.value'));
    }

    public function test_group_i_returns_insufficient_if_no_active_jobs_for_prediction()
    {
        config(['stats.min_sample.prediction' => 1]);
        
        $user = User::factory()->create();
        
        // PERBAIKAN: Gunakan current_status
        Job::factory()->create(['user_id' => $user->id, 'current_status' => JobStatus::Rejected]);

        $response = $this->actingAs($user)->getJson('/stats/I');
        $response->assertStatus(200);

        $response->assertJsonPath('cards.prediction_score.value', null);
    }
}