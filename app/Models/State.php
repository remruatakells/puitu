<?php
// app/Models/State.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class State extends Model
{
    protected $table = 'states';
    public $timestamps = false;

    protected $fillable = [
        'name','country_id','country_code','country_name','iso2','iso3166_2',
        'fips_code','type','level','parent_id','latitude','longitude','timezone',
    ];

    protected $casts = [
        'country_id' => 'integer',
        'parent_id'  => 'integer',
        'level'      => 'integer',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    /** @return BelongsTo<Country, State> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /** @return BelongsTo<State, State> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(State::class, 'parent_id');
    }

    /** @return HasMany<State> */
    public function children(): HasMany
    {
        return $this->hasMany(State::class, 'parent_id');
    }

    /** @return HasMany<CityDistrict> */
    public function cities(): HasMany
    {
        return $this->hasMany(CityDistrict::class, 'state_id');
    }

    /** @return HasMany<Town> */
    public function towns(): HasMany
    {
        return $this->hasMany(Town::class, 'state_id');
    }

    # Scopes
    public function scopeInCountry($q, int|string $country)
    {
        return is_numeric($country)
            ? $q->where('country_id', (int)$country)
            : $q->where('country_code', strtoupper((string)$country));
    }

    public function scopeCode($q, ?string $iso2 = null)
    {
        return $iso2 ? $q->where('iso2', strtoupper($iso2)) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $t = trim($term);
        return $q->where(function ($qq) use ($t) {
            $qq->where('name', 'like', "%{$t}%")
               ->orWhere('iso2', 'like', "%{$t}%")
               ->orWhere('iso3166_2', 'like', "%{$t}%")
               ->orWhere('type', 'like', "%{$t}%");
        });
    }

    # Normalizers
    protected function countryCode(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ? strtoupper($v) : null);
    }

    protected function iso2(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ? strtoupper($v) : null);
    }

    protected function iso3166_2(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v ? strtoupper($v) : null);
    }
}
