<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyInterestEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $customerByStatus = Customer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $customerCounts = [];
        foreach (Customer::STATUSES as $status) {
            $customerCounts[$status] = (int) ($customerByStatus[$status] ?? 0);
        }

        $propertyByStatus = Property::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $propertyCounts = [];
        foreach (Property::STATUSES as $status) {
            $propertyCounts[$status] = (int) ($propertyByStatus[$status] ?? 0);
        }

        $activeLeases = Lease::query()->where('status', 'active')->count();
        $endingSoon = Lease::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays(30)->toDateString())
            ->count();

        $offersTotal = PropertyInterestEvent::query()->where('type', 'offer')->count();
        $viewsTotal = PropertyInterestEvent::query()->where('type', 'view')->count();
        $buyOffers = PropertyInterestEvent::query()
            ->where('type', 'offer')
            ->where('offer_intent', 'buy')
            ->count();
        $rentOffers = PropertyInterestEvent::query()
            ->where('type', 'offer')
            ->where('offer_intent', 'rent')
            ->count();

        $days = 14;
        $start = now()->subDays($days - 1)->startOfDay();

        $activity = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $activity[$key] = [
                'date' => $key,
                'label' => $day->format('d.m'),
                'views' => 0,
                'offers' => 0,
                'customers' => 0,
            ];
        }

        $interestRows = PropertyInterestEvent::query()
            ->selectRaw('DATE(occurred_at) as day, type, COUNT(*) as total')
            ->where('occurred_at', '>=', $start)
            ->groupBy('day', 'type')
            ->get();

        foreach ($interestRows as $row) {
            $day = Carbon::parse($row->day)->toDateString();
            if (! isset($activity[$day])) {
                continue;
            }
            if ($row->type === 'view') {
                $activity[$day]['views'] = (int) $row->total;
            }
            if ($row->type === 'offer') {
                $activity[$day]['offers'] = (int) $row->total;
            }
        }

        $customerRows = Customer::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->get();

        foreach ($customerRows as $row) {
            $day = Carbon::parse($row->day)->toDateString();
            if (isset($activity[$day])) {
                $activity[$day]['customers'] = (int) $row->total;
            }
        }

        $customerStatusLabels = [
            'new' => 'Yeni',
            'contacted' => 'Görüşüldü',
            'interested' => 'İlgili',
            'closed' => 'Kapandı',
        ];

        $propertyStatusLabels = [
            'active' => 'Aktif',
            'under_contract' => 'Sözleşme aşamasında',
            'rented' => 'Kiralandı',
            'sold' => 'Satıldı',
        ];

        return response()->json([
            'summary' => [
                'customers_total' => array_sum($customerCounts),
                'customers_active' => ($customerCounts['interested'] ?? 0) + ($customerCounts['contacted'] ?? 0),
                'customers_new' => $customerCounts['new'] ?? 0,
                'properties_total' => array_sum($propertyCounts),
                'properties_active' => $propertyCounts['active'] ?? 0,
                'leases_active' => $activeLeases,
                'leases_ending_soon' => $endingSoon,
                'views_total' => $viewsTotal,
                'offers_total' => $offersTotal,
            ],
            'customers_by_status' => collect($customerCounts)
                ->map(fn (int $value, string $status) => [
                    'key' => $status,
                    'label' => $customerStatusLabels[$status] ?? $status,
                    'value' => $value,
                ])
                ->values()
                ->all(),
            'properties_by_status' => collect($propertyCounts)
                ->map(fn (int $value, string $status) => [
                    'key' => $status,
                    'label' => $propertyStatusLabels[$status] ?? $status,
                    'value' => $value,
                ])
                ->values()
                ->all(),
            'offers_by_intent' => [
                ['key' => 'rent', 'label' => 'Kira teklifi', 'value' => $rentOffers],
                ['key' => 'buy', 'label' => 'Satın alma', 'value' => $buyOffers],
                [
                    'key' => 'other',
                    'label' => 'Belirtilmemiş',
                    'value' => max(0, $offersTotal - $rentOffers - $buyOffers),
                ],
            ],
            'activity_last_14_days' => array_values($activity),
        ]);
    }
}
