<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyInterestEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyInterestEventController extends Controller
{
    public function all(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(PropertyInterestEvent::TYPES)],
            'offer_intent' => ['nullable', Rule::in(PropertyInterestEvent::OFFER_INTENTS)],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PropertyInterestEvent::query()
            ->with(['creator:uid,name', 'property:uid,title,location,listing_type,status'])
            ->latest('occurred_at');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['offer_intent'])) {
            $query->where('offer_intent', $validated['offer_intent']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('property', function ($pq) use ($search) {
                        $pq->where('title', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $validated['per_page'] ?? 20;
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (PropertyInterestEvent $event) => $event->toApiArray())
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function index(Property $property): JsonResponse
    {
        $events = $property->interestEvents()
            ->with('creator:uid,name')
            ->latest('occurred_at')
            ->get();

        $views = $events->where('type', 'view')->count();
        $offers = $events->where('type', 'offer')->count();

        return response()->json([
            'views_count' => $views,
            'offers_count' => $offers,
            'data' => $events->map(fn (PropertyInterestEvent $event) => $event->toApiArray())->values(),
        ]);
    }

    public function store(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PropertyInterestEvent::TYPES)],
            'offer_intent' => ['nullable', Rule::in(PropertyInterestEvent::OFFER_INTENTS)],
            'offer_timing' => ['nullable', Rule::in(PropertyInterestEvent::OFFER_TIMINGS)],
            'occurred_at' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => [
                'nullable',
                'string',
                'regex:/^05[0-9]{9}$/',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', Rule::in(PropertyInterestEvent::CURRENCIES)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['type'] === 'offer') {
            if (empty($data['offer_intent'])) {
                throw ValidationException::withMessages([
                    'offer_intent' => ['Teklif amacı seçin (kira veya satın alma).'],
                ]);
            }

            if (empty($data['offer_timing'])) {
                throw ValidationException::withMessages([
                    'offer_timing' => ['Teklifin ne zaman geçerli olacağını seçin.'],
                ]);
            }

            if (empty($data['amount'])) {
                throw ValidationException::withMessages([
                    'amount' => ['Teklif için tutar zorunludur.'],
                ]);
            }

            $currency = strtoupper($data['currency'] ?? 'TRY');
            $data['currency'] = $currency;

            if ($currency === 'TRY') {
                $data['exchange_rate'] = 1;
            } elseif (empty($data['exchange_rate']) || (float) $data['exchange_rate'] <= 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => ['Döviz teklifinde kur zorunludur.'],
                ]);
            }
        } else {
            $data['offer_intent'] = null;
            $data['offer_timing'] = null;
            $data['amount'] = null;
            $data['currency'] = null;
            $data['exchange_rate'] = null;
        }

        $event = $property->interestEvents()->create([
            ...$data,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'created_by' => $request->user()->id,
        ]);

        $event->load(['creator:uid,name', 'property:uid,title,location,listing_type,status']);

        return response()->json([
            'data' => $event->toApiArray(),
        ], 201);
    }

    public function destroy(Property $property, PropertyInterestEvent $event): JsonResponse
    {
        if ($event->property_id !== $property->id) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Kayıt silindi.']);
    }
}
