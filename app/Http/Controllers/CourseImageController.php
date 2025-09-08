<?php
// app/Http/Controllers/CourseImageController.php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseImages;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class CourseImageController extends Controller
{
    // GET /v1/courses/{course}/images?section_id=&free_only=1
    public function index(Request $req, Course $course)
    {
        try {
            $q = $course->images()->orderBy('position');
            if ($req->filled('section_id')) $q->where('section_id', $req->integer('section_id'));
            if ($req->boolean('free_only')) $q->where('is_free_preview', 1);

            $per  = min(100, max(1, (int)$req->query('per_page', 50)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Images retrieved successfully',
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
                'message' => 'Failed to retrieve images',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/images
    public function store(Request $req, Course $course)
    {
        try {
            $v = $req->validate([
                'section_id'      => ['nullable','integer','exists:course_sections,id'],
                'title'           => ['required','string','max:180'],
                'slug'            => ['nullable','string','max:200'],
                'description'     => ['nullable','string'],
                'image_url'       => ['required','string','max:255'],
                'width'           => ['nullable','integer','min:0'],
                'height'          => ['nullable','integer','min:0'],
                'size_bytes'      => ['nullable','integer','min:0'],
                'mime_type'       => ['nullable','string','max:60'],
                'is_free_preview' => ['nullable','boolean'],
                'position'        => ['nullable','integer','min:0'],
            ]);

            $slug        = $v['slug'] ?? Str::slug($v['title']);
            $v['slug']   = $this->uniqueSlug($course->id, $slug);
            $v['position'] = $v['position'] ?? ((($course->images()->max('position')) ?? -1) + 1);

            $img = $course->images()->create($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image created successfully',
                'data'    => $img,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}/images/{image}
    public function show(Course $course, CourseImages $image)
    {
        try {
            abort_unless($image->course_id === $course->id, 404);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image retrieved successfully',
                'data'    => $image,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Image not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}/images/{image}
    public function update(Request $req, Course $course, CourseImages $image)
    {
        try {
            abort_unless($image->course_id === $course->id, 404);

            $v = $req->validate([
                'section_id'      => ['sometimes','nullable','integer','exists:course_sections,id'],
                'title'           => ['sometimes','string','max:180'],
                'slug'            => ['nullable','string','max:200'],
                'description'     => ['nullable','string'],
                'image_url'       => ['sometimes','string','max:255'],
                'width'           => ['nullable','integer','min:0'],
                'height'          => ['nullable','integer','min:0'],
                'size_bytes'      => ['nullable','integer','min:0'],
                'mime_type'       => ['nullable','string','max:60'],
                'is_free_preview' => ['nullable','boolean'],
                'position'        => ['nullable','integer','min:0'],
            ]);

            if (array_key_exists('title', $v) && !array_key_exists('slug', $v)) {
                $v['slug'] = $this->uniqueSlug($course->id, Str::slug($v['title']), $image->id);
            } elseif (!empty($v['slug'])) {
                $v['slug'] = $this->uniqueSlug($course->id, $v['slug'], $image->id);
            }

            $image->update($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image updated successfully',
                'data'    => $image->refresh(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Image not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}/images/{image}
    public function destroy(Course $course, CourseImages $image)
    {
        try {
            abort_unless($image->course_id === $course->id, 404);

            $image->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Image deleted successfully',
                'data'    => null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Image not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueSlug(int $courseId, string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        for ($i = 0; ; $i++) {
            $try = $i ? "{$slug}-{$i}" : $slug;
            $exists = CourseImages::where('course_id', $courseId)
                ->where('slug', $try)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if (!$exists) return $try;
        }
    }
}
