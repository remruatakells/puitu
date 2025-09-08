<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\Request;
use Exception;

class CourseSectionController extends Controller
{
    // GET /v1/courses/{course}/sections
    public function index(Course $course)
    {
        try {
            $sections = $course->sections()->orderBy('position')->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sections retrieved successfully',
                'data'    => $sections,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve sections',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/sections
    public function store(Request $req, Course $course)
    {
        try {
            $validated = $req->validate([
                'title'    => ['required','string','max:160'],
                'position' => ['nullable','integer','min:0'],
            ]);

            $position = $validated['position'] ?? ((($course->sections()->max('position')) ?? -1) + 1);

            $section = $course->sections()->create([
                'title'    => $validated['title'],
                'position' => $position,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Section created successfully',
                'data'    => $section,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create section',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}/sections/{section}
    public function show(Course $course, CourseSection $section)
    {
        try {
            abort_unless($section->course_id === $course->id, 404);

            return response()->json([
                'status'  => 'success',
                'message' => 'Section retrieved successfully',
                'data'    => $section,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Handles the 404 from abort_unless
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve section',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}/sections/{section}
    public function update(Request $req, Course $course, CourseSection $section)
    {
        try {
            abort_unless($section->course_id === $course->id, 404);

            $validated = $req->validate([
                'title'    => ['sometimes','string','max:160'],
                'position' => ['nullable','integer','min:0'],
            ]);

            $section->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Section updated successfully',
                'data'    => $section->refresh(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update section',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}/sections/{section}
    public function destroy(Course $course, CourseSection $section)
    {
        try {
            abort_unless($section->course_id === $course->id, 404);

            $section->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Section deleted successfully',
                'data'    => null,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Section not found in this course',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete section',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses/{course}/sections/reorder
    // Body: { "orders": [ { "id": 12, "position": 0 }, { "id": 15, "position": 1 } ] }
    public function reorder(Request $req, Course $course)
    {
        try {
            $data = $req->validate([
                'orders' => ['required','array','min:1'],
                'orders.*.id' => ['required','integer','exists:course_sections,id'],
                'orders.*.position' => ['required','integer','min:0'],
            ]);

            $ids = collect($data['orders'])->pluck('id')->all();
            $sections = $course->sections()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                if (!isset($sections[$id])) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Section {$id} not in course",
                    ], 422);
                }
            }

            foreach ($data['orders'] as $row) {
                $sections[$row['id']]->update(['position' => $row['position']]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Sections reordered successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reorder sections',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
