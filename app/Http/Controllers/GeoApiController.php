<?php

namespace App\Http\Controllers;

use App\Models\{Country, State, CityDistrict, Town};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\ApiResponse;

class GeoApiController extends Controller
{
    use ApiResponse;

    // GET /v1/geo/countries?q=&region=&subregion=&with_counts=1&per_page=100&page=1
    public function countries(Request $req)
    {
        $perPage = min(200, max(1, (int)$req->query('per_page', 100)));
        $q       = trim((string)$req->query('q', ''));
        $region  = trim((string)$req->query('region', ''));
        $sub     = trim((string)$req->query('subregion', ''));
        $withCnt = $req->boolean('with_counts');

        $key = "geo:countries:".md5(json_encode([$q,$region,$sub,$withCnt,$perPage,$req->query('page',1)]));

        return Cache::remember($key, now()->addMinutes(10), function () use ($q,$region,$sub,$withCnt,$perPage) {
            $qb = Country::query();
            if ($q !== '') {
                $qb->where(function($w) use ($q){
                    $w->where('name', 'like', "%$q%")
                      ->orWhere('official_name', 'like', "%$q%")
                      ->orWhere('iso2', 'like', "%$q%")
                      ->orWhere('iso3', 'like', "%$q%");
                });
            }
            if ($region !== '')   $qb->where('region', $region);
            if ($sub !== '')      $qb->where('subregion', $sub);
            if ($withCnt)         $qb->withCount(['states','cities','towns']);

            $page = $qb->orderBy('name')->paginate($perPage);

            return $this->ok(
                $page->items(),
                [
                    'current_page' => $page->currentPage(),
                    'per_page'     => $page->perPage(),
                    'total'        => $page->total(),
                    'last_page'    => $page->lastPage(),
                ]
            );
        });
    }

    // GET /v1/geo/states?country=IN|840|India&q=&with_counts=0
    public function states(Request $req)
    {
        $req->validate(['country' => ['required','string']]);

        [$country, $countryId] = $this->resolveCountry($req->query('country'));
        if (!$countryId) return $this->fail('Country not found', 404);

        $perPage = min(500, max(1, (int)$req->query('per_page', 200)));
        $q       = trim((string)$req->query('q', ''));
        $withCnt = $req->boolean('with_counts');

        $qb = State::query()->where('country_id', $countryId);
        if ($q !== '') $qb->where('name','like',"%$q%");
        if ($withCnt) $qb->withCount(['cities','towns']);

        $page = $qb->orderBy('name')->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'country'      => ['id'=>$countryId, 'name'=>$country->name, 'iso2'=>$country->iso2],
            ]
        );
    }

    // GET /v1/geo/cities?country=IN&state=KA|Karnataka|123&q=&per_page=100
    public function cities(Request $req)
    {
        $req->validate([
            'country' => ['nullable','string'],
            'state'   => ['nullable','string'],
        ]);

        $perPage = min(500, max(1, (int)$req->query('per_page', 100)));
        $q       = trim((string)$req->query('q', ''));
        $countryId = null;
        $stateId   = null;

        if ($req->filled('state')) {
            [$state, $stateId] = $this->resolveState($req->query('state'), $req->query('country'));
            if (!$stateId) return $this->fail('State not found', 404);
            $countryId = $state->country_id;
        } elseif ($req->filled('country')) {
            [, $countryId] = $this->resolveCountry($req->query('country'));
            if (!$countryId) return $this->fail('Country not found', 404);
        }

        $qb = CityDistrict::query();
        if ($countryId) $qb->where('country_id', $countryId);
        if ($stateId)   $qb->where('state_id', $stateId);
        if ($q !== '')  $qb->where('name','like',"%$q%");

        $page = $qb->orderBy('name')->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        );
    }

    // GET /v1/geo/towns?country=IN&state=MZ&city_id=123&min_pop=1000&max_pop=100000&q=&per_page=100
    public function towns(Request $req)
    {
        $perPage = min(500, max(1, (int)$req->query('per_page', 100)));
        $q       = trim((string)$req->query('q', ''));
        $minPop  = $req->filled('min_pop') ? (int)$req->query('min_pop') : null;
        $maxPop  = $req->filled('max_pop') ? (int)$req->query('max_pop') : null;

        $countryId = null; $stateId = null; $cityId = null;

        if ($req->filled('state')) {
            [$state, $stateId] = $this->resolveState($req->query('state'), $req->query('country'));
            if (!$stateId) return $this->fail('State not found', 404);
            $countryId = $state->country_id;
        } elseif ($req->filled('country')) {
            [, $countryId] = $this->resolveCountry($req->query('country'));
            if (!$countryId) return $this->fail('Country not found', 404);
        }

        if ($req->filled('city_id')) {
            $cityId = (int)$req->query('city_id');
            if (!CityDistrict::whereKey($cityId)->exists()) return $this->fail('City/District not found', 404);
        }

        $qb = Town::query();
        if ($countryId) $qb->where('country_id', $countryId);
        if ($stateId)   $qb->where('state_id', $stateId);
        if ($cityId)    $qb->where('city_district_id', $cityId);
        if ($q !== '')  $qb->where('name','like',"%$q%");
        if ($minPop !== null) $qb->where('population','>=',$minPop);
        if ($maxPop !== null) $qb->where('population','<=',$maxPop);

        $page = $qb->orderByRaw('population IS NULL')
                   ->orderByDesc('population')
                   ->orderBy('name')
                   ->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        );
    }

    // ---------- Helpers (unchanged) ----------

    private function resolveCountry(?string $input): array
    {
        $s = trim((string)$input);
        if ($s === '') return [null, null];

        $c = Country::where('iso2', strtoupper($s))
            ->orWhere('iso3', strtoupper($s))
            ->orWhere('numeric_code', $s)
            ->orWhere('name', $s)
            ->first();

        return [$c, $c?->id];
    }

    private function resolveState(?string $stateInput, ?string $countryInput = null): array
    {
        $s = trim((string)$stateInput);
        if ($s === '') return [null, null];

        $qb = State::query();
        if ($countryInput) {
            [, $cid] = $this->resolveCountry($countryInput);
            if ($cid) $qb->where('country_id', $cid);
        }

        if (ctype_digit($s)) {
            $st = (clone $qb)->whereKey((int)$s)->first();
            if ($st) return [$st, $st->id];
        }

        $st = (clone $qb)->where('state_code', strtoupper($s))->first();
        if ($st) return [$st, $st->id];

        $st = (clone $qb)->where('name', $s)->orWhere('name','like',"%$s%")->first();
        return [$st, $st?->id];
    }
}
