<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityDistrict extends Model
{
    protected $table = 'cities_districts';
    public $timestamps = false;
    protected $fillable = [
        'country_id','state_id','name','type','city_code','latitude','longitude','wikidata_id'
    ];

    public function country() { return $this->belongsTo(Country::class, 'country_id'); }
    public function state()   { return $this->belongsTo(State::class, 'state_id'); }
    public function towns()   { return $this->hasMany(Town::class, 'city_district_id'); }
}
