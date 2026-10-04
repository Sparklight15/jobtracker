<?php

namespace App\Support;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\IndustrySector;
use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Enums\WorkMode;
use Illuminate\Http\Request;

/**
 * Filter builder untuk List Loker.
 *
 * Format di URL (GET, jadi bisa di-bookmark dan ikut terbawa saat pagination):
 *   ?match=all|any
 *   &filter[0][field]=current_status&filter[0][op]=is&filter[0][value]=applied
 *   &filter[1][field]=applied_date&filter[1][op]=between&filter[1][value]=2026-09-01&filter[1][value2]=2026-09-30
 *
 * Semua input dicocokkan dengan whitelist (atribut, operator, nilai).
 * Baris yang tidak valid atau belum lengkap diabaikan, bukan error.
 */
class JobFilter
{
    public const MAX_RULES = 8;

    /** @var array<int, array{field: string, op: string, value: string|int|null, value2: string|int|null}> */
    private array $rules;

    private string $match;

    private static ?array $fields = null;

    private function __construct(array $rules, string $match)
    {
        $this->rules = $rules;
        $this->match = $match;
    }

    public static function fromRequest(Request $request): self
    {
        $raw = $request->query('filter', []);

        return new self(
            self::sanitize(is_array($raw) ? $raw : []),
            $request->query('match') === 'any' ? 'any' : 'all',
        );
    }

    /**
     * Atribut yang bisa difilter. type: text, enum, date, number, boolean, skill.
     */
    public static function fields(): array
    {
        return self::$fields ??= [
            'company_name' => ['label' => 'Perusahaan', 'type' => 'text'],
            'position' => ['label' => 'Posisi', 'type' => 'text'],
            'current_status' => ['label' => 'Status', 'type' => 'enum', 'options' => self::enumOptions(JobStatus::class)],
            'applied_date' => ['label' => 'Tanggal apply', 'type' => 'date'],
            'first_response_date' => ['label' => 'Tanggal respons pertama', 'type' => 'date'],
            'channel' => ['label' => 'Channel', 'type' => 'enum', 'options' => self::enumOptions(Channel::class)],
            'has_referral' => ['label' => 'Pakai referral', 'type' => 'boolean'],
            'referrer_name' => ['label' => 'Nama referrer', 'type' => 'text'],
            'industry_sector' => ['label' => 'Sektor industri', 'type' => 'enum', 'options' => self::enumOptions(IndustrySector::class)],
            'city' => ['label' => 'Kota', 'type' => 'text'],
            'work_mode' => ['label' => 'Mode kerja', 'type' => 'enum', 'options' => self::enumOptions(WorkMode::class)],
            'salary_min' => ['label' => 'Gaji minimum (Rp)', 'type' => 'number'],
            'salary_max' => ['label' => 'Gaji maksimum (Rp)', 'type' => 'number'],
            'salary_offered' => ['label' => 'Gaji ditawarkan (Rp)', 'type' => 'number'],
            'fit_score' => ['label' => 'Fit score (1-5)', 'type' => 'number'],
            'skill_match_score' => ['label' => 'Skill match (1-5)', 'type' => 'number'],
            'cv_customization' => ['label' => 'CV', 'type' => 'enum', 'options' => self::enumOptions(CvCustomization::class)],
            'rejection_reason' => ['label' => 'Alasan rejection', 'type' => 'enum', 'options' => self::enumOptions(RejectionReason::class)],
            'offer_decision' => ['label' => 'Keputusan offer', 'type' => 'enum', 'options' => self::enumOptions(OfferDecision::class)],
            'skill_gap' => ['label' => 'Skill yang kurang', 'type' => 'skill'],
            'job_url' => ['label' => 'Link lowongan', 'type' => 'text'],
            'notes' => ['label' => 'Catatan', 'type' => 'text'],
        ];
    }

    /**
     * Operator per tipe atribut. inputs = jumlah kolom nilai yang dibutuhkan.
     */
    public static function operators(): array
    {
        $empty = [
            ['value' => 'is_empty', 'label' => 'kosong', 'inputs' => 0],
            ['value' => 'is_not_empty', 'label' => 'tidak kosong', 'inputs' => 0],
        ];

        return [
            'text' => [
                ['value' => 'contains', 'label' => 'mengandung', 'inputs' => 1],
                ['value' => 'not_contains', 'label' => 'tidak mengandung', 'inputs' => 1],
                ['value' => 'equals', 'label' => 'sama dengan', 'inputs' => 1],
                ...$empty,
            ],
            'enum' => [
                ['value' => 'is', 'label' => 'adalah', 'inputs' => 1],
                ['value' => 'is_not', 'label' => 'bukan', 'inputs' => 1],
                ...$empty,
            ],
            'date' => [
                ['value' => 'on', 'label' => 'pada', 'inputs' => 1],
                ['value' => 'before', 'label' => 'sebelum', 'inputs' => 1],
                ['value' => 'after', 'label' => 'setelah', 'inputs' => 1],
                ['value' => 'between', 'label' => 'antara', 'inputs' => 2],
                ...$empty,
            ],
            'number' => [
                ['value' => 'eq', 'label' => 'sama dengan', 'inputs' => 1],
                ['value' => 'gte', 'label' => 'minimal', 'inputs' => 1],
                ['value' => 'lte', 'label' => 'maksimal', 'inputs' => 1],
                ['value' => 'between', 'label' => 'antara', 'inputs' => 2],
                ...$empty,
            ],
            'boolean' => [
                ['value' => 'is', 'label' => 'adalah', 'inputs' => 1],
            ],
            'skill' => [
                ['value' => 'contains', 'label' => 'ada yang mengandung', 'inputs' => 1],
                ['value' => 'not_contains', 'label' => 'tidak ada yang mengandung', 'inputs' => 1],
            ],
        ];
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function match(): string
    {
        return $this->match;
    }

    public function isActive(): bool
    {
        return $this->rules !== [];
    }

    /**
     * Terapkan semua kondisi ke query. Kondisi dibungkus satu grup kurung
     * supaya mode "salah satu" (OR) tidak merusak batasan lain di query
     * (terutama where user_id).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function apply($query): void
    {
        if ($this->rules === []) {
            return;
        }

        $boolean = $this->match === 'any' ? 'or' : 'and';

        $query->where(function ($group) use ($boolean) {
            foreach ($this->rules as $rule) {
                $group->where(
                    fn ($q) => $this->applyRule($q, $rule),
                    null,
                    null,
                    $boolean
                );
            }
        });
    }

    private function applyRule($q, array $rule): void
    {
        ['field' => $field, 'op' => $op, 'value' => $v, 'value2' => $v2] = $rule;
        $type = self::fields()[$field]['type'];

        // Relasi: skill yang kurang
        if ($type === 'skill') {
            $like = '%'.addcslashes((string) $v, '\\%_').'%';
            $constraint = fn ($s) => $s->where('skill_name', 'like', $like);

            if ($op === 'contains') {
                $q->whereHas('skillGaps', $constraint);
            } else {
                $q->whereDoesntHave('skillGaps', $constraint);
            }

            return;
        }

        // $field aman dipakai sebagai nama kolom: sudah lolos whitelist fields()
        switch ($op) {
            case 'contains':
                $q->where($field, 'like', '%'.addcslashes((string) $v, '\\%_').'%');
                break;

            case 'not_contains':
                $like = '%'.addcslashes((string) $v, '\\%_').'%';
                $q->where(fn ($w) => $w->whereNull($field)->orWhere($field, 'not like', $like));
                break;

            case 'equals':
            case 'eq':
                $q->where($field, $v);
                break;

            case 'is':
                $q->where($field, $type === 'boolean' ? (int) $v : $v);
                break;

            case 'is_not':
                $q->where(fn ($w) => $w->whereNull($field)->orWhere($field, '!=', $v));
                break;

            case 'is_empty':
                $q->where(function ($w) use ($field, $type) {
                    $w->whereNull($field);

                    if ($type === 'text') {
                        $w->orWhere($field, '');
                    }
                });
                break;

            case 'is_not_empty':
                $q->whereNotNull($field);

                if ($type === 'text') {
                    $q->where($field, '!=', '');
                }
                break;

            case 'on':
                $q->whereDate($field, $v);
                break;

            case 'before':
                $q->whereDate($field, '<', $v);
                break;

            case 'after':
                $q->whereDate($field, '>', $v);
                break;

            case 'gte':
                $q->where($field, '>=', $v);
                break;

            case 'lte':
                $q->where($field, '<=', $v);
                break;

            case 'between':
                $q->whereBetween($field, [$v, $v2]);
                break;
        }
    }

    /**
     * Bersihkan input mentah dari URL menjadi daftar kondisi yang valid.
     */
    private static function sanitize(array $raw): array
    {
        $fields = self::fields();
        $operators = self::operators();
        $rules = [];

        foreach (array_slice(array_values($raw), 0, self::MAX_RULES) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = $row['field'] ?? null;
            $op = $row['op'] ?? null;

            if (! is_string($field) || ! isset($fields[$field]) || ! is_string($op)) {
                continue;
            }

            $definition = $fields[$field];
            $meta = collect($operators[$definition['type']])->firstWhere('value', $op);

            if ($meta === null) {
                continue;
            }

            $inputs = $meta['inputs'];
            $value = $inputs >= 1 ? self::cleanValue($definition, $row['value'] ?? null) : null;
            $value2 = $inputs === 2 ? self::cleanValue($definition, $row['value2'] ?? null) : null;

            // Baris belum lengkap: lewati
            if (($inputs >= 1 && $value === null) || ($inputs === 2 && $value2 === null)) {
                continue;
            }

            // Rentang terbalik: tukar
            if ($inputs === 2 && $value > $value2) {
                [$value, $value2] = [$value2, $value];
            }

            $rules[] = [
                'field' => $field,
                'op' => $op,
                'value' => $value,
                'value2' => $value2,
            ];
        }

        return $rules;
    }

    private static function cleanValue(array $definition, mixed $raw): string|int|null
    {
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        return match ($definition['type']) {
            'text', 'skill' => mb_substr($raw, 0, 150),
            'enum' => in_array($raw, array_column($definition['options'], 'value'), true) ? $raw : null,
            'date' => self::isValidDate($raw) ? $raw : null,
            'number' => preg_match('/^\d{1,12}$/', $raw) === 1 ? (int) $raw : null,
            'boolean' => in_array($raw, ['0', '1'], true) ? $raw : null,
            default => null,
        };
    }

    private static function isValidDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function enumOptions(string $enumClass): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            $enumClass::cases()
        );
    }
}