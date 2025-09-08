<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'subcategory_id','user_id','title','slug','summary','thumbnail_url','language',
        'level','is_premium','status','approved','price'
    ];

    protected $casts = [
        'is_premium'      => 'boolean',
        'approved'      => 'boolean',
    ];

    public function subcategory(){ return $this->belongsTo(Subcategory::class); }
    public function sections(){ return $this->hasMany(CourseSection::class)->orderBy('position'); }
    public function videos(){ return $this->hasMany(CourseVideos::class)->orderBy('position'); }
    public function documents(){ return $this->hasMany(CourseDocuments::class)->orderBy('position'); }
    public function audios(){ return $this->hasMany(CourseAudios::class)->orderBy('position'); }
    public function images(){ return $this->hasMany(CourseImages::class)->orderBy('position'); }
}
