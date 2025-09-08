<?php
// app/Http/Controllers/SubcategoryController.php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class SubcategoryController extends Controller
{
    // GET /v1/subcategories?category_id=&q=&active=1&sort=position,-name&per_page=20
    public function index(Request $req)
    {
        try {
            $q = Subcategory::query()
                ->with('category')
                ->when($req->filled('category_id'), fn($qq) => $qq->where('category_id', (int)$req->query('category_id')))
                ->when($req->has('active'), fn($qq) => $qq->where('is_active', (int)$req->boolean('active')))
                ->when($req->filled('q'), function ($qq) use ($req) {
                    $term = '%'.trim($req->string('q')).'%';
                    $qq->where(fn($w) =>
                        $w->where('name','like',$term)
                          ->orWhere('slug','like',$term)
                          ->orWhere('description','like',$term)
                    );
                });

            if ($req->filled('sort')) {
                foreach (explode(',', $req->string('sort')) as $part) {
                    $dir = str_starts_with($part,'-') ? 'desc' : 'asc';
                    $col = ltrim($part,'-');
                    if (in_array($col,['name','slug','position','is_active','created_at','updated_at'])) {
                        $q->orderBy($col,$dir);
                    }
                }
            } else {
                $q->orderBy('position')->orderBy('name');
            }

            $per  = min(100, max(1, (int)$req->query('per_page', 20)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategories retrieved successfully',
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
                'message' => 'Failed to retrieve subcategories',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/categories/{category}/subcategories
    public function listByCategory(Category $category, Request $req)
    {
        try {
            $q = $category->subcategories()->newQuery();

            if ($req->has('active')) $q->where('is_active',(int)$req->boolean('active'));
            if ($req->filled('q')) {
                $term = '%'.trim($req->string('q')).'%';
                $q->where(fn($w)=>
                    $w->where('name','like',$term)
                      ->orWhere('slug','like',$term)
                      ->orWhere('description','like',$term)
                );
            }

            $q->orderBy('position')->orderBy('name');

            $per  = min(100, max(1, (int)$req->query('per_page', 50)));
            $page = $q->paginate($per)->appends($req->query());

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategories retrieved successfully',
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
                'message' => 'Failed to retrieve subcategories for category',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/categories/{category}/subcategories
    public function store(Request $req, Category $category)
    {
        try {
            $v = $req->validate([
                'name'        => ['required','string','max:120'],
                'slug'        => ['nullable','string','max:160'],
                'description' => ['nullable','string'],
                'position'    => ['nullable','integer','min:0'],
                'is_active'   => ['nullable','boolean'],
            ]);

            $slug        = $v['slug'] ?? Str::slug($v['name']);
            $v['slug']   = $this->uniqueSlug($category->id, $slug);
            $v['position'] = $v['position'] ?? (($category->subcategories()->max('position') ?? -1) + 1);

            $sub = $category->subcategories()->create($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategory created successfully',
                'data'    => $sub,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create subcategory',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/subcategories/{subcategory}
    public function show(Subcategory $subcategory)
    {
        try {
            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategory retrieved successfully',
                'data'    => $subcategory->load('category'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve subcategory',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/subcategories/{subcategory}
    public function update(Request $req, Subcategory $subcategory)
    {
        try {
            $v = $req->validate([
                'category_id' => ['sometimes','integer','exists:categories,id'],
                'name'        => ['sometimes','string','max:120'],
                'slug'        => ['nullable','string','max:160'],
                'description' => ['nullable','string'],
                'position'    => ['nullable','integer','min:0'],
                'is_active'   => ['nullable','boolean'],
            ]);

            $categoryId = $v['category_id'] ?? $subcategory->category_id;

            if (array_key_exists('name',$v) && !array_key_exists('slug',$v)) {
                $v['slug'] = $this->uniqueSlug($categoryId, Str::slug($v['name']), $subcategory->id);
            } elseif (!empty($v['slug'])) {
                $v['slug'] = $this->uniqueSlug($categoryId, $v['slug'], $subcategory->id);
            }

            $subcategory->update($v);

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategory updated successfully',
                'data'    => $subcategory->refresh()->load('category'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update subcategory',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/subcategories/{subcategory}
    public function destroy(Subcategory $subcategory)
    {
        try {
            $subcategory->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategory deleted successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete subcategory',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // POST /v1/categories/{category}/subcategories/reorder
    // Body: { "orders": [ {"id":10,"position":0}, {"id":12,"position":1} ] }
    public function reorder(Request $req, Category $category)
    {
        try {
            $data = $req->validate([
                'orders' => ['required','array','min:1'],
                'orders.*.id' => ['required','integer','exists:subcategories,id'],
                'orders.*.position' => ['required','integer','min:0'],
            ]);

            $ids  = collect($data['orders'])->pluck('id')->all();
            $subs = $category->subcategories()->whereIn('id',$ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                if (!isset($subs[$id])) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Subcategory {$id} not in this category",
                    ], 422);
                }
            }

            foreach ($data['orders'] as $row) {
                $subs[$row['id']]->update(['position'=>$row['position']]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Subcategories reordered successfully',
                'data'    => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reorder subcategories',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function uniqueSlug(int $categoryId, string $base, ?int $ignoreId=null): string
    {
        $slug = Str::slug($base);
        for ($i=0;;$i++) {
            $try = $i ? "{$slug}-{$i}" : $slug;
            $exists = Subcategory::where('category_id',$categoryId)
                ->where('slug',$try)
                ->when($ignoreId, fn($q)=>$q->where('id','!=',$ignoreId))
                ->exists();
            if (!$exists) return $try;
        }
    }
}
