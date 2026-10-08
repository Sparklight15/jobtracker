<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\StatusHistory;
use Illuminate\Console\Command;

class BackfillFirstResponse extends Command
{
    protected $signature = 'jobs:backfill-first-response';

    protected $description = 'Isi first_response_date dari status_history untuk loker yang masih kosong';

    /** Sama dengan JobStatusChanger::RESPONSE_STATUSES */
    private const RESPONSE_STATUSES = ['screening', 'interview', 'offer', 'rejected'];

    public function handle(): int
    {
        $updated = 0;

        Job::query()
            ->whereNull('first_response_date')
            ->chunkById(100, function ($jobs) use (&$updated) {
                foreach ($jobs as $job) {
                    $date = StatusHistory::query()
                        ->where('job_id', $job->id)
                        ->whereIn('status', self::RESPONSE_STATUSES)
                        ->min('changed_at');

                    if ($date) {
                        $job->update(['first_response_date' => $date]);
                        $updated++;
                    }
                }
            });

        $this->info("Selesai: {$updated} loker diisi first_response_date.");

        return self::SUCCESS;
    }
}