<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class CourseController extends Controller
{
    // GET /v1/courses?subcategory_id=&category_id=&q=&status=&approved=&is_premium=&language=&level=&sort=created_at,-title&with=sections,videos
    public function index(Request $req)
    {
        try {
            $q = Course::query()
                ->withCount(['sections'])
                ->when($req->boolean('with_counts', true), fn($q) =>
                    $q->withCount(['videos','documents','audios','images'])
                )
                ->when($req->filled('with'), function ($q) use ($req) {
                    $withs = collect(explode(',', $req->string('with')))->map(fn($w) => trim($w))->all();
                    $q->with($withs);
                })
                ->when($req->filled('subcategory_id'), fn($q2) => $q2->where('subcategory_id', $req->integer('subcategory_id')))
                ->when($req->filled('category_id'), function ($q2) use ($req) {
                    $q2->whereHas('subcategory', function ($qq) use ($req) {
                        $qq->where('category_id', $req->integer('category_id'));
                    });
                })
                ->when($req->filled('status'), fn($q2) => $q2->where('status', $req->string('status')))
                ->when($req->filled('approved'), fn($q2) => $q2->where('approved', (int) $req->boolean('approved')))
                ->when($req->filled('is_premium'), fn($q2) => $q2->where('is_premium', (int) $req->boolean('is_premium')))
                ->when($req->filled('language'), fn($q2) => $q2->where('language', $req->string('language')))
                ->when($req->filled('level'), fn($q2) => $q2->where('level', $req->string('level')))
                ->when($req->filled('q'), function ($q2) use ($req) {
                    $term = '%'.trim($req->string('q')).'%';
                    $q2->where(function ($qq) use ($term) {
                        $qq->where('title', 'like', $term)
                           ->orWhere('summary', 'like', $term);
                    });
                });

            // Multi-sort: ?sort=created_at,-title
            if ($req->filled('sort')) {
                foreach (explode(',', $req->string('sort')) as $part) {
                    $part = trim($part);
                    if ($part === '') continue;
                    $dir = Str::startsWith($part, '-') ? 'desc' : 'asc';
                    $col = ltrim($part, '-');
                    if (in_array($col, ['title','created_at','updated_at','status','approved','price'])) {
                        $q->orderBy($col, $dir);
                    }
                }
            } else {
                $q->orderByDesc('created_at');
            }

            $perPage = min(100, max(1, (int)$req->query('per_page', 20)));
            $data = $q->paginate($perPage)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Courses retrieved successfully',
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
                'message' => 'Failed to retrieve courses',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/courses
    public function store(Request $req)
    {
        try {
            $validated = $req->validate([
                'subcategory_id' => ['required','integer','exists:subcategories,id'],
                'user_id'        => ['required','string','max:64','exists:users,id'],
                'title'          => ['required','string','max:180'],
                'slug'           => ['nullable','string','max:200'],
                'summary'        => ['nullable','string'],
                'thumbnail_url'  => ['nullable','string','max:255'],
                'language'       => ['nullable','string','max:40'],
                'level'          => ['nullable','string','max:40'],
                'is_premium'     => ['nullable','boolean'],
                'status'         => ['nullable', Rule::in(['draft','published','archived'])],
                'approved'       => ['nullable','boolean'],
                'price'          => ['nullable','numeric','min:0'],
            ]);

            // Ensure unique slug within subcategory
            $slug = $validated['slug'] ?? Str::slug($validated['title']);
            $slug = $this->uniqueCourseSlug($validated['subcategory_id'], $slug);

            $course = Course::create(array_merge($validated, ['slug' => $slug]));

            return response()->json([
                'status'  => 'success',
                'message' => 'Course created successfully',
                'data'    => $course,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/courses/{course}?with=sections,videos
    public function show(Course $course, Request $req)
    {
        try {
            if ($req->filled('with')) {
                $withs = collect(explode(',', $req->string('with')))->map(fn($w) => trim($w))->all();
                $course->load($withs);
            }
            $course->loadCount(['sections','videos','documents','audios','images']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Course retrieved successfully',
                'data'    => $course,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/courses/{course}
    public function update(Request $req, Course $course)
    {
        try {
            $validated = $req->validate([
                'subcategory_id' => ['sometimes','integer','exists:subcategories,id'],
                'user_id'        => ['sometimes','string','max:64','exists:users,id'],
                'title'          => ['sometimes','string','max:180'],
                'slug'           => ['nullable','string','max:200'],
                'summary'        => ['nullable','string'],
                'thumbnail_url'  => ['nullable','string','max:255'],
                'language'       => ['nullable','string','max:40'],
                'level'          => ['nullable','string','max:40'],
                'is_premium'     => ['nullable','boolean'],
                'status'         => ['nullable', Rule::in(['draft','published','archived'])],
                'approved'       => ['nullable','boolean'],
                'price'          => ['nullable','numeric','min:0'],
            ]);

            if (array_key_exists('title', $validated) && !array_key_exists('slug', $validated)) {
                // If title changes and slug not explicitly set, refresh slug uniqueness
                $newSlug = Str::slug($validated['title']);
                $validated['slug'] = $this->uniqueCourseSlug($validated['subcategory_id'] ?? $course->subcategory_id, $newSlug, $course->id);
            } elseif (array_key_exists('slug', $validated) && $validated['slug']) {
                $validated['slug'] = $this->uniqueCourseSlug($validated['subcategory_id'] ?? $course->subcategory_id, $validated['slug'], $course->id);
            }

            $course->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Course updated successfully',
                'data'    => $course->refresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/courses/{course}
    public function destroy(Course $course)
    {
        try {
            $course->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Course deleted successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueCourseSlug(int $subcategoryId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $i = 0;
        do {
            $try = $i === 0 ? $slug : "{$slug}-{$i}";
            $exists = Course::where('subcategory_id', $subcategoryId)
                ->where('slug', $try)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if (!$exists) return $try;
            $i++;
        } while (true);
    }
}
