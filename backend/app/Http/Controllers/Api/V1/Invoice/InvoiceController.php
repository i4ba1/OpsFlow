<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\MarkInvoicePaid;
use App\Actions\Invoices\UpdateInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\InvoiceTransitionRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InvoiceController extends Controller implements HasMiddleware
{
    private const SORTABLE = [
        'invoice_number',
        'status',
        'issued_at',
        'due_at',
        'total_amount',
        'created_at',
        'updated_at',
    ];

    private const WITH_FOR_LIST = [
        'customer:id,name,customer_code',
        'project:id,name,project_code',
        'createdBy:id,name,email,status,timezone',
    ];

    private const WITH_FOR_DETAIL = [
        'customer:id,name,customer_code,billing_email',
        'project:id,name,project_code',
        'createdBy:id,name,email,status,timezone',
        'items',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:invoice.view', only: ['index', 'show']),
            new Middleware('permission:invoice.create', only: ['store']),
            new Middleware('permission:invoice.update', only: ['update']),
            new Middleware('permission:invoice.issue', only: ['issue']),
            new Middleware('permission:invoice.mark_paid', only: ['markPaid']),
            new Middleware('permission:invoice.void', only: ['void']),
        ];
    }

    public function __construct(
        private readonly CreateInvoice $createInvoice,
        private readonly UpdateInvoice $updateInvoice,
        private readonly IssueInvoice $issueInvoice,
        private readonly MarkInvoicePaid $markInvoicePaid,
        private readonly VoidInvoice $voidInvoice,
    ) {}

    /**
     * GET /api/v1/invoices — paginated org-scoped invoice list with status,
     * customer and search filters plus sortable columns
     * (invoice_number/status/issued_at/due_at/total_amount/created_at,
     * default -created_at).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        [$sort, $direction] = $this->sort($request);

        $query = Invoice::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(self::WITH_FOR_LIST);

        $this->filterStatuses($query, $request);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (string) $request->query('customer_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            if ($search !== '') {
                $query->where('invoice_number', 'like', "%{$search}%");
            }
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        /** @var LengthAwarePaginator<int, Invoice> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => InvoiceResource::collection($paginator->items()),
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
     * POST /api/v1/invoices — create a DRAFT invoice for an org customer with
     * line items; amounts and totals are computed from decimal string math.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->createInvoice->handle($request->user(), $request->validated());

        return response()->json([
            'data' => new InvoiceResource($invoice->load(self::WITH_FOR_DETAIL)),
        ], 201);
    }

    /**
     * GET /api/v1/invoices/{invoice} — single org invoice with its items and
     * totals as strings.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertSameOrganization($invoice, $request->user()->organization_id);

        return response()->json([
            'data' => new InvoiceResource($invoice->load(self::WITH_FOR_DETAIL)),
        ]);
    }

    /**
     * PATCH /api/v1/invoices/{invoice} — update the mutable fields and replace
     * the line items of a DRAFT invoice; ISSUED/PAID/VOID invoices are not
     * editable (422 INVOICE_NOT_EDITABLE).
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->assertSameOrganization($invoice, $request->user()->organization_id);

        $updated = $this->updateInvoice->handle($invoice, $request->validated());

        return response()->json([
            'data' => new InvoiceResource($updated->load(self::WITH_FOR_DETAIL)),
        ]);
    }

    /**
     * POST /api/v1/invoices/{invoice}/issue — issue a DRAFT invoice: allocate
     * the INV number, stamp issued_at and default the due date to 30 days from
     * issue when the draft has none (PRD §17).
     */
    public function issue(InvoiceTransitionRequest $request, Invoice $invoice): JsonResponse
    {
        $this->assertSameOrganization($invoice, $request->user()->organization_id);

        $updated = $this->issueInvoice->handle($invoice, $request->user());

        return response()->json([
            'data' => new InvoiceResource($updated->load(self::WITH_FOR_DETAIL)),
        ]);
    }

    /**
     * POST /api/v1/invoices/{invoice}/mark-paid — mark an ISSUED or OVERDUE
     * invoice as paid; PAID/VOID invoices reject the transition (422
     * INVOICE_ALREADY_PAID).
     */
    public function markPaid(InvoiceTransitionRequest $request, Invoice $invoice): JsonResponse
    {
        $this->assertSameOrganization($invoice, $request->user()->organization_id);

        $updated = $this->markInvoicePaid->handle($invoice);

        return response()->json([
            'data' => new InvoiceResource($updated->load(self::WITH_FOR_DETAIL)),
        ]);
    }

    /**
     * POST /api/v1/invoices/{invoice}/void — void a DRAFT, ISSUED or OVERDUE
     * invoice; PAID invoices are terminal and reject the transition (422
     * INVOICE_ALREADY_PAID).
     */
    public function void(InvoiceTransitionRequest $request, Invoice $invoice): JsonResponse
    {
        $this->assertSameOrganization($invoice, $request->user()->organization_id);

        $updated = $this->voidInvoice->handle($invoice);

        return response()->json([
            'data' => new InvoiceResource($updated->load(self::WITH_FOR_DETAIL)),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', '-created_at');
        $direction = '';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        } elseif (str_starts_with($sort, '+')) {
            $direction = 'asc';
            $sort = substr($sort, 1);
        }

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
            $direction = '';
        }

        if ($direction === '') {
            $direction = strtolower((string) $request->query('direction', 'desc'));
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * status[]=DRAFT&status[]=ISSUED filters; unknown values are ignored.
     *
     * @param  Builder<Invoice>  $query
     */
    private function filterStatuses(Builder $query, Request $request): void
    {
        if (! $request->filled('status')) {
            return;
        }

        $values = (array) $request->query('status');
        $valid = array_values(array_filter(
            array_map(
                static fn ($value): ?string => is_string($value)
                    ? InvoiceStatus::tryFrom(strtoupper($value))?->value
                    : null,
                $values,
            ),
            static fn (?string $value): bool => $value !== null,
        ));

        if ($valid !== []) {
            $query->whereIn('status', $valid);
        }
    }

    private function assertSameOrganization(Invoice $invoice, string $organizationId): void
    {
        abort_unless($invoice->organization_id === $organizationId, 404);
    }
}
