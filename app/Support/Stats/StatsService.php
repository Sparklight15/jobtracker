<?php

namespace App\Support\Stats;

use App\Models\User;
use App\Support\Stats\Contracts\StatGroup;
use Carbon\CarbonImmutable;

/**
 * Melayani /stats/{group}.
 *
 * Grup tidak perlu didaftarkan manual: huruf grup (A-Z) dipetakan ke kelas
 * App\Support\Stats\Groups\Group{huruf}. Sebuah grup aktif jika:
 * 1. hurufnya ada di config('stats.groups') (judul menu dan section Beranda), dan
 * 2. kelasnya ada dan mengimplementasi StatGroup.
 * Grup yang kelasnya belum dibuat otomatis dianggap belum tersedia (404 / tidak available).
 */
class StatsService
{
    public function has(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    /** null = grup tidak dikenal (controller membalas 404). */
    public function forGroup(
        string $key,
        User $user,
        StatsPeriod $period,
        ?CarbonImmutable $today = null,
    ): ?array {
        $class = $this->resolve($key);

        if ($class === null) {
            return null;
        }

        $context = new StatsContext($user, $period, $today ?? CarbonImmutable::today());
        $result = app($class)->compute($context);

        return [
            'group' => strtoupper($key),
            'period' => $period->value,
            'sample' => $context->jobs()->count(),
            'cards' => $result['cards'] ?? [],
            'charts' => $result['charts'] ?? [],
        ];
    }

    /** @return class-string<StatGroup>|null */
    private function resolve(string $key): ?string
    {
        $key = strtoupper($key);

        if (! preg_match('/^[A-Z]$/', $key) || ! array_key_exists($key, config('stats.groups', []))) {
            return null;
        }

        $class = __NAMESPACE__.'\\Groups\\Group'.$key;

        return class_exists($class) && is_subclass_of($class, StatGroup::class)
            ? $class
            : null;
    }
}