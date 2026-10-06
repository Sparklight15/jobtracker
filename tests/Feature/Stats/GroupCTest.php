<?php

namespace Tests\Feature\Stats;

use App\Enums\Channel;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Loker dengan channel dan status tetap. Riwayat berupa daftar [status, hari setelah apply].
     * Tanpa $responded, first_response_date dibiarkan kosong.
     */
    private function job(User $user, Channel $channel, JobStatus $status = JobStatus::Applied, bool $responded = false, array $history = []): Job
    {
        $applied = today()->subDays(10);

        $job = Job::factory()->for($user)->create([
            'channel' => $channel,
            'current_status' => $status,
            'applied_date' => $applied,
            'first_response_date' => $responded ? $applied->copy()->addDays(2) : null,
        ]);

        foreach ($history as [$step, $offset]) {
            $job->statusHistory()->create([
                'status' => $step,
                'changed_at' => $applied->copy()->addDays($offset),
            ]);
        }

        return $job;
    }

    /**
     * LinkedIn: 8 loker, 4 direspons, 3 pernah Interview/Offer.
     * Referral: 5 loker, 1 direspons, 0 conversion.
     * Cold Apply: 2 loker (di bawah batas per channel).
     */
    private function seedChannels(User $user): void
    {
        $a = JobStatus::Applied;
        $s = JobStatus::Screening;
        $i = JobStatus::Interview;
        $r = JobStatus::Rejected;

        // Pernah Interview lewat riwayat, sekarang Rejected
        $this->job($user, Channel::Linkedin, $r, true, [[$a, 0], [$i, 3], [$r, 6]]);
        // Offer tanpa riwayat: tetap dihitung dari current_status
        $this->job($user, Channel::Linkedin, JobStatus::Offer, true);
        // Sedang Interview
        $this->job($user, Channel::Linkedin, $i, true);
        // Direspons tapi berhenti di Screening: bukan conversion
        $this->job($user, Channel::Linkedin, $r, true, [[$a, 0], [$s, 2], [$r, 5]]);
        // Sisanya belum ada respons
        for ($n = 0; $n < 4; $n++) {
            $this->job($user, Channel::Linkedin);
        }

        $this->job($user, Channel::Referral, $s, true);
        for ($n = 0; $n < 4; $n++) {
            $this->job($user, Channel::Referral);
        }

        $this->job($user, Channel::ColdApply, $i, true);
        $this->job($user, Channel::ColdApply);
    }

    // ----- #10 distribusi -----

    public function test_distribusi_per_channel_urut_terbanyak(): void
    {
        $user = User::factory()->create();
        $this->seedChannels($user);

        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('charts.channel_distribution.status', 'ok')
            ->assertJsonPath('charts.channel_distribution.type', 'hbar')
            ->assertJsonPath('charts.channel_distribution.labels', ['LinkedIn', 'Referral', 'Cold Apply'])
            ->assertJsonPath('charts.channel_distribution.series.0.data', [8, 5, 2]);
    }

    public function test_distribusi_butuh_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        for ($n = 0; $n < 3; $n++) {
            $this->job($user, Channel::Linkedin);
        }

        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('charts.channel_distribution.status', 'insufficient')
            ->assertJsonPath('charts.channel_distribution.current', 3)
            ->assertJsonPath('charts.channel_distribution.min_required', 5);
    }

    // ----- #11 conversion -----

    public function test_conversion_dari_riwayat_dan_status_sekarang(): void
    {
        $user = User::factory()->create();
        $this->seedChannels($user);

        // LinkedIn 3/8 = 37,5%. Referral 0/5 = 0%. Cold Apply hanya 2 sampel: kosong.
        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('charts.channel_conversion.status', 'ok')
            ->assertJsonPath('charts.channel_conversion.unit', '%')
            ->assertJsonPath('charts.channel_conversion.labels', ['LinkedIn', 'Referral', 'Cold Apply'])
            ->assertJsonPath('charts.channel_conversion.series.0.data', [37.5, 0, null])
            ->assertJsonPath('charts.channel_conversion.counts', [
                ['n' => 3, 'of' => 8],
                ['n' => 0, 'of' => 5],
                ['n' => 1, 'of' => 2],
            ]);
    }

    // ----- #12 response rate -----

    public function test_response_rate_per_channel(): void
    {
        $user = User::factory()->create();
        $this->seedChannels($user);

        // LinkedIn 4/8 = 50%. Referral 1/5 = 20%. Cold Apply: kosong.
        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('charts.channel_response_rate.status', 'ok')
            ->assertJsonPath('charts.channel_response_rate.series.0.data', [50, 20, null])
            ->assertJsonPath('charts.channel_response_rate.counts.0', ['n' => 4, 'of' => 8]);
    }

    public function test_persentase_butuh_satu_channel_dengan_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        // 6 loker tersebar 2-2-2: distribusi cukup, tapi tidak ada channel yang layak dihitung persentasenya
        foreach ([Channel::Linkedin, Channel::Referral, Channel::ColdApply] as $channel) {
            $this->job($user, $channel);
            $this->job($user, $channel);
        }

        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('charts.channel_distribution.status', 'ok')
            ->assertJsonPath('charts.channel_conversion.status', 'insufficient')
            ->assertJsonPath('charts.channel_conversion.current', 2)
            ->assertJsonPath('charts.channel_conversion.min_required', 5)
            ->assertJsonPath('charts.channel_response_rate.status', 'insufficient');
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        for ($n = 0; $n < 6; $n++) {
            $this->job($other, Channel::Linkedin);
        }

        $this->actingAs($user)
            ->getJson('/stats/C')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('charts.channel_distribution.status', 'insufficient')
            ->assertJsonPath('charts.channel_conversion.status', 'insufficient');
    }
}