<?php
// app/Http/Controllers/CategoryController.php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class CategoryController extends Controller
{
    // GET /v1/categories?q=&active=1&sort=position,-name&per_page=20
    public function index(Request $req)
    {
        try {
            $q = Category::query()
                ->withCount('subcategories')
                ->when($req->has('active'), fn($qq) => $qq->where('is_active', (int) $req->boolean('active')))
                ->when($req->filled('q'), function ($qq) use ($req) {
                    $term = '%'.trim($req->string('q')).'%';
                    $qq->where(function ($w) use ($term) {
                        $w->where('name','like',$term)
                          ->orWhere('slug','like',$term)
                          ->orWhere('description','like',$term);
                    });
                });

            if ($req->filled('sort')) {
                foreach (explode(',', $req->string('sort')) as $part) {
                    $part = trim($part);
                    $dir = str_starts_with($part, '-') ? 'desc' : 'asc';
                    $col = ltrim($part, '-');
                    if (in_array($col, ['name','slug','position','is_active','created_at','updated_at'])) {
                        $q->orderBy($col, $dir);
                    }
                }
            } else {
                $q->orderBy('position')->orderBy('name');
            }

            $per  = min(100, max(1, (int)$req->query('per_page', 20)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Categories retrieved successfully',
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
                'message' => 'Failed to retrieve categories',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/categories
    public function store(Request $req)
    {
        try {
            $v = $req->validate([
                'name'        => ['required','string','max:120'],
                'slug'        => ['nullable','string','max:140','unique:categories,slug'],
                'description' => ['nullable','string'],
                'position'    => ['nullable','integer','min:0'],
                'is_active'   => ['nullable','boolean'],
            ]);

            $v['slug'] = $v['slug'] ?? Str::slug($v['name']);
            // ensure global-unique slug
            if (Category::where('slug', $v['slug'])->exists()) {
                $base = $v['slug']; $i=1;
                while (Category::where('slug', $try = "{$base}-{$i}")->exists()) $i++;
                $v['slug'] = $try;
            }
            $v['position'] = $v['position'] ?? ((Category::max('position') ?? -1) + 1);

            $cat = Category::create($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Category created successfully',
                'data'    => $cat,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create category',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/categories/{category}
    public function show(Category $category)
    {
        try {
            return response()->json([
                'status'  => 'success',
                'message' => 'Category retrieved successfully',
                'data'    => $category->loadCount('subcategories'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve category',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/categories/{category}
    public function update(Request $req, Category $category)
    {
        try {
            $v = $req->validate([
                'name'        => ['sometimes','string','max:120'],
                'slug'        => ['nullable','string','max:140', Rule::unique('categories','slug')->ignore($category->id)],
                'description' => ['nullable','string'],
                'position'    => ['nullable','integer','min:0'],
                'is_active'   => ['nullable','boolean'],
            ]);

            if (array_key_exists('name',$v) && !array_key_exists('slug',$v)) {
                $candidate = Str::slug($v['name']);
                if (Category::where('slug',$candidate)->where('id','!=',$category->id)->exists()) {
                    $base = $candidate; $i=1;
                    while (Category::where('slug', $try="{$base}-{$i}")->where('id','!=',$category->id)->exists()) $i++;
                    $candidate = $try;
                }
                $v['slug'] = $candidate;
            }

            $category->update($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Category updated successfully',
                'data'    => $category->refresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update category',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/categories/{category}
    public function destroy(Category $category)
    {
        try {
            $category->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Category deleted successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete category',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/categories/reorder
    // Body: { "orders": [ {"id":1,"position":0}, {"id":3,"position":1} ] }
    public function reorder(Request $req)
    {
        try {
            $data = $req->validate([
                'orders' => ['required','array','min:1'],
                'orders.*.id' => ['required','integer','exists:categories,id'],
                'orders.*.position' => ['required','integer','min:0'],
            ]);

            foreach ($data['orders'] as $row) {
                Category::whereKey($row['id'])->update(['position'=>$row['position']]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Categories reordered successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reorder categories',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
