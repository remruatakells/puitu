<?php
// app/Models/Category.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name','slug','description','position','is_active'];

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class)->orderBy('position');
    }
}
