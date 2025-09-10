<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseChapter extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'description', 'position'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
