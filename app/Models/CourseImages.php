<?php
// app/Models/CourseImage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseImages extends Model
{
    protected $fillable = [
        'course_id','section_id','title','slug','description','image_url',
        'width','height','size_bytes','mime_type','is_free_preview','position'
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
    ];

    public function course(){ return $this->belongsTo(Course::class); }
    public function section(){ return $this->belongsTo(CourseSection::class); }
}
