<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutProspect: string
{
    case AContacter = 'a_contacter';
    case Contacte = 'contacte';
    case ARelancer = 'a_relancer';
    case Interesse = 'interesse';
    case Client = 'client';
    case PasInteresse = 'pas_interesse';

    public function label(): string
    {
        return match ($this) {
            self::AContacter => 'À contacter',
            self::Contacte => 'Contacté',
            self::ARelancer => 'À relancer',
            self::Interesse => 'Intéressé',
            self::Client => 'Client',
            self::PasInteresse => 'Refusé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AContacter => 'secondary',
            self::Contacte => 'primary',
            self::ARelancer => 'warning',
            self::Interesse => 'info',
            self::Client => 'success',
            self::PasInteresse => 'danger',
        };
    }
}
