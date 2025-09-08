<?php
// app/Models/CourseDocument.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseDocuments extends Model
{
    protected $fillable = [
        'course_id','section_id','title','slug','description','file_url',
        'pages','size_bytes','mime_type','language','is_free_preview','position'
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
    ];

    public function course(){ return $this->belongsTo(Course::class); }
    public function section(){ return $this->belongsTo(CourseSection::class); }
}
