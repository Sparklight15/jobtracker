<?php

namespace Tests\Feature\Stats;

use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupGTest extends TestCase
{
    use RefreshDatabase;

    private const A = JobStatus::Applied;

    private const S = JobStatus::Screening;

    private const I = JobStatus::Interview;

    private const R = JobStatus::Rejected;

    private const G = JobStatus::Ghosted;

    /** Loker Rejected dengan riwayat berupa daftar [status, hari setelah apply]. */
    private function rejected(User $user, array $history, ?RejectionReason $reason = null): Job
    {
        $applied = today()->subDays(40);

        $job = Job::factory()->for($user)->create([
            'current_status' => JobStatus::Rejected,
            'rejection_reason' => $reason,
            'offer_decision' => null,
            'applied_date' => $applied,
        ]);

        foreach ($history as [$step, $offset]) {
            $job->statusHistory()->create([
                'status' => $step,
                'changed_at' => $applied->copy()->addDays($offset),
            ]);
        }

        return $job;
    }

    private function offer(User $user, ?OfferDecision $decision): Job
    {
        return Job::factory()->for($user)->create([
            'current_status' => JobStatus::Offer,
            'offer_decision' => $decision,
            'rejection_reason' => null,
            'applied_date' => today()->subDays(40),
        ]);
    }

    /**
     * 7 loker Rejected:
     * - 3 dari Screening (Skill kurang x2, Gaji tidak sesuai)
     * - 2 dari Interview (Skill kurang, Culture fit)
     * - 1 langsung dari Applied (tanpa alasan)
     * - 1 Interview -> Ghosted -> Rejected (Tidak ada respons), dihitung Interview
     */
    private function seedRejected(User $user): void
    {
        $screening = [[self::A, 0], [self::S, 2], [self::R, 5]];
        $interview = [[self::A, 0], [self::S, 2], [self::I, 5], [self::R, 9]];

        $this->rejected($user, $screening, RejectionReason::SkillGap);
        $this->rejected($user, $screening, RejectionReason::SkillGap);
        $this->rejected($user, $screening, RejectionReason::SalaryMismatch);
        $this->rejected($user, $interview, RejectionReason::SkillGap);
        $this->rejected($user, $interview, RejectionReason::CultureFit);
        $this->rejected($user, [[self::A, 0], [self::R, 4]], null);
        $this->rejected($user, [[self::A, 0], [self::I, 3], [self::G, 20], [self::R, 25]], RejectionReason::NoResponse);
    }

    // ----- #22 alasan rejection -----

    public function test_alasan_rejection_urut_terbanyak_dan_tidak_diisi_terakhir(): void
    {
        $user = User::factory()->create();
        $this->seedRejected($user);

        // Seri (nilai 1) diurut abjad. "Tidak diisi" selalu terakhir.
        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.rejection_reasons.status', 'ok')
            ->assertJsonPath('charts.rejection_reasons.type', 'hbar')
            ->assertJsonPath('charts.rejection_reasons.labels', [
                'Skill kurang', 'Culture fit', 'Gaji tidak sesuai', 'Tidak ada respons', 'Tidak diisi',
            ])
            ->assertJsonPath('charts.rejection_reasons.series.0.data', [3, 1, 1, 1, 1]);
    }

    public function test_hanya_loker_rejected_yang_dihitung(): void
    {
        $user = User::factory()->create();
        $this->seedRejected($user);

        // Loker aktif dengan alasan terisi tidak boleh ikut terhitung
        Job::factory()->for($user)->create([
            'current_status' => JobStatus::Applied,
            'rejection_reason' => RejectionReason::Other,
            'applied_date' => today()->subDays(10),
        ]);

        $response = $this->actingAs($user)->getJson('/stats/G');

        $this->assertSame(7, array_sum($response->json('charts.rejection_reasons.series.0.data')));
        $this->assertNotContains('Lainnya', $response->json('charts.rejection_reasons.labels'));
    }

    public function test_rejection_butuh_minimal_lima_loker_rejected(): void
    {
        $user = User::factory()->create();

        for ($n = 0; $n < 4; $n++) {
            $this->rejected($user, [[self::A, 0], [self::R, 3]], RejectionReason::Other);
        }

        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.rejection_reasons.status', 'insufficient')
            ->assertJsonPath('charts.rejection_reasons.current', 4)
            ->assertJsonPath('charts.rejection_reasons.min_required', 5)
            ->assertJsonPath('charts.rejection_stage.status', 'insufficient');
    }

    // ----- #23 tahap penolakan -----

    public function test_ditolak_di_tahap_mana_dari_status_sebelum_rejected(): void
    {
        $user = User::factory()->create();
        $this->seedRejected($user);

        // Applied 1, Screening 3, Interview 3 (termasuk yang lewat Ghosted). Tanpa "Lainnya".
        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.rejection_stage.status', 'ok')
            ->assertJsonPath('charts.rejection_stage.type', 'bar')
            ->assertJsonPath('charts.rejection_stage.labels', ['Applied', 'Screening', 'Interview'])
            ->assertJsonPath('charts.rejection_stage.series.0.data', [1, 3, 3]);
    }

    public function test_rejected_tanpa_riwayat_masuk_lainnya(): void
    {
        $user = User::factory()->create();

        for ($n = 0; $n < 5; $n++) {
            $this->rejected($user, []);
        }

        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.rejection_stage.labels', ['Applied', 'Screening', 'Interview', 'Lainnya'])
            ->assertJsonPath('charts.rejection_stage.series.0.data', [0, 0, 0, 5]);
    }

    // ----- #24 keputusan offer -----

    public function test_keputusan_offer_kosong_dianggap_menunggu(): void
    {
        $user = User::factory()->create();

        foreach ([OfferDecision::Accepted, OfferDecision::Accepted, OfferDecision::Accepted,
            OfferDecision::Declined, OfferDecision::Pending, null] as $decision) {
            $this->offer($user, $decision);
        }

        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.offer_decision.status', 'ok')
            ->assertJsonPath('charts.offer_decision.type', 'doughnut')
            ->assertJsonPath('charts.offer_decision.labels', ['Diterima', 'Ditolak', 'Menunggu'])
            ->assertJsonPath('charts.offer_decision.series.0.data', [3, 1, 2]);
    }

    public function test_offer_butuh_minimal_lima_loker_offer(): void
    {
        $user = User::factory()->create();

        for ($n = 0; $n < 3; $n++) {
            $this->offer($user, OfferDecision::Accepted);
        }

        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('charts.offer_decision.status', 'insufficient')
            ->assertJsonPath('charts.offer_decision.current', 3)
            ->assertJsonPath('charts.offer_decision.min_required', 5);
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->seedRejected($other);
        for ($n = 0; $n < 5; $n++) {
            $this->offer($other, OfferDecision::Accepted);
        }

        $this->actingAs($user)
            ->getJson('/stats/G')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('charts.rejection_reasons.status', 'insufficient')
            ->assertJsonPath('charts.rejection_stage.status', 'insufficient')
            ->assertJsonPath('charts.offer_decision.status', 'insufficient');
    }
}