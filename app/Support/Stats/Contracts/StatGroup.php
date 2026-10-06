<?php

namespace App\Support\Stats\Contracts;

use App\Support\Stats\StatsContext;

interface StatGroup
{
    /**
     * @return array{cards?: array<string, array>, charts?: array<string, array>}
     *              Isi pakai App\Support\Stats\Stat. Amplop (group, period, sample)
     *              ditambahkan oleh StatsService.
     */
    public function compute(StatsContext $context): array;
}
