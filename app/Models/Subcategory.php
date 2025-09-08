<?php
// app/Models/Subcategory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    protected $fillable = ['category_id','name','slug','description','position','is_active'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // If you have Course model:
    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
