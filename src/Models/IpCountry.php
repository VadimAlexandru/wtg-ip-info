<?php

namespace IpCountryDetector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, string $string1, int $ipLong)
 * @method static insertOrIgnore(array $batch)
 */
class IpCountry extends Model
{
    public const TABLE = 'ip_country';

    protected $table = self::TABLE;

    protected $fillable = [
        'first_ip',
        'last_ip',
        'country',
    ];

    public $timestamps = true;
}
