<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $table = 'states';
    public $timestamps = false;
    protected $fillable = ['country_id','name','type','state_code','iso2'];

    public function country() { return $this->belongsTo(Country::class, 'country_id'); }
    public function cities()  { return $this->hasMany(CityDistrict::class, 'state_id'); }
    public function towns()   { return $this->hasMany(Town::class, 'state_id'); }
}
