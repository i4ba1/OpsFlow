<?php

namespace App\Http\Controllers\Api\V1\SLA;

use App\Http\Controllers\Controller;
use App\Http\Requests\SLA\StoreBusinessCalendarRequest;
use App\Http\Requests\SLA\UpdateBusinessCalendarRequest;
use App\Http\Resources\Api\V1\BusinessCalendarResource;
use App\Models\BusinessCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class BusinessCalendarController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sla.view', only: ['index', 'show']),
            new Middleware('permission:sla.manage', only: ['store', 'update']),
        ];
    }

    /**
     * GET /api/v1/business-calendars — paginated org calendar list with their
     * hours and holidays.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $query = BusinessCalendar::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('hours', 'holidays')
            ->orderBy('created_at');

        /** @var LengthAwarePaginator<int, BusinessCalendar> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => BusinessCalendarResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    /**
     * POST /api/v1/business-calendars — create a calendar with its weekly
     * hours and holidays in one transaction.
     */
    public function store(StoreBusinessCalendarRequest $request): JsonResponse
    {
        $calendar = DB::transaction(function () use ($request): BusinessCalendar {
            $calendar = BusinessCalendar::query()->create([
                'organization_id' => $request->user()->organization_id,
                'name' => $request->validated('name'),
                'timezone' => $request->validated('timezone', 'Asia/Singapore'),
                'description' => $request->validated('description'),
            ]);

            $this->syncSchedule($calendar, $request->validated());

            return $calendar;
        });

        return response()->json([
            'data' => new BusinessCalendarResource($calendar->load('hours', 'holidays')),
        ], 201);
    }

    /**
     * GET /api/v1/business-calendars/{businessCalendar} — single org calendar.
     */
    public function show(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertSameOrganization($businessCalendar, $request->user()->organization_id);

        return response()->json([
            'data' => new BusinessCalendarResource($businessCalendar->load('hours', 'holidays')),
        ]);
    }

    /**
     * PATCH /api/v1/business-calendars/{businessCalendar} — update the
     * calendar profile and/or replace its hours and holidays.
     */
    public function update(UpdateBusinessCalendarRequest $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertSameOrganization($businessCalendar, $request->user()->organization_id);

        $updated = DB::transaction(function () use ($request, $businessCalendar): BusinessCalendar {
            $data = $request->validated();

            if (isset($data['hours']) || isset($data['holidays'])) {
                $this->syncSchedule($businessCalendar, $data);
            }

            $businessCalendar->update(collect($data)->except('hours', 'holidays')->all());

            return $businessCalendar;
        });

        return response()->json([
            'data' => new BusinessCalendarResource($updated->load('hours', 'holidays')),
        ]);
    }

    /**
     * Replace the weekly hours and holidays of a calendar from validated
     * input.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncSchedule(BusinessCalendar $businessCalendar, array $data): void
    {
        $hours = $data['hours'] ?? [];

        if (is_array($hours)) {
            $businessCalendar->hours()->delete();

            foreach ($hours as $hour) {
                if (! isset($hour['day_of_week'], $hour['opens_at'], $hour['closes_at'])) {
                    continue;
                }

                $businessCalendar->hours()->create([
                    'day_of_week' => (int) $hour['day_of_week'],
                    'opens_at' => $hour['opens_at'].':00',
                    'closes_at' => $hour['closes_at'].':00',
                ]);
            }
        }

        $holidays = $data['holidays'] ?? [];

        if (is_array($holidays)) {
            $businessCalendar->holidays()->delete();

            foreach ($holidays as $holiday) {
                if (! isset($holiday['name'], $holiday['date'])) {
                    continue;
                }

                $businessCalendar->holidays()->create([
                    'name' => $holiday['name'],
                    'date' => $holiday['date'],
                ]);
            }
        }
    }

    private function assertSameOrganization(BusinessCalendar $businessCalendar, string $organizationId): void
    {
        abort_unless($businessCalendar->organization_id === $organizationId, 404);
    }
}
