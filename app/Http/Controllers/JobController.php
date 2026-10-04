<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Models\Job;
use App\Support\JobFilter;
use App\Support\JobStatusChanger;
use App\Support\StatusTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobController extends Controller
{
    private const PER_PAGE = 10;

    /** Kolom yang boleh dipakai untuk sorting (whitelist). */
    private const SORTABLE = [
        'company_name',
        'position',
        'current_status',
        'applied_date',
        'channel',
        'city',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $userId = $request->user()->id;

        // Kata kunci pencarian (perusahaan atau posisi)
        $keyword = $request->query('q');
        $search = is_string($keyword) ? mb_substr(trim($keyword), 0, 100) : '';

        // Sorting: hanya kolom whitelist. Default: tanggal apply terbaru di atas.
        $sort = $request->query('sort');
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'applied_date';

        $dir = $request->query('dir');
        $dir = in_array($dir, ['asc', 'desc'], true)
            ? $dir
            : ($sort === 'applied_date' ? 'desc' : 'asc');

        // Kondisi filter dari form (sudah dibersihkan dan divalidasi di JobFilter)
        $filter = JobFilter::fromRequest($request);

        // Semua query loker WAJIB berawal dari user_id pengguna yang login.
        $query = Job::query()->where('user_id', $userId);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '\\%_').'%';

            $query->where(function ($q) use ($like) {
                $q->where('company_name', 'like', $like)
                    ->orWhere('position', 'like', $like);
            });
        }

        // Kondisi filter dibungkus satu grup kurung, jadi mode "salah satu"
        // (OR) tidak pernah melepas batasan user_id di atas.
        $filter->apply($query);

        $jobs = $query
            ->orderBy($sort, $dir)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Halaman di luar jangkauan (mis. setelah filter dipersempit): lompat ke halaman terakhir
        if ($jobs->isEmpty() && $jobs->currentPage() > 1) {
            return redirect()->to(
                $request->fullUrlWithQuery(['page' => $jobs->lastPage()])
            );
        }

        $hasAnyJobs = $jobs->total() > 0
            || Job::query()->where('user_id', $userId)->exists();

        return view('jobs.index', [
            'jobs' => $jobs,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'hasAnyJobs' => $hasAnyJobs,
            'hasFilter' => $search !== '' || $filter->isActive(),
            'filterConfig' => [
                'fields' => JobFilter::fields(),
                'operators' => JobFilter::operators(),
                'rules' => $filter->rules(),
                'match' => $filter->match(),
                'max' => JobFilter::MAX_RULES,
            ],
        ]);
    }

    public function show(Request $request, Job $job): View
    {
        // Bukan pemilik: JobPolicy menghasilkan 404
        $this->authorize('view', $job);

        $job->load([
            'statusHistory' => fn ($q) => $q->orderBy('changed_at')->orderBy('id'),
            'skillGaps' => fn ($q) => $q->orderBy('skill_name'),
        ]);

        return view('jobs.show', [
            'job' => $job,
            'timeline' => StatusTimeline::build($job->statusHistory),
            'backUrl' => $this->backUrl($request, $job),
        ]);
    }

    public function updateStatus(UpdateJobStatusRequest $request, Job $job): RedirectResponse
    {
        // Otorisasi (404 untuk bukan pemilik) dan validasi sudah dikerjakan UpdateJobStatusRequest
        $data = $request->validated();
        $status = JobStatus::from($data['status']);

        JobStatusChanger::apply($job, $status, $data['changed_at'], $data);

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', "Status diubah menjadi {$status->label()}.");
    }

    public function destroy(Request $request, Job $job): RedirectResponse
    {
        // Bukan pemilik: JobPolicy menghasilkan 404
        $this->authorize('delete', $job);

        $position = $job->position;
        $company = $job->company_name;

        // Riwayat status ikut terhapus lewat cascadeOnDelete di migrasi
        $job->delete();

        // Balik ke List Loker dengan filter dan halaman terakhir (kalau ada)
        $listUrl = $request->session()->pull('jobs.list_url', route('jobs.index'));

        return redirect()
            ->to($listUrl)
            ->with('success', "Loker {$position} di {$company} dihapus.");
    }

    /**
     * Tombol kembali: kalau pengguna datang dari List Loker, filter, sorting, dan
     * halaman terakhirnya dibawa pulang. Alamat list disimpan di session supaya tetap
     * terbawa setelah ubah status (saat halaman detail dimuat ulang lewat redirect).
     */
    private function backUrl(Request $request, Job $job): string
    {
        $fallback = route('jobs.index');
        $previous = url()->previous($fallback);

        $parts = parse_url($previous);
        $sameHost = ($parts['host'] ?? null) === $request->getHost();
        $path = $parts['path'] ?? null;

        if ($sameHost && $path === parse_url($fallback, PHP_URL_PATH)) {
            $request->session()->put('jobs.list_url', $previous);

            return $previous;
        }

        // Datang dari halaman detail ini sendiri (setelah ubah status)
        if ($sameHost && $path === parse_url(route('jobs.show', $job), PHP_URL_PATH)) {
            return $request->session()->get('jobs.list_url', $fallback);
        }

        return $fallback;
    }
}