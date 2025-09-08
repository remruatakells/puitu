<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $fillable = ['course_id','title','position'];
    public function course(){ return $this->belongsTo(Course::class); }
    public function videos(){ return $this->hasMany(CourseVideos::class, 'section_id'); }
    public function documents(){ return $this->hasMany(CourseDocuments::class, 'section_id'); }
    public function audios(){ return $this->hasMany(CourseAudios::class, 'section_id'); }
    public function images(){ return $this->hasMany(CourseImages::class, 'section_id'); }
}
