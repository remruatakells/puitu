<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseVideos extends Model
{
    protected $fillable = [
        'course_id','section_id','title','slug','description','playback_url',
        'duration_seconds','size_bytes','width','height','mime_type','captions_json',
        'drm_license_url','is_free_preview','position'
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
        'captions_json'   => 'array',
    ];

    public function course(){ return $this->belongsTo(Course::class); }
    public function section(){ return $this->belongsTo(CourseSection::class); }
}