<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    public $timestamps = false;
    protected $fillable = [
        'name','official_name','iso2','iso3','numeric_code','region','subregion','is_un_member'
    ];

    public function states() { return $this->hasMany(State::class, 'country_id'); }
    public function cities() { return $this->hasMany(CityDistrict::class, 'country_id'); }
    public function towns()  { return $this->hasMany(Town::class, 'country_id'); }
}
