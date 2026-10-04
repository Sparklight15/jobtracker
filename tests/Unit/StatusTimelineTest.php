<?php

namespace Tests\Unit;

use App\Enums\JobStatus;
use App\Models\StatusHistory;
use App\Support\StatusTimeline;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatusTimelineTest extends TestCase
{
    private function row(int $id, JobStatus $status, string $date): StatusHistory
    {
        $row = new StatusHistory(['status' => $status, 'changed_at' => $date]);
        $row->id = $id;

        return $row;
    }

    public function test_menghitung_lama_tiap_tahap(): void
    {
        $timeline = StatusTimeline::build([
            $this->row(1, JobStatus::Applied, '2026-09-01'),
            $this->row(2, JobStatus::Screening, '2026-09-05'),
            $this->row(3, JobStatus::Interview, '2026-09-12'),
        ], Carbon::parse('2026-09-20'));

        // Tahap terakhir (Interview) masih berjalan: dihitung sampai hari ini
        $this->assertSame([4, 7, 8], $timeline->pluck('days')->all());
        $this->assertSame([false, false, true], $timeline->pluck('isCurrent')->all());
    }

    public function test_status_final_tidak_dihitung_lamanya(): void
    {
        $timeline = StatusTimeline::build([
            $this->row(1, JobStatus::Applied, '2026-09-01'),
            $this->row(2, JobStatus::Rejected, '2026-09-10'),
        ], Carbon::parse('2026-09-20'));

        $this->assertSame([9, null], $timeline->pluck('days')->all());
    }

    public function test_urutan_mengikuti_tanggal_bukan_id(): void
    {
        // Data lama diinput belakangan: id lebih kecil tapi tanggal lebih baru
        $timeline = StatusTimeline::build([
            $this->row(1, JobStatus::Rejected, '2026-09-10'),
            $this->row(2, JobStatus::Applied, '2026-09-01'),
        ], Carbon::parse('2026-09-20'));

        $this->assertSame([JobStatus::Applied, JobStatus::Rejected], $timeline->pluck('status')->all());
        $this->assertSame([9, null], $timeline->pluck('days')->all());
    }

    public function test_tanggal_sama_diurutkan_dengan_id(): void
    {
        $timeline = StatusTimeline::build([
            $this->row(2, JobStatus::Screening, '2026-09-05'),
            $this->row(1, JobStatus::Applied, '2026-09-05'),
        ], Carbon::parse('2026-09-06'));

        $this->assertSame([JobStatus::Applied, JobStatus::Screening], $timeline->pluck('status')->all());
        $this->assertSame([0, 1], $timeline->pluck('days')->all());
    }

    public function test_riwayat_kosong_menghasilkan_koleksi_kosong(): void
    {
        $this->assertTrue(StatusTimeline::build([])->isEmpty());
    }
}