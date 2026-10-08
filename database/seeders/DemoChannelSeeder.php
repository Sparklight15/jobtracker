<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoChannelSeeder extends Seeder
{
    /** Naikkan kalau min_sample di config/stats.php lebih besar dari ini. */
    private const PER_CHANNEL = 5;

    /** Penanda supaya data dummy mudah dihapus lagi. */
    private const PREFIX = '[DEMO] ';

    public function run(): void
    {
        // Ganti kalau mau dipasang ke akun tertentu: User::where('email', '...')->firstOrFail()
        $user = User::query()->firstOrFail();

        foreach (Channel::cases() as $i => $channel) {
            $interviews = $i % 3;               // 0-2 lamaran sampai Interview
            $screenings = 1 + ($i % 2);         // 1-2 lamaran dapat respons saja

            for ($n = 0; $n < self::PER_CHANNEL; $n++) {
                $applied = Carbon::today()->subDays(3 + ($i * 2 + $n * 3) % 24);
                $responded = $applied->copy()->addDays(3);
                $interviewAt = $applied->copy()->addDays(7);

                if ($n < $interviews) {
                    $status = 'interview';
                    $firstResponse = $responded;
                    $path = [['screening', $responded], ['interview', $interviewAt]];
                } elseif ($n < $interviews + $screenings) {
                    $status = 'screening';
                    $firstResponse = $responded;
                    $path = [['screening', $responded]];
                } else {
                    $status = 'applied';
                    $firstResponse = null;
                    $path = [];
                }

                $isReferral = $channel === Channel::Referral;

                $job = $user->jobs()->create([
                    'company_name' => self::PREFIX.'PT '.$channel->label().' '.($n + 1),
                    'position' => 'Staff '.($n + 1),
                    'current_status' => $status,
                    'applied_date' => $applied->toDateString(),
                    'first_response_date' => $firstResponse?->toDateString(),
                    'channel' => $channel->value,
                    'has_referral' => $isReferral,
                    'referrer_name' => $isReferral ? 'Teman Demo' : null,
                ]);

                // Riwayat status: dipakai statistik untuk mengecek "pernah sampai Interview"
                $job->statusHistory()->create(['status' => 'applied', 'changed_at' => $applied]);

                foreach ($path as [$step, $date]) {
                    $job->statusHistory()->create(['status' => $step, 'changed_at' => $date]);
                }
            }
        }
    }
}