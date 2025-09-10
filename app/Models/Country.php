<?php
// app/Models/Country.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';
    public $timestamps = false;

    protected $fillable = [
        'name','iso3','iso2','numeric_code','phonecode','capital',
        'currency','currency_name','currency_symbol','tld','native',
        'region','region_id','subregion','subregion_id','nationality',
        'timezones','latitude','longitude','emoji','emojiU',
    ];

    protected $casts = [
        'region_id'     => 'integer',
        'subregion_id'  => 'integer',
        'latitude'      => 'float',
        'longitude'     => 'float',
        'timezones'     => 'array',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<State> */
    public function states(): HasMany
    {
        return $this->hasMany(State::class, 'country_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<CityDistrict> */
    public function cities(): HasMany
    {
        return $this->hasMany(CityDistrict::class, 'country_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Town> */
    public function towns(): HasMany
    {
        return $this->hasMany(Town::class, 'country_id');
    }

    # Scopes
    public function scopeCode2($q, string $iso2)
    {
        return $q->where('iso2', strtoupper($iso2));
    }

    public function scopeCode3($q, string $iso3)
    {
        return $q->where('iso3', strtoupper($iso3));
    }

    public function scopeInRegion($q, ?string $region = null)
    {
        return $region ? $q->where('region', $region) : $q;
    }

    public function scopeInSubregion($q, ?string $sub = null)
    {
        return $sub ? $q->where('subregion', $sub) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $t = trim($term);
        return $q->where(function ($qq) use ($t) {
            $qq->where('name', 'like', "%{$t}%")
               ->orWhere('iso2', 'like', "%{$t}%")
               ->orWhere('iso3', 'like', "%{$t}%")
               ->orWhere('native', 'like', "%{$t}%")
               ->orWhere('capital', 'like', "%{$t}%");
        });
    }

    # Normalizers (keep codes uppercase)
    protected function iso2(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ? strtoupper($v) : null);
    }

    protected function iso3(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ? strtoupper($v) : null);
    }
}
