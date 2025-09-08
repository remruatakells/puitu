<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseVideos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class CourseVideoController extends Controller
{
    // GET /v1/courses/{course}/videos?section_id=&free_only=1
    public function index(Request $req, Course $course)
    {
        try {
            $q = $course->videos()->orderBy('position');

            if ($req->filled('section_id')) {
                $q->where('section_id', $req->integer('section_id'));
            }
            if ($req->boolean('free_only')) {
                $q->where('is_free_preview', 1);
            }

            $perPage = min(100, max(1, (int)$req->query('per_page', 50)));
            $data = $q->paginate($perPage)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Videos retrieved successfully',
                'data'    => $data->items(),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                    'last_page'    => $data->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve videos',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/videos
    public function store(Request $req, Course $course)
    {
        try {
            $validated = $req->validate([
                'section_id'       => ['nullable','integer','exists:course_sections,id'],
                'title'            => ['required','string','max:180'],
                'slug'             => ['nullable','string','max:200'],
                'description'      => ['nullable','string'],
                'playback_url'     => ['required','string','max:255'],
                'duration_seconds' => ['nullable','integer','min:0'],
                'size_bytes'       => ['nullable','integer','min:0'],
                'width'            => ['nullable','integer','min:0'],
                'height'           => ['nullable','integer','min:0'],
                'mime_type'        => ['nullable','string','max:60'],
                'captions_json'    => ['nullable','json'],
                'drm_license_url'  => ['nullable','string','max:255'],
                'is_free_preview'  => ['nullable','boolean'],
                'position'         => ['nullable','integer','min:0'],
            ]);

            $slug = $validated['slug'] ?? Str::slug($validated['title']);
            $slug = $this->uniqueVideoSlug($course->id, $slug);

            $position = $validated['position'] ?? ((($course->videos()->max('position')) ?? -1) + 1);

            $video = $course->videos()->create(array_merge($validated, [
                'slug'     => $slug,
                'position' => $position,
            ]));

            return response()->json([
                'status'  => 'success',
                'message' => 'Video created successfully',
                'data'    => $video,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create video',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}/videos/{video}
    public function show(Course $course, CourseVideos $video)
    {
        try {
            abort_unless($video->course_id === $course->id, 404);

            return response()->json([
                'status'  => 'success',
                'message' => 'Video retrieved successfully',
                'data'    => $video,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Video not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve video',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}/videos/{video}
    public function update(Request $req, Course $course, CourseVideos $video)
    {
        try {
            abort_unless($video->course_id === $course->id, 404);

            $validated = $req->validate([
                'section_id'       => ['sometimes','nullable','integer','exists:course_sections,id'],
                'title'            => ['sometimes','string','max:180'],
                'slug'             => ['nullable','string','max:200'],
                'description'      => ['nullable','string'],
                'playback_url'     => ['sometimes','string','max:255'],
                'duration_seconds' => ['nullable','integer','min:0'],
                'size_bytes'       => ['nullable','integer','min:0'],
                'width'            => ['nullable','integer','min:0'],
                'height'           => ['nullable','integer','min:0'],
                'mime_type'        => ['nullable','string','max:60'],
                'captions_json'    => ['nullable','json'],
                'drm_license_url'  => ['nullable','string','max:255'],
                'is_free_preview'  => ['nullable','boolean'],
                'position'         => ['nullable','integer','min:0'],
            ]);

            if (array_key_exists('title', $validated) && !array_key_exists('slug', $validated)) {
                $validated['slug'] = $this->uniqueVideoSlug($course->id, Str::slug($validated['title']), $video->id);
            } elseif (array_key_exists('slug', $validated) && $validated['slug']) {
                $validated['slug'] = $this->uniqueVideoSlug($course->id, $validated['slug'], $video->id);
            }

            $video->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Video updated successfully',
                'data'    => $video->refresh(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Video not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update video',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}/videos/{video}
    public function destroy(Course $course, CourseVideos $video)
    {
        try {
            abort_unless($video->course_id === $course->id, 404);

            $video->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Video deleted successfully',
                'data'    => null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Video not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete video',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueVideoSlug(int $courseId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $i = 0;
        do {
            $try = $i === 0 ? $slug : "{$slug}-{$i}";
            $exists = CourseVideos::where('course_id', $courseId)
                ->where('slug', $try)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if (!$exists) return $try;
            $i++;
        } while (true);
    }
}
