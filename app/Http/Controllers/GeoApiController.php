<?php

namespace App\Http\Controllers;

use App\Models\{Country, State, CityDistrict, Town};
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\ApiResponse;

class GeoApiController extends Controller
{
    use ApiResponse;

    // GET /v1/geo/countries?q=&region=&subregion=&with_counts=1&per_page=100&page=1
    public function countries(Request $req)
    {
        $perPage = min(200, max(1, (int) $req->query('per_page', 100)));
        $q = trim((string) $req->query('q', ''));
        $region = trim((string) $req->query('region', ''));
        $sub = trim((string) $req->query('subregion', ''));
        $withCnt = $req->boolean('with_counts');

        $qb = Country::query();

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                    ->orWhere('iso2', 'like', "%$q%")
                    ->orWhere('iso3', 'like', "%$q%");
            });
        }

        if ($region !== '')
            $qb->where('region', $region);
        if ($sub !== '')
            $qb->where('subregion', $sub);
        if ($withCnt)
            $qb->withCount(['states', 'cities', 'towns']);

        $page = $qb->orderBy('name')
            ->paginate($perPage)
            ->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]
        );
    }

    // GET /v1/geo/states?country=IN|840|India&q=&with_counts=0
    public function states(Request $req)
    {
        $req->validate(['country' => ['required', 'string']]);

        [$country, $countryId] = $this->resolveCountry($req->query('country'));
        if (!$countryId)
            return $this->fail('Country not found', 404);

        $perPage = min(500, max(1, (int) $req->query('per_page', 200)));
        $q = trim((string) $req->query('q', ''));
        $withCnt = $req->boolean('with_counts');

        $qb = State::query()->where('country_id', $countryId);
        if ($q !== '')
            $qb->where('name', 'like', "%$q%");
        if ($withCnt)
            $qb->withCount(['cities', 'towns']);

        $page = $qb->orderBy('name')->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'country' => ['id' => $countryId, 'name' => $country->name, 'iso2' => $country->iso2],
            ]
        );
    }

    // GET /v1/geo/cities?country=IN&state=KA|Karnataka|123&q=&per_page=100
    public function cities(Request $req)
    {
        $req->validate([
            'country' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $perPage = min(500, max(1, (int) $req->query('per_page', 100)));
        $q = trim((string) $req->query('q', ''));
        $countryId = null;
        $stateId = null;

        if ($req->filled('state')) {
            [$state, $stateId] = $this->resolveState($req->query('state'), $req->query('country'));
            if (!$stateId)
                return $this->fail('State not found', 404);
            $countryId = $state->country_id;
        } elseif ($req->filled('country')) {
            [, $countryId] = $this->resolveCountry($req->query('country'));
            if (!$countryId)
                return $this->fail('Country not found', 404);
        }

        $qb = CityDistrict::query();
        if ($countryId)
            $qb->where('country_id', $countryId);
        if ($stateId)
            $qb->where('state_id', $stateId);
        if ($q !== '')
            $qb->where('name', 'like', "%$q%");

        $page = $qb->orderBy('name')->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]
        );
    }

    // GET /v1/geo/towns?country=IN&state=MZ&city_id=123&min_pop=1000&max_pop=100000&q=&per_page=100
    public function towns(Request $req)
    {
        $perPage = min(500, max(1, (int) $req->query('per_page', 100)));
        $q = trim((string) $req->query('q', ''));
        $minPop = $req->filled('min_pop') ? (int) $req->query('min_pop') : null;
        $maxPop = $req->filled('max_pop') ? (int) $req->query('max_pop') : null;

        $countryId = null;
        $stateId = null;
        $cityId = null;

        if ($req->filled('state')) {
            [$state, $stateId] = $this->resolveState($req->query('state'), $req->query('country'));
            if (!$stateId)
                return $this->fail('State not found', 404);
            $countryId = $state->country_id;
        } elseif ($req->filled('country')) {
            [, $countryId] = $this->resolveCountry($req->query('country'));
            if (!$countryId)
                return $this->fail('Country not found', 404);
        }

        if ($req->filled('city_id')) {
            $cityId = (int) $req->query('city_id');
            if (!CityDistrict::whereKey($cityId)->exists())
                return $this->fail('City/District not found', 404);
        }

        $qb = Town::query();
        if ($countryId)
            $qb->where('country_id', $countryId);
        if ($stateId)
            $qb->where('state_id', $stateId);
        if ($cityId)
            $qb->where('city_district_id', $cityId);
        if ($q !== '')
            $qb->where('name', 'like', "%$q%");
        if ($minPop !== null)
            $qb->where('population', '>=', $minPop);
        if ($maxPop !== null)
            $qb->where('population', '<=', $maxPop);

        $page = $qb->orderByRaw('population IS NULL') // non-null first
            ->orderByDesc('population')
            ->orderBy('name')
            ->paginate($perPage)->appends($req->query());

        return $this->ok(
            $page->items(),
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]
        );
    }

    // ---------- Helpers ----------

    private function resolveCountry(?string $input): array
    {
        $s = trim((string) $input);
        if ($s === '')
            return [null, null];

        $c = Country::where('iso2', strtoupper($s))
            ->orWhere('iso3', strtoupper($s))
            ->orWhere('numeric_code', $s)
            ->orWhere('name', $s)
            ->first();

        return [$c, $c?->id];
    }

    private function resolveState(?string $stateInput, ?string $countryInput = null): array
    {
        $s = trim((string) $stateInput);
        if ($s === '')
            return [null, null];

        $qb = State::query();
        if ($countryInput) {
            [, $cid] = $this->resolveCountry($countryInput);
            if ($cid)
                $qb->where('country_id', $cid);
        }

        if (ctype_digit($s)) {
            $st = (clone $qb)->whereKey((int) $s)->first();
            if ($st)
                return [$st, $st->id];
        }

        // Try full ISO 3166-2 (e.g., IN-MZ), then short code (e.g., MZ), then by name
        $st = (clone $qb)->where('iso3166_2', strtoupper($s))->first()
            ?? (clone $qb)->where('iso2', strtoupper($s))->first()
            ?? (clone $qb)->where('name', $s)->orWhere('name', 'like', "%$s%")->first();

        return [$st, $st?->id];
    }

    public function storeCountry(Request $req)
    {
        $payload = $this->wrapItems($req->all()); // => [ [row], [row], ... ]

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'iso2' => ['required', 'string', 'size:2'],
            'iso3' => ['required', 'string', 'size:3'],
            'numeric_code' => ['nullable', 'integer'],
            'phonecode' => ['nullable', 'integer'],
            'capital' => ['nullable', 'string', 'max:200'],
            'currency' => ['nullable', 'string', 'max:50'],
            'currency_name' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'tld' => ['nullable', 'string', 'max:20'],
            'native' => ['nullable', 'string', 'max:200'],
            'region' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'integer'],
            'subregion' => ['nullable', 'string', 'max:100'],
            'subregion_id' => ['nullable', 'integer'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'timezones' => ['nullable'], // array or JSON string
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'emojiU' => ['nullable', 'string', 'max:20'],
        ];

        $created = [];
        DB::transaction(function () use ($payload, $rules, &$created) {
            foreach ($payload as $row) {
                $data = validator($row, $rules)->validate();
                // Normalize codes; model mutators also handle this, but OK to be explicit:
                $data['iso2'] = strtoupper($data['iso2']);
                $data['iso3'] = strtoupper($data['iso3']);
                // Accept timezones as JSON string or array
                if (isset($data['timezones']) && is_string($data['timezones'])) {
                    $decoded = json_decode($data['timezones'], true);
                    if (json_last_error() === JSON_ERROR_NONE)
                        $data['timezones'] = $decoded;
                }
                $country = Country::updateOrCreate(
                    ['iso2' => $data['iso2']], // idempotent on iso2
                    $data
                );
                $created[] = $country->fresh();
            }
        });

        $res = $this->ok($created, ['count' => count($created)]);
        return $res->setStatusCode(201);
    }

    // POST /v1/geo/states
    // Body can include { country: "IN" } or each item can have country / country_id
    public function storeState(Request $req)
    {
        $topCountry = $req->input('country');
        [, $topCountryId] = $this->resolveCountry($topCountry);

        $payload = $this->wrapItems($req->except('country'));

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'iso2' => ['nullable', 'string', 'max:20'],       // short (e.g., MZ)
            'iso3166_2' => ['nullable', 'string', 'max:20'],       // full (e.g., IN-MZ)
            'fips_code' => ['nullable', 'string', 'max:10'],
            'type' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:states,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'timezone' => ['nullable', 'string', 'max:64'],
            // country provided either via top param or per-item:
            'country' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'country_name' => ['nullable', 'string', 'max:200'],
        ];

        $created = [];
        DB::transaction(function () use ($payload, $rules, $topCountryId, &$created) {
            foreach ($payload as $row) {
                $data = validator($row, $rules)->validate();

                $countryId = $data['country_id'] ?? null;
                if (!$countryId && !empty($data['country'])) {
                    [, $countryId] = $this->resolveCountry($data['country']);
                }
                if (!$countryId)
                    $countryId = $topCountryId;
                if (!$countryId)
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'country not resolved'], 422)
                    );

                $data['country_id'] = $countryId;
                // hydrate denorm fields if missing
                $c = Country::find($countryId);
                $data['country_code'] = strtoupper($data['country_code'] ?? $c?->iso2 ?? '');
                $data['country_name'] = $data['country_name'] ?? $c?->name ?? '';

                if (!empty($data['iso2']))
                    $data['iso2'] = strtoupper($data['iso2']);
                if (!empty($data['iso3166_2']))
                    $data['iso3166_2'] = strtoupper($data['iso3166_2']);

                // Upsert key: (country_id, iso3166_2) if present, else (country_id, iso2) else by (country_id, name)
                $match = ['country_id' => $countryId];
                if (!empty($data['iso3166_2']))
                    $match += ['iso3166_2' => $data['iso3166_2']];
                elseif (!empty($data['iso2']))
                    $match += ['iso2' => $data['iso2']];
                else
                    $match += ['name' => $data['name']];

                $state = State::updateOrCreate($match, $data);
                $created[] = $state->fresh();
            }
        });

        $res = $this->ok($created, ['count' => count($created)]);
        return $res->setStatusCode(201);
    }

    // POST /v1/geo/cities
    // Assumes CityDistrict has: name, country_id, state_id, latitude, longitude, population (nullable)
    public function storeCity(Request $req)
    {
        $topCountry = $req->input('country');
        $topState = $req->input('state');
        [, $topCountryId] = $this->resolveCountry($topCountry);
        [, $topStateId] = $this->resolveState($topState, $topCountry);

        $payload = $this->wrapItems($req->except(['country', 'state']));

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'population' => ['nullable', 'integer'],
            // optional per-item scoping:
            'country' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state' => ['nullable', 'string'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
        ];

        $created = [];
        DB::transaction(function () use ($payload, $rules, $topCountryId, $topStateId, &$created) {
            foreach ($payload as $row) {
                $data = validator($row, $rules)->validate();

                $countryId = $data['country_id'] ?? null;
                $stateId = $data['state_id'] ?? null;

                if (!$countryId && !empty($data['country'])) {
                    [, $countryId] = $this->resolveCountry($data['country']);
                }
                if (!$stateId && !empty($data['state'])) {
                    [, $stateId] = $this->resolveState($data['state'], $data['country'] ?? null);
                }

                $countryId = $countryId ?: $topCountryId;
                $stateId = $stateId ?: $topStateId;

                if (!$countryId)
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'country not resolved'], 422)
                    );
                if (!$stateId)
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'state not resolved'], 422)
                    );

                $data['country_id'] = $countryId;
                $data['state_id'] = $stateId;

                $city = CityDistrict::updateOrCreate(
                    ['name' => $data['name'], 'state_id' => $stateId],
                    $data
                );
                $created[] = $city->fresh();
            }
        });

        $res = $this->ok($created, ['count' => count($created)]);
        return $res->setStatusCode(201);
    }

    // POST /v1/geo/towns
    // Assumes Town has: name,country_id,state_id,city_district_id (nullable), latitude, longitude, population
    public function storeTown(Request $req)
    {
        $topCountry = $req->input('country');
        $topState = $req->input('state');
        [, $topCountryId] = $this->resolveCountry($topCountry);
        [, $topStateId] = $this->resolveState($topState, $topCountry);

        $payload = $this->wrapItems($req->except(['country', 'state']));

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'population' => ['nullable', 'integer'],
            'city_district_id' => ['nullable', 'integer', 'exists:city_districts,id'],
            // optional per-item scoping:
            'country' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state' => ['nullable', 'string'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city' => ['nullable', 'string'], // resolve by name within state if given
        ];

        $created = [];
        DB::transaction(function () use ($payload, $rules, $topCountryId, $topStateId, &$created) {
            foreach ($payload as $row) {
                $data = validator($row, $rules)->validate();

                $countryId = $data['country_id'] ?? null;
                $stateId = $data['state_id'] ?? null;

                if (!$countryId && !empty($data['country'])) {
                    [, $countryId] = $this->resolveCountry($data['country']);
                }
                if (!$stateId && !empty($data['state'])) {
                    [, $stateId] = $this->resolveState($data['state'], $data['country'] ?? null);
                }

                $countryId = $countryId ?: $topCountryId;
                $stateId = $stateId ?: $topStateId;

                if (!$countryId)
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'country not resolved'], 422)
                    );
                if (!$stateId)
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['message' => 'state not resolved'], 422)
                    );

                // resolve city_district if provided by name
                $cityDistrictId = $data['city_district_id'] ?? null;
                if (!$cityDistrictId && !empty($data['city'])) {
                    $cd = CityDistrict::where('state_id', $stateId)->where('name', $data['city'])->first();
                    if ($cd)
                        $cityDistrictId = $cd->id;
                }

                $data['country_id'] = $countryId;
                $data['state_id'] = $stateId;
                $data['city_district_id'] = $cityDistrictId;

                $town = Town::updateOrCreate(
                    ['name' => $data['name'], 'state_id' => $stateId],
                    $data
                );
                $created[] = $town->fresh();
            }
        });

        $res = $this->ok($created, ['count' => count($created)]);
        return $res->setStatusCode(201);
    }

    // ---- tiny helper to accept single object or {items:[...]} or raw array ----
    private function wrapItems($input): array
    {
        if (isset($input['items']) && is_array($input['items']))
            return array_values($input['items']);
        if (is_array($input) && array_is_list($input))
            return $input;
        if (is_array($input))
            return [$input];
        return [];
    }
}
