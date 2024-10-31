<?php

namespace IpCountryDetector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, string $string1, int $ipLong)
 * @method static insertOrIgnore(array $batch)
 */
class IpCountry extends Model
{
    protected $table = 'ip_country';

    protected $fillable = [
        'first_ip',
        'last_ip',
        'country',
    ];

    public $timestamps = true;
}
