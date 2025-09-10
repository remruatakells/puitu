<?php

namespace App\Http\Controllers;

use App\Models\{Course, CourseChapter};
use Illuminate\Http\Request;

class CourseChapterController extends Controller
{
    // List all chapters for a course
    public function index(Course $course)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $course->chapters()->orderBy('position')->get()
        ]);
    }

    // Create a new chapter under a course
    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'position'    => 'nullable|integer|min:1'
        ]);

        $chapter = $course->chapters()->create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Chapter created successfully',
            'data'    => $chapter
        ], 201);
    }

    // Show a single chapter
    public function show(Course $course, CourseChapter $chapter)
    {
        if ($chapter->course_id !== $course->id) {
            abort(404, 'Chapter not found for this course');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $chapter
        ]);
    }

    // Update a chapter
    public function update(Request $request, Course $course, CourseChapter $chapter)
    {
        if ($chapter->course_id !== $course->id) {
            abort(404, 'Chapter not found for this course');
        }

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'position'    => 'nullable|integer|min:1'
        ]);

        $chapter->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Chapter updated successfully',
            'data'    => $chapter
        ]);
    }

    // Delete a chapter
    public function destroy(Course $course, CourseChapter $chapter)
    {
        if ($chapter->course_id !== $course->id) {
            abort(404, 'Chapter not found for this course');
        }

        $chapter->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Chapter deleted successfully'
        ]);
    }
}
