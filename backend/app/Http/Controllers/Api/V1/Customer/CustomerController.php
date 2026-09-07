<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Support\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['name', 'customer_code', 'status', 'created_at', 'updated_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer.view', only: ['index', 'show']),
            new Middleware('permission:customer.create', only: ['store']),
            new Middleware('permission:customer.update', only: ['update']),
            new Middleware('permission:customer.archive', only: ['destroy']),
        ];
    }

    /**
     * GET /api/v1/customers — paginated, searchable and sortable org customer
     * list.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $sort = (string) $request->query('sort', 'created_at');
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $query = Customer::query()
            ->where('organization_id', $request->user()->organization_id)
            ->withCount('contacts');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            if ($search !== '') {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('status')) {
            $statuses = (array) $request->query('status', []);
            $valid = array_values(array_filter(
                array_map(
                    static fn ($value): ?string => is_string($value)
                        ? CustomerStatus::tryFrom(strtoupper($value))?->value
                        : null,
                    $statuses,
                ),
                static fn (?string $value): bool => $value !== null,
            ));

            if ($valid !== []) {
                $query->whereIn('status', $valid);
            }
        }

        $query->orderBy($sort, $direction);

        /** @var LengthAwarePaginator<int, Customer> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CustomerResource::collection($paginator->items()),
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
     * POST /api/v1/customers — create a customer with an auto-generated code.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->createWithCode($request);

        return response()->json([
            'data' => new CustomerResource($customer->loadCount('contacts')),
        ], 201);
    }

    /**
     * GET /api/v1/customers/{customer} — single org customer.
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->assertSameOrganization($customer, $request->user()->organization_id);

        return response()->json([
            'data' => new CustomerResource($customer->loadCount('contacts')),
        ]);
    }

    /**
     * PATCH /api/v1/customers/{customer} — update profile fields and status.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->assertSameOrganization($customer, $request->user()->organization_id);

        $old = $customer->only($request->validated() !== [] ? array_keys($request->validated()) : []);

        $customer->update($request->validated());

        $new = $customer->only(array_keys($request->validated()));

        app(AuditRecorder::class)->record(
            actor: $request->user(),
            action: 'customer.updated',
            entity: $customer,
            oldValues: $old,
            newValues: $new,
            request: $request,
        );

        return response()->json([
            'data' => new CustomerResource($customer->fresh()->loadCount('contacts')),
        ]);
    }

    /**
     * DELETE /api/v1/customers/{customer} — soft archive: the customer keeps
     * its row and history, only the status flips to ARCHIVED.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->assertSameOrganization($customer, $request->user()->organization_id);

        $customer->update([
            'status' => CustomerStatus::Archived,
            'archived_at' => now(),
        ]);

        app(AuditRecorder::class)->record(
            actor: $request->user(),
            action: 'customer.archived',
            entity: $customer,
            newValues: ['status' => CustomerStatus::Archived->value],
            request: $request,
        );

        return response()->json([
            'data' => new CustomerResource($customer->fresh()->loadCount('contacts')),
        ]);
    }

    /**
     * Insert the customer inside a transaction, retrying with a freshly
     * generated code when the unique (organization_id, customer_code)
     * constraint rejects a candidate so parallel creates cannot collide.
     */
    private function createWithCode(StoreCustomerRequest $request): Customer
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'CUS-'.strtoupper(Str::random(8));

            try {
                return DB::transaction(function () use ($request, $code): Customer {
                    return Customer::query()->create([
                        'organization_id' => $request->user()->organization_id,
                        'customer_code' => $code,
                        'name' => $request->validated('name'),
                        'legal_name' => $request->validated('legal_name'),
                        'registration_number' => $request->validated('registration_number'),
                        'email' => $request->validated('email'),
                        'phone' => $request->validated('phone'),
                        'billing_email' => $request->validated('billing_email'),
                        'address_line_1' => $request->validated('address_line_1'),
                        'address_line_2' => $request->validated('address_line_2'),
                        'city' => $request->validated('city'),
                        'postal_code' => $request->validated('postal_code'),
                        'country_code' => $request->validated('country_code', 'SG'),
                        'status' => $request->validated('status', CustomerStatus::Active->value),
                        'metadata' => $request->validated('metadata'),
                        'created_by' => $request->user()->id,
                    ]);
                });
            } catch (QueryException) {
                // A concurrent create claimed this code — retry with a fresh one.
            }
        }

        abort(500, 'Unable to allocate a unique customer code.');
    }

    private function assertSameOrganization(Customer $customer, string $organizationId): void
    {
        abort_unless($customer->organization_id === $organizationId, 404);
    }
}
