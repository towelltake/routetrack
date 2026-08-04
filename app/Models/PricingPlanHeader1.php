<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PricingPlanHeader1 extends Model
{
    protected $table = 'pricingplanheader1';
    protected $primaryKey = 'customerpricingkey';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'customerpricingkey',
        'description',
        'arbdescription',
        'type',
        'active',
        'country',
        'trac_country',
        'alternatecode',
        'division',
        'startday',
        'endday',
    ];

    protected static ?string $countryColumn = null;

    public function getCountryAttribute($value): mixed
    {
        if ($value !== null) {
            return $value;
        }

        return $this->attributes['trac_country'] ?? null;
    }

    public function setCountryAttribute($value): void
    {
        $column = self::countryColumn();

        $this->attributes[$column] = $value;

        if ($column !== 'country') {
            unset($this->attributes['country']);
        }
    }

    private static function countryColumn(): string
    {
        if (self::$countryColumn !== null) {
            return self::$countryColumn;
        }

        self::$countryColumn = Schema::hasColumn('pricingplanheader1', 'country') ? 'country' : 'trac_country';

        return self::$countryColumn;
    }
}
