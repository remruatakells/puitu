<?php
// app/Models/CreatorProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'marital_status',
        'occupation',
        'religion',
        'total_years_experience',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
