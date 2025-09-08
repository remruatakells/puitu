<?php
// app/Models/CourseAudio.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAudios extends Model
{
    protected $fillable = [
        'course_id','section_id','title','slug','description','playback_url',
        'duration_seconds','size_bytes','mime_type','language','is_free_preview','position'
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
    ];

    public function course(){ return $this->belongsTo(Course::class); }
    public function section(){ return $this->belongsTo(CourseSection::class); }
}
