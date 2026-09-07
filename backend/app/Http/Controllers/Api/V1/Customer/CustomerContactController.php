<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerContactRequest;
use App\Http\Resources\Api\V1\CustomerContactResource;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class CustomerContactController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer.view', only: ['index']),
            new Middleware('permission:customer.update', only: ['store']),
        ];
    }

    /**
     * GET /api/v1/customers/{customer}/contacts — paginated list of the
     * contacts of one org customer.
     */
    public function index(Request $request, Customer $customer): JsonResponse
    {
        $this->assertSameOrganization($customer, $request->user()->organization_id);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $query = $customer->contacts()
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at');

        /** @var LengthAwarePaginator<int, CustomerContact> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CustomerContactResource::collection($paginator->items()),
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
     * POST /api/v1/customers/{customer}/contacts — add a contact. Setting
     * is_primary clears the primary flag on the other contacts of the customer
     * within the same transaction.
     */
    public function store(StoreCustomerContactRequest $request, Customer $customer): JsonResponse
    {
        $this->assertSameOrganization($customer, $request->user()->organization_id);

        $contact = DB::transaction(function () use ($request, $customer): CustomerContact {
            if ((bool) $request->validated('is_primary', false)) {
                $customer->contacts()->update(['is_primary' => false]);
            }

            return $customer->contacts()->create([
                'organization_id' => $customer->organization_id,
                'user_id' => $request->validated('user_id'),
                'name' => $request->validated('name'),
                'email' => strtolower($request->validated('email')),
                'phone' => $request->validated('phone'),
                'job_title' => $request->validated('job_title'),
                'is_primary' => (bool) $request->validated('is_primary', false),
            ]);
        });

        return response()->json([
            'data' => new CustomerContactResource($contact),
        ], 201);
    }

    private function assertSameOrganization(Customer $customer, string $organizationId): void
    {
        abort_unless($customer->organization_id === $organizationId, 404);
    }
}
