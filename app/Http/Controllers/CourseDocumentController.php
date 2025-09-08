<?php
// app/Http/Controllers/CourseDocumentController.php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class CourseDocumentController extends Controller
{
    // GET /v1/courses/{course}/documents?section_id=&free_only=1
    public function index(Request $req, Course $course)
    {
        try {
            $q = $course->documents()->orderBy('position');

            if ($req->filled('section_id')) $q->where('section_id', $req->integer('section_id'));
            if ($req->boolean('free_only')) $q->where('is_free_preview', 1);

            $per  = min(100, max(1, (int)$req->query('per_page', 50)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Documents retrieved successfully',
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
                'message' => 'Failed to retrieve documents',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/documents
    public function store(Request $req, Course $course)
    {
        try {
            $v = $req->validate([
                'section_id'      => ['nullable','integer','exists:course_sections,id'],
                'title'           => ['required','string','max:180'],
                'slug'            => ['nullable','string','max:200'],
                'description'     => ['nullable','string'],
                'file_url'        => ['required','string','max:255'],
                'pages'           => ['nullable','integer','min:0'],
                'size_bytes'      => ['nullable','integer','min:0'],
                'mime_type'       => ['nullable','string','max:60'],
                'language'        => ['nullable','string','max:40'],
                'is_free_preview' => ['nullable','boolean'],
                'position'        => ['nullable','integer','min:0'],
            ]);

            $slug        = $v['slug'] ?? Str::slug($v['title']);
            $v['slug']   = $this->uniqueSlug($course->id, $slug);
            $v['position'] = $v['position'] ?? ((($course->documents()->max('position')) ?? -1) + 1);

            $doc = $course->documents()->create($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Document created successfully',
                'data'    => $doc,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create document',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}/documents/{document}
    public function show(Course $course, CourseDocuments $document)
    {
        try {
            abort_unless($document->course_id === $course->id, 404);

            return response()->json([
                'status'  => 'success',
                'message' => 'Document retrieved successfully',
                'data'    => $document,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Document not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve document',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}/documents/{document}
    public function update(Request $req, Course $course, CourseDocuments $document)
    {
        try {
            abort_unless($document->course_id === $course->id, 404);

            $v = $req->validate([
                'section_id'      => ['sometimes','nullable','integer','exists:course_sections,id'],
                'title'           => ['sometimes','string','max:180'],
                'slug'            => ['nullable','string','max:200'],
                'description'     => ['nullable','string'],
                'file_url'        => ['sometimes','string','max:255'],
                'pages'           => ['nullable','integer','min:0'],
                'size_bytes'      => ['nullable','integer','min:0'],
                'mime_type'       => ['nullable','string','max:60'],
                'language'        => ['nullable','string','max:40'],
                'is_free_preview' => ['nullable','boolean'],
                'position'        => ['nullable','integer','min:0'],
            ]);

            if (array_key_exists('title', $v) && !array_key_exists('slug', $v)) {
                $v['slug'] = $this->uniqueSlug($course->id, Str::slug($v['title']), $document->id);
            } elseif (!empty($v['slug'])) {
                $v['slug'] = $this->uniqueSlug($course->id, $v['slug'], $document->id);
            }

            $document->update($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Document updated successfully',
                'data'    => $document->refresh(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Document not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update document',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}/documents/{document}
    public function destroy(Course $course, CourseDocuments $document)
    {
        try {
            abort_unless($document->course_id === $course->id, 404);

            $document->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Document deleted successfully',
                'data'    => null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Document not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete document',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueSlug(int $courseId, string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        for ($i = 0; ; $i++) {
            $try = $i ? "{$slug}-{$i}" : $slug;
            $exists = CourseDocuments::where('course_id', $courseId)
                ->where('slug', $try)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if (!$exists) return $try;
        }
    }
}
