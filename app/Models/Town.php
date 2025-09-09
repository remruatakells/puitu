<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Town extends Model
{
    protected $table = 'towns';
    public $timestamps = false;
    protected $fillable = [
        'country_id','state_id','city_district_id','name','latitude','longitude','wikidata_id','population'
    ];

    public function country() { return $this->belongsTo(Country::class, 'country_id'); }
    public function state()   { return $this->belongsTo(State::class, 'state_id'); }
    public function city()    { return $this->belongsTo(CityDistrict::class, 'city_district_id'); }
}
