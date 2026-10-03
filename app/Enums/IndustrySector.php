<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IndustrySector: string
{
    use HasOptions;

    case Teknologi = 'Teknologi';
    case Manufaktur = 'Manufaktur';
    case Keuangan = 'Keuangan';
    case Kesehatan = 'Kesehatan';
    case Pendidikan = 'Pendidikan';
    case RetailFmcg = 'Retail/FMCG';
    case JasaKonsultan = 'Jasa/Konsultan';
    case MediaKreatif = 'Media/Kreatif';
    case Lainnya = 'Lainnya';

    public function label(): string
    {
        return $this->value;
    }
}