namespace Database\Seeders;

use App\Models\BenchmarkReference;
use Illuminate\Database\Seeder;

class BenchmarkReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'metric_key' => BenchmarkReference::JOB_SEARCH_DURATION_MONTHS,
                'sector' => null,
                'value' => 5.5, // 23 minggu
                'source' => 'Bureau of Labor Statistics (BLS)',
                'source_year' => 2025,
                'notes' => 'Rata-rata durasi pencarian kerja secara global.',
            ],
            [
                'metric_key' => BenchmarkReference::APPLICANTS_PER_JOB,
                'sector' => null,
                'value' => 150,
                'source' => 'NEOGOV & SHRM',
                'source_year' => 2024,
                'notes' => 'Rata-rata pelamar organik tervalidasi lintas sektor.',
            ],
            [
                'metric_key' => 'time_to_hire_days',
                'sector' => null,
                'value' => 43,
                'source' => 'Employ Recruiter Nation',
                'source_year' => 2024,
                'notes' => 'Rata-rata waktu proses dari melamar hingga offering.',
            ],
        ];

        foreach ($data as $row) {
            BenchmarkReference::updateOrCreate(
                ['metric_key' => $row['metric_key'], 'sector' => $row['sector']],
                $row
            );
        }
    }
}