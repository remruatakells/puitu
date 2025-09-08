<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // your table is literally named `user` (singular)
    protected $table = 'user';

    // PK is varchar, not auto-increment
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    // your table (per screenshot) doesn’t have created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'phone',
        'dob',
        'country_code',
        'country',
        'state',
        'district',
        'town',
        'profile_image',
    ];

    public function creatorProfile()
    {
        return $this->hasOne(CreatorProfile::class, 'user_id', 'id');
    }
}
