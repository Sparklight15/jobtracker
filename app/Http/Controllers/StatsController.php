<?php

namespace App\Http\Controllers;

use App\Support\Stats\StatsPeriod;
use App\Support\Stats\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    /** Halaman Beranda: kerangka section. Datanya dimuat lazy lewat show(). */
    public function index(Request $request): View
    {
        $groups = collect(config('stats.groups'))->map(
            fn (array $group, string $key) => $group + ['available' => $this->stats->has($key)]
        );

        return view('beranda', [
            'period' => StatsPeriod::fromRequest($request->query('period')),
            'periods' => StatsPeriod::options(),
            'groups' => $groups,
        ]);
    }

    /** JSON satu grup, selalu terscope ke user yang login. */
    public function show(Request $request, string $group): JsonResponse
    {
        $data = $this->stats->forGroup(
            $group,
            $request->user(),
            StatsPeriod::fromRequest($request->query('period')),
        );

        abort_if($data === null, 404);

        return response()->json($data);
    }
}