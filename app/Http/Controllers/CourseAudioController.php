<?php
// app/Http/Controllers/CourseAudioController.php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseAudios;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class CourseAudioController extends Controller
{
    // GET /v1/courses/{course}/audios?section_id=&free_only=1
    public function index(Request $req, Course $course)
    {
        try {
            $q = $course->audios()->orderBy('position');

            if ($req->filled('section_id')) $q->where('section_id', $req->integer('section_id'));
            if ($req->boolean('free_only')) $q->where('is_free_preview', 1);

            $per  = min(100, max(1, (int)$req->query('per_page', 50)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Audios retrieved successfully',
                'data'    => $page->items(),
                'meta'    => [
                    'current_page' => $page->currentPage(),
                    'per_page'     => $page->perPage(),
                    'total'        => $page->total(),
                    'last_page'    => $page->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve audios',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/audios
    public function store(Request $req, Course $course)
    {
        try {
            $v = $req->validate([
                'section_id'       => ['nullable','integer','exists:course_sections,id'],
                'title'            => ['required','string','max:180'],
                'slug'             => ['nullable','string','max:200'],
                'description'      => ['nullable','string'],
                'playback_url'     => ['required','string','max:255'],
                'duration_seconds' => ['nullable','integer','min:0'],
                'size_bytes'       => ['nullable','integer','min:0'],
                'mime_type'        => ['nullable','string','max:60'],
                'language'         => ['nullable','string','max:40'],
                'is_free_preview'  => ['nullable','boolean'],
                'position'         => ['nullable','integer','min:0'],
            ]);

            $slug        = $v['slug'] ?? Str::slug($v['title']);
            $v['slug']   = $this->uniqueSlug($course->id, $slug);
            $v['position'] = $v['position'] ?? ((($course->audios()->max('position')) ?? -1) + 1);

            $audio = $course->audios()->create($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Audio created successfully',
                'data'    => $audio,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create audio',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}/audios/{audio}
    public function show(Course $course, CourseAudios $audio)
    {
        try {
            abort_unless($audio->course_id === $course->id, 404);

            return response()->json([
                'status'  => 'success',
                'message' => 'Audio retrieved successfully',
                'data'    => $audio,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Audio not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve audio',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}/audios/{audio}
    public function update(Request $req, Course $course, CourseAudios $audio)
    {
        try {
            abort_unless($audio->course_id === $course->id, 404);

            $v = $req->validate([
                'section_id'       => ['sometimes','nullable','integer','exists:course_sections,id'],
                'title'            => ['sometimes','string','max:180'],
                'slug'             => ['nullable','string','max:200'],
                'description'      => ['nullable','string'],
                'playback_url'     => ['sometimes','string','max:255'],
                'duration_seconds' => ['nullable','integer','min:0'],
                'size_bytes'       => ['nullable','integer','min:0'],
                'mime_type'        => ['nullable','string','max:60'],
                'language'         => ['nullable','string','max:40'],
                'is_free_preview'  => ['nullable','boolean'],
                'position'         => ['nullable','integer','min:0'],
            ]);

            if (array_key_exists('title', $v) && !array_key_exists('slug', $v)) {
                $v['slug'] = $this->uniqueSlug($course->id, Str::slug($v['title']), $audio->id);
            } elseif (!empty($v['slug'])) {
                $v['slug'] = $this->uniqueSlug($course->id, $v['slug'], $audio->id);
            }

            $audio->update($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Audio updated successfully',
                'data'    => $audio->refresh(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Audio not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update audio',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}/audios/{audio}
    public function destroy(Course $course, CourseAudios $audio)
    {
        try {
            abort_unless($audio->course_id === $course->id, 404);

            $audio->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Audio deleted successfully',
                'data'    => null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Audio not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete audio',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueSlug(int $courseId, string $base, ?int $ignoreId=null): string
    {
        $slug = Str::slug($base);
        for ($i=0;;$i++) {
            $try = $i ? "{$slug}-{$i}" : $slug;
            $exists = CourseAudios::where('course_id',$courseId)
                ->where('slug',$try)
                ->when($ignoreId, fn($q) => $q->where('id','!=',$ignoreId))
                ->exists();
            if (!$exists) return $try;
        }
    }
}
