<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\DisbursementRequest;
use App\Models\FundAdjustment;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\SystemSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinancialManagementController extends Controller
{
    private const BUDGET_TYPES = [
        'annual',
        'supplemental',
    ];

    private const BUDGET_STATUSES = [
        'draft',
        'for_council_approval',
        'approved',
        'rejected',
    ];

    private const BUDGET_CATEGORIES = [
        'administrative',
        'operations',
        'social_services',
        'infrastructure',
        'contingency',
        'other_expense',
    ];

    private const DISBURSEMENT_STATUSES = [
        'requested',
        'approved',
        'rejected',
        'released',
    ];

    public function financialManagement(Request $request): Response
    {
        return Inertia::render('Admin/FinancialManagementOverview', [
            'summary' => $this->buildSummary(''),
            'recentPayments' => Payment::query()
                ->with(['resident:id,first_name,last_name', 'collector:id,name'])
                ->orderByDesc('paid_at')
                ->limit(8)
                ->get(),
            'recentFundAdjustments' => FundAdjustment::query()
                ->with('adjustedBy:id,name')
                ->latest('adjusted_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function adjustFunds(Request $request)
    {
        $validated = $request->validate([
            'adjustment_type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = (float) $validated['amount'];
        $adjustment = null;
        $beforeBalances = [];
        $afterBalances = [];

        DB::transaction(function () use ($validated, $amount, $request, &$adjustment, &$beforeBalances, &$afterBalances) {
            $beforeBalances = $this->balances();
            $isDebit = $validated['adjustment_type'] === 'debit';

            if ($isDebit && $amount > $beforeBalances['available_funds']) {
                throw ValidationException::withMessages([
                    'amount' => 'Debit adjustment exceeds available funds.',
                ]);
            }

            $adjustment = FundAdjustment::create([
                'adjustment_type' => $validated['adjustment_type'],
                'amount' => $amount,
                'reason' => $validated['reason'],
                'reference_no' => $validated['reference_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'adjusted_by' => $request->user()->id,
                'adjusted_at' => now(),
            ]);

            $afterBalances = $this->balances();
        });

        AuditLogger::log(
            $request,
            'finance.funds.adjust',
            FundAdjustment::class,
            $adjustment->id,
            [
                'available_funds' => $beforeBalances['available_funds'],
                'net_adjustments' => $beforeBalances['net_adjustments'],
            ],
            [
                'adjustment_type' => $adjustment->adjustment_type,
                'amount' => (float) $adjustment->amount,
                'reason' => $adjustment->reason,
                'reference_no' => $adjustment->reference_no,
                'available_funds' => $afterBalances['available_funds'],
                'net_adjustments' => $afterBalances['net_adjustments'],
            ]
        );

        return redirect()->back()->with('success', 'Funds adjusted successfully.');
    }

    public function budgetPlanning(Request $request): Response
    {
        $filters = $this->extractBudgetFilters($request);
        $selectedYear = (int) $filters['fiscal_year'];

        $utilizedByCategory = Payment::query()
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->whereYear('paid_at', $selectedYear)
            ->selectRaw('expense_type, SUM(amount) as total')
            ->groupBy('expense_type')
            ->pluck('total', 'expense_type');

        $pendingByCategory = Payment::query()
            ->where('transaction_type', 'expense')
            ->whereIn('workflow_status', ['requested', 'approved'])
            ->whereYear('paid_at', $selectedYear)
            ->selectRaw('expense_type, SUM(amount) as total')
            ->groupBy('expense_type')
            ->pluck('total', 'expense_type');

        $pendingRequestsByCategory = DisbursementRequest::query()
            ->whereIn('status', ['requested', 'approved'])
            ->whereYear('requested_at', $selectedYear)
            ->selectRaw('expense_type, SUM(amount) as total')
            ->groupBy('expense_type')
            ->pluck('total', 'expense_type');

        foreach ($pendingRequestsByCategory as $category => $total) {
            $pendingByCategory[$category] = (float) ($pendingByCategory[$category] ?? 0) + (float) $total;
        }

        $allocations = $this->buildBudgetFilteredQuery($filters)
            ->with(['creator:id,name', 'approver:id,name'])
            ->paginate(12)
            ->withQueryString()
            ->through(function (BudgetAllocation $allocation) use ($utilizedByCategory, $pendingByCategory) {
                $effectiveBudget = (float) ($allocation->revised_amount > 0 ? $allocation->revised_amount : $allocation->allocated_amount);
                $utilized = (float) ($utilizedByCategory[$allocation->category] ?? 0);
                $pending = (float) ($pendingByCategory[$allocation->category] ?? 0);

                return [
                    ...$allocation->toArray(),
                    'effective_budget' => $effectiveBudget,
                    'utilized_amount' => $utilized,
                    'pending_amount' => $pending,
                    'available_amount' => $effectiveBudget - $utilized,
                ];
            });

        $yearAllocations = BudgetAllocation::query()
            ->where('fiscal_year', $selectedYear)
            ->get(['allocated_amount', 'revised_amount']);

        $totalBudget = (float) $yearAllocations->sum(fn ($row) => (float) ($row->revised_amount > 0 ? $row->revised_amount : $row->allocated_amount));
        $totalUtilized = (float) $utilizedByCategory->sum();
        $totalPending = (float) $pendingByCategory->sum();

        return Inertia::render('Admin/BudgetPlanning', [
            'filters' => $filters,
            'allocations' => $allocations,
            'summary' => [
                'fiscal_year' => $selectedYear,
                'total_budget' => $totalBudget,
                'total_utilized' => $totalUtilized,
                'total_pending' => $totalPending,
                'total_available' => $totalBudget - $totalUtilized,
            ],
            'categories' => self::BUDGET_CATEGORIES,
            'budgetTypes' => self::BUDGET_TYPES,
            'budgetStatuses' => self::BUDGET_STATUSES,
        ]);
    }

    public function storeBudget(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'budget_type' => ['required', Rule::in(self::BUDGET_TYPES)],
            'category' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'program_name' => ['nullable', 'string', 'max:255'],
            'resolution_no' => ['nullable', 'string', 'max:120'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'revised_amount' => ['nullable', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $allocation = BudgetAllocation::create([
            'fiscal_year' => $validated['fiscal_year'],
            'budget_type' => $validated['budget_type'],
            'category' => $validated['category'],
            'program_name' => $validated['program_name'] ?? null,
            'resolution_no' => $validated['resolution_no'] ?? null,
            'allocated_amount' => $validated['allocated_amount'],
            'revised_amount' => $validated['revised_amount'] ?? $validated['allocated_amount'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'draft',
            'submitted_at' => null,
            'created_by' => $request->user()->id,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.create',
            BudgetAllocation::class,
            $allocation->id,
            null,
            $allocation->only([
                'fiscal_year',
                'budget_type',
                'category',
                'program_name',
                'resolution_no',
                'allocated_amount',
                'revised_amount',
                'remarks',
                'status',
                'submitted_at',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation created.');
    }

    public function updateBudget(Request $request, BudgetAllocation $allocation)
    {
        if (in_array($allocation->status, ['for_council_approval', 'approved'], true)) {
            return redirect()->back()->with('error', 'Budget allocation is already in approval workflow. Use submit/approve/reject actions instead.');
        }

        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'budget_type' => ['required', Rule::in(self::BUDGET_TYPES)],
            'category' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'program_name' => ['nullable', 'string', 'max:255'],
            'resolution_no' => ['nullable', 'string', 'max:120'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'revised_amount' => ['nullable', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['draft', 'rejected'])],
        ]);

        $before = $allocation->only([
            'fiscal_year',
            'budget_type',
            'category',
            'program_name',
            'resolution_no',
            'allocated_amount',
            'revised_amount',
            'remarks',
            'status',
            'submitted_at',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $allocation->update([
            'fiscal_year' => $validated['fiscal_year'],
            'budget_type' => $validated['budget_type'],
            'category' => $validated['category'],
            'program_name' => $validated['program_name'] ?? null,
            'resolution_no' => $validated['resolution_no'] ?? null,
            'allocated_amount' => $validated['allocated_amount'],
            'revised_amount' => $validated['revised_amount'] ?? $validated['allocated_amount'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'] ?? ($allocation->status ?: 'draft'),
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.update',
            BudgetAllocation::class,
            $allocation->id,
            $before,
            $allocation->only([
                'fiscal_year',
                'budget_type',
                'category',
                'program_name',
                'resolution_no',
                'allocated_amount',
                'revised_amount',
                'remarks',
                'status',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation updated.');
    }

    public function destroyBudget(Request $request, BudgetAllocation $allocation)
    {
        if ($allocation->status === 'approved') {
            return redirect()->back()->with('error', 'Approved budget allocations cannot be deleted.');
        }

        $before = $allocation->only([
            'fiscal_year',
            'budget_type',
            'category',
            'program_name',
            'resolution_no',
            'allocated_amount',
            'revised_amount',
            'remarks',
            'status',
            'submitted_at',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $allocationId = $allocation->id;
        $allocation->delete();

        AuditLogger::log(
            $request,
            'finance.budget.delete',
            BudgetAllocation::class,
            $allocationId,
            $before,
            null
        );

        return redirect()->back()->with('success', 'Budget allocation deleted.');
    }

    public function submitBudget(Request $request, BudgetAllocation $allocation)
    {
        if (! in_array($allocation->status, ['draft', 'rejected'], true)) {
            return redirect()->back()->with('error', 'Only draft or rejected allocations can be submitted.');
        }

        $before = $allocation->only([
            'status',
            'submitted_at',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $allocation->update([
            'status' => 'for_council_approval',
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.submit',
            BudgetAllocation::class,
            $allocation->id,
            $before,
            $allocation->only([
                'status',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation submitted for council approval.');
    }

    public function approveBudget(Request $request, BudgetAllocation $allocation)
    {
        if ($allocation->status !== 'for_council_approval') {
            return redirect()->back()->with('error', 'Only allocations waiting for council approval can be approved.');
        }

        $validated = $request->validate([
            'resolution_no' => ['required', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'approved_at' => ['nullable', 'date'],
        ]);

        $before = $allocation->only([
            'status',
            'resolution_no',
            'remarks',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $allocation->update([
            'status' => 'approved',
            'resolution_no' => $validated['resolution_no'],
            'remarks' => $validated['remarks'] ?? $allocation->remarks,
            'approved_by' => $request->user()->id,
            'approved_at' => $validated['approved_at'] ?? now(),
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.approve',
            BudgetAllocation::class,
            $allocation->id,
            $before,
            $allocation->only([
                'status',
                'resolution_no',
                'remarks',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation approved.');
    }

    public function rejectBudget(Request $request, BudgetAllocation $allocation)
    {
        if ($allocation->status !== 'for_council_approval') {
            return redirect()->back()->with('error', 'Only allocations waiting for council approval can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $before = $allocation->only([
            'status',
            'approved_by',
            'approved_at',
            'rejection_reason',
        ]);

        $allocation->update([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.reject',
            BudgetAllocation::class,
            $allocation->id,
            $before,
            $allocation->only([
                'status',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation rejected.');
    }

    public function paymentProcessing(Request $request): Response
    {
        $filters = $this->extractFilters($request, 'paid_at');

        return Inertia::render('Admin/PaymentProcessing', [
            'filters' => $filters,
            'payments' => $this->buildFilteredQuery($filters)
                ->with(['resident:id,first_name,last_name', 'collector:id,name', 'approver:id,name'])
                ->paginate(10)
                ->withQueryString(),
            'disbursementRequests' => DisbursementRequest::query()
                ->with([
                    'budgetAllocation:id,fiscal_year,budget_type,category,program_name',
                    'requester:id,name',
                    'approver:id,name',
                    'rejectedBy:id,name',
                    'releasedPayment:id,or_number,paid_at',
                ])
                ->latest('requested_at')
                ->limit(20)
                ->get(),
            'budgetAllocations' => BudgetAllocation::query()
                ->where('status', 'approved')
                ->orderByDesc('fiscal_year')
                ->orderBy('category')
                ->get(['id', 'fiscal_year', 'budget_type', 'category', 'program_name', 'status']),
            'residents' => Resident::query()
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(300)
                ->get(),
            'summary' => $this->buildSummary($filters['search']),
        ]);
    }

    public function officialReceipts(Request $request): Response
    {
        $filters = $this->extractFilters($request, 'or_number');

        return Inertia::render('Admin/OfficialReceipts', [
            'filters' => $filters,
            'payments' => $this->buildFilteredQuery($filters)
                ->where('transaction_type', 'revenue')
                ->where('workflow_status', 'paid')
                ->whereNotNull('or_number')
                ->with(['resident:id,first_name,last_name', 'collector:id,name'])
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function collectionReports(Request $request): Response
    {
        $filters = $this->extractFilters($request, 'paid_at');
        $baseQuery = $this->applySearch(Payment::query(), $filters['search']);
        $baseQuery = $this->applyTypeFilters($baseQuery, $filters);

        $serviceTotals = (clone $baseQuery)
            ->selectRaw('transaction_type, COALESCE(revenue_source, expense_type, service_type) as category, COUNT(*) as transactions_count, SUM(amount) as total_amount')
            ->groupBy('transaction_type', 'category')
            ->orderByDesc('total_amount')
            ->get();

        return Inertia::render('Admin/CollectionReports', [
            'filters' => $filters,
            'payments' => $this->buildFilteredQuery($filters)
                ->with(['resident:id,first_name,last_name', 'collector:id,name'])
                ->paginate(12)
                ->withQueryString(),
            'summary' => $this->buildSummary($filters['search']),
            'serviceTotals' => $serviceTotals,
        ]);
    }

    public function transactionHistory(Request $request): Response
    {
        $filters = $this->extractFilters($request, 'paid_at');

        return Inertia::render('Admin/TransactionHistory', [
            'filters' => $filters,
            'payments' => $this->buildFilteredQuery($filters)
                ->with(['resident:id,first_name,last_name', 'collector:id,name'])
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function financialSummary(Request $request): Response
    {
        $serviceTotals = Payment::query()
            ->selectRaw('transaction_type, COALESCE(revenue_source, expense_type, service_type) as category, COUNT(*) as transactions_count, SUM(amount) as total_amount')
            ->groupBy('transaction_type', 'category')
            ->orderByDesc('total_amount')
            ->get();

        $monthlyRows = Payment::query()
            ->where('workflow_status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->orderBy('paid_at')
            ->get(['paid_at', 'amount', 'transaction_type']);

        $monthlyTotals = $monthlyRows
            ->groupBy(fn ($row) => optional($row->paid_at)->format('Y-m'))
            ->map(function ($items, $month) {
                $revenue = (float) $items->where('transaction_type', 'revenue')->sum('amount');
                $expense = (float) $items->where('transaction_type', 'expense')->sum('amount');
                return [
                    'month' => $month,
                    'revenue_total' => $revenue,
                    'expense_total' => $expense,
                    'total_amount' => $revenue - $expense,
                    'transactions_count' => (int) $items->count(),
                ];
            })
            ->values();

        return Inertia::render('Admin/FinancialSummary', [
            'summary' => $this->buildSummary(''),
            'serviceTotals' => $serviceTotals,
            'monthlyTotals' => $monthlyTotals,
        ]);
    }

    public function storeDisbursementRequest(Request $request)
    {
        $validated = $request->validate([
            'budget_allocation_id' => ['nullable', 'exists:budget_allocations,id'],
            'request_reference' => ['nullable', 'string', 'max:120', Rule::unique('disbursement_requests', 'request_reference')],
            'expense_type' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'purpose' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_number' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $allocation = null;
        if (! empty($validated['budget_allocation_id'])) {
            $allocation = BudgetAllocation::query()->find($validated['budget_allocation_id']);
            if (! $allocation || $allocation->status !== 'approved') {
                throw ValidationException::withMessages([
                    'budget_allocation_id' => 'Selected budget allocation must be approved.',
                ]);
            }

            if ($allocation->category !== $validated['expense_type']) {
                throw ValidationException::withMessages([
                    'expense_type' => 'Expense type must match the selected budget allocation category.',
                ]);
            }
        }

        $requestReference = $validated['request_reference']
            ?? 'DR-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $disbursementRequest = DisbursementRequest::create([
            'budget_allocation_id' => $allocation?->id,
            'request_reference' => $requestReference,
            'expense_type' => $validated['expense_type'],
            'purpose' => $validated['purpose'],
            'amount' => $validated['amount'],
            'status' => 'requested',
            'requested_by' => $request->user()->id,
            'requested_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'released_payment_id' => null,
            'voucher_number' => $validated['voucher_number'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.disbursement.request',
            DisbursementRequest::class,
            $disbursementRequest->id,
            null,
            $disbursementRequest->only([
                'budget_allocation_id',
                'request_reference',
                'expense_type',
                'purpose',
                'amount',
                'status',
                'requested_by',
                'requested_at',
                'voucher_number',
                'remarks',
            ])
        );

        return redirect()->back()->with('success', 'Disbursement request submitted.');
    }

    public function approveDisbursementRequest(Request $request, DisbursementRequest $disbursementRequest)
    {
        if ($disbursementRequest->status !== 'requested') {
            return redirect()->back()->with('error', 'Only requested disbursements can be approved.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'approved_at' => ['nullable', 'date'],
        ]);

        $before = $disbursementRequest->only([
            'status',
            'approved_by',
            'approved_at',
            'remarks',
            'rejection_reason',
        ]);

        $disbursementRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => $validated['approved_at'] ?? now(),
            'remarks' => $validated['remarks'] ?? $disbursementRequest->remarks,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.disbursement.approve',
            DisbursementRequest::class,
            $disbursementRequest->id,
            $before,
            $disbursementRequest->only([
                'status',
                'approved_by',
                'approved_at',
                'remarks',
                'rejection_reason',
            ])
        );

        return redirect()->back()->with('success', 'Disbursement request approved.');
    }

    public function rejectDisbursementRequest(Request $request, DisbursementRequest $disbursementRequest)
    {
        if (! in_array($disbursementRequest->status, ['requested', 'approved'], true)) {
            return redirect()->back()->with('error', 'Only requested or approved disbursements can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $before = $disbursementRequest->only([
            'status',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'approved_by',
            'approved_at',
        ]);

        $disbursementRequest->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => null,
            'approved_at' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.disbursement.reject',
            DisbursementRequest::class,
            $disbursementRequest->id,
            $before,
            $disbursementRequest->only([
                'status',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'approved_by',
                'approved_at',
            ])
        );

        return redirect()->back()->with('success', 'Disbursement request rejected.');
    }

    public function releaseDisbursementRequest(Request $request, DisbursementRequest $disbursementRequest)
    {
        if ($disbursementRequest->status !== 'approved') {
            return redirect()->back()->with('error', 'Only approved disbursements can be released.');
        }

        $validated = $request->validate([
            'or_number' => ['required', 'string', 'max:100', Rule::unique('payments', 'or_number')],
            'description' => ['nullable', 'string', 'max:255'],
            'voucher_number' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = (float) $disbursementRequest->amount;
        $impact = $this->ledgerImpactByInput('expense', 'paid', $amount);
        if (($this->balances()['available_funds'] + $impact) < 0) {
            throw ValidationException::withMessages([
                'or_number' => 'Disbursement release exceeds currently available funds.',
            ]);
        }

        $before = $disbursementRequest->only([
            'status',
            'released_payment_id',
            'voucher_number',
        ]);

        $payment = null;

        DB::transaction(function () use ($request, $disbursementRequest, $validated, &$payment): void {
            $payment = Payment::create([
                'resident_id' => null,
                'certificate_id' => null,
                'disbursement_request_id' => $disbursementRequest->id,
                'budget_allocation_id' => $disbursementRequest->budget_allocation_id,
                'collected_by' => $request->user()->id,
                'approved_by' => $disbursementRequest->approved_by ?? $request->user()->id,
                'or_number' => $validated['or_number'],
                'transaction_type' => 'expense',
                'revenue_source' => null,
                'expense_type' => $disbursementRequest->expense_type,
                'workflow_status' => 'paid',
                'request_reference' => $disbursementRequest->request_reference,
                'voucher_number' => $validated['voucher_number'] ?? $disbursementRequest->voucher_number,
                'service_type' => 'disbursement',
                'description' => $validated['description'] ?? $disbursementRequest->purpose,
                'amount' => $disbursementRequest->amount,
                'paid_at' => $validated['paid_at'] ?? now(),
                'approved_at' => $disbursementRequest->approved_at ?? now(),
                'notes' => $validated['notes'] ?? $disbursementRequest->remarks,
            ]);

            $disbursementRequest->update([
                'status' => 'released',
                'released_payment_id' => $payment->id,
                'voucher_number' => $validated['voucher_number'] ?? $disbursementRequest->voucher_number,
                'remarks' => $validated['notes'] ?? $disbursementRequest->remarks,
            ]);
        });

        AuditLogger::log(
            $request,
            'finance.disbursement.release',
            DisbursementRequest::class,
            $disbursementRequest->id,
            $before,
            $disbursementRequest->only([
                'status',
                'released_payment_id',
                'voucher_number',
            ])
        );

        AuditLogger::log(
            $request,
            'finance.payment.create',
            Payment::class,
            $payment->id,
            null,
            $payment->only([
                'disbursement_request_id',
                'budget_allocation_id',
                'or_number',
                'transaction_type',
                'expense_type',
                'workflow_status',
                'request_reference',
                'voucher_number',
                'description',
                'amount',
                'paid_at',
                'approved_at',
                'notes',
            ])
        );

        return redirect()->back()->with('success', 'Disbursement released and posted to payments.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request, 'paid_at');

        $payments = $this->buildFilteredQuery($filters)
            ->with([
                'resident:id,first_name,last_name',
                'collector:id,name',
            ])
            ->get();

        $filename = 'financial-report-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Transaction Type',
                'Workflow Status',
                'OR Number',
                'Disbursement Ref',
                'Budget Allocation ID',
                'Revenue Source',
                'Expense Type',
                'Reference',
                'Voucher',
                'Resident',
                'Service Type',
                'Description',
                'Amount',
                'Paid At',
                'Collector',
                'Notes',
            ]);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->transaction_type,
                    $payment->workflow_status,
                    $payment->or_number,
                    $payment->disbursement_request_id,
                    $payment->budget_allocation_id,
                    $payment->revenue_source,
                    $payment->expense_type,
                    $payment->request_reference,
                    $payment->voucher_number,
                    $payment->resident
                        ? "{$payment->resident->last_name}, {$payment->resident->first_name}"
                        : '',
                    $payment->service_type,
                    $payment->description,
                    number_format((float) $payment->amount, 2, '.', ''),
                    optional($payment->paid_at)->format('Y-m-d H:i:s'),
                    $payment->collector?->name ?? '',
                    $payment->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    public function receipt(Request $request, Payment $payment)
    {
        abort_if($payment->transaction_type !== 'revenue', 404);

        $payment->load([
            'resident:id,first_name,last_name',
            'collector:id,name',
        ]);

        return response()->view('receipts.official', [
            'payment' => $payment,
            'issuedBy' => $request->user()?->name ?? 'System',
            'barangayName' => SystemSetting::current()->barangay_name,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayment($request);

        $transactionType = (string) $validated['transaction_type'];
        $workflowStatus = (string) $validated['workflow_status'];
        $amount = (float) $validated['amount'];

        $impact = $this->ledgerImpactByInput($transactionType, $workflowStatus, $amount);
        if ($impact < 0 && ($this->balances()['available_funds'] + $impact) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense exceeds currently available funds.',
            ]);
        }

        $approvedBy = in_array($workflowStatus, ['approved', 'paid'], true) ? $request->user()->id : null;
        $approvedAt = in_array($workflowStatus, ['approved', 'paid'], true)
            ? ($validated['approved_at'] ?? now())
            : null;

        $payment = Payment::create([
            'resident_id' => $validated['resident_id'] ?? null,
            'certificate_id' => null,
            'disbursement_request_id' => $validated['disbursement_request_id'] ?? null,
            'budget_allocation_id' => $validated['budget_allocation_id'] ?? null,
            'collected_by' => $request->user()->id,
            'approved_by' => $approvedBy,
            'or_number' => $validated['or_number'],
            'transaction_type' => $transactionType,
            'revenue_source' => $transactionType === 'revenue' ? $validated['revenue_source'] : null,
            'expense_type' => $transactionType === 'expense' ? $validated['expense_type'] : null,
            'workflow_status' => $workflowStatus,
            'request_reference' => $validated['request_reference'] ?? null,
            'voucher_number' => $validated['voucher_number'] ?? null,
            'service_type' => $validated['service_type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'approved_at' => $approvedAt,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            $request,
            'finance.payment.create',
            Payment::class,
            $payment->id,
            null,
            $payment->only([
                'resident_id',
                'disbursement_request_id',
                'budget_allocation_id',
                'or_number',
                'transaction_type',
                'revenue_source',
                'expense_type',
                'workflow_status',
                'request_reference',
                'voucher_number',
                'service_type',
                'description',
                'amount',
                'paid_at',
                'approved_at',
                'notes',
            ])
        );

        return redirect()->back()->with('success', 'Financial transaction recorded successfully.');
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $this->validatePayment($request, $payment);

        $transactionType = (string) $validated['transaction_type'];
        $workflowStatus = (string) $validated['workflow_status'];
        $amount = (float) $validated['amount'];

        $availableWithoutCurrent = $this->balances()['available_funds'] - $this->ledgerImpact($payment);
        $newImpact = $this->ledgerImpactByInput($transactionType, $workflowStatus, $amount);
        if (($availableWithoutCurrent + $newImpact) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense exceeds currently available funds.',
            ]);
        }

        $before = $payment->only([
            'resident_id',
            'disbursement_request_id',
            'budget_allocation_id',
            'or_number',
            'transaction_type',
            'revenue_source',
            'expense_type',
            'workflow_status',
            'request_reference',
            'voucher_number',
            'service_type',
            'description',
            'amount',
            'paid_at',
            'approved_at',
            'notes',
        ]);

        $approvedBy = in_array($workflowStatus, ['approved', 'paid'], true) ? $request->user()->id : null;
        $approvedAt = in_array($workflowStatus, ['approved', 'paid'], true)
            ? ($validated['approved_at'] ?? now())
            : null;

        $payment->update([
            'resident_id' => $validated['resident_id'] ?? null,
            'disbursement_request_id' => $validated['disbursement_request_id'] ?? null,
            'budget_allocation_id' => $validated['budget_allocation_id'] ?? null,
            'approved_by' => $approvedBy,
            'or_number' => $validated['or_number'],
            'transaction_type' => $transactionType,
            'revenue_source' => $transactionType === 'revenue' ? $validated['revenue_source'] : null,
            'expense_type' => $transactionType === 'expense' ? $validated['expense_type'] : null,
            'workflow_status' => $workflowStatus,
            'request_reference' => $validated['request_reference'] ?? null,
            'voucher_number' => $validated['voucher_number'] ?? null,
            'service_type' => $validated['service_type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'approved_at' => $approvedAt,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            $request,
            'finance.payment.update',
            Payment::class,
            $payment->id,
            $before,
            $payment->only([
                'resident_id',
                'disbursement_request_id',
                'budget_allocation_id',
                'or_number',
                'transaction_type',
                'revenue_source',
                'expense_type',
                'workflow_status',
                'request_reference',
                'voucher_number',
                'service_type',
                'description',
                'amount',
                'paid_at',
                'approved_at',
                'notes',
            ])
        );

        return redirect()->back()->with('success', 'Financial transaction updated successfully.');
    }

    public function destroy(Request $request, Payment $payment)
    {
        $before = $payment->only([
            'resident_id',
            'disbursement_request_id',
            'budget_allocation_id',
            'or_number',
            'transaction_type',
            'revenue_source',
            'expense_type',
            'workflow_status',
            'request_reference',
            'voucher_number',
            'service_type',
            'description',
            'amount',
            'paid_at',
            'approved_at',
            'notes',
        ]);

        $paymentId = $payment->id;
        $payment->delete();

        AuditLogger::log(
            $request,
            'finance.payment.delete',
            Payment::class,
            $paymentId,
            $before,
            null
        );

        return redirect()->back()->with('success', 'Payment deleted successfully.');
    }

    private function buildSummary(string $search): array
    {
        $summaryQuery = $this->applySearch(Payment::query(), $search);
        $summaryQuery = $this->applyTypeFilters($summaryQuery, [
            'transaction_type' => null,
            'workflow_status' => null,
        ]);
        $baseFunds = (float) (SystemSetting::current()->barangay_funds ?? 0);
        $revenueCollected = (float) (clone $summaryQuery)
            ->where('transaction_type', 'revenue')
            ->where('workflow_status', 'paid')
            ->sum('amount');
        $expensesPaid = (float) (clone $summaryQuery)
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->sum('amount');
        $pendingPaymentDisbursements = (float) (clone $summaryQuery)
            ->where('transaction_type', 'expense')
            ->whereIn('workflow_status', ['requested', 'approved'])
            ->sum('amount');
        $pendingRequestDisbursements = (float) DisbursementRequest::query()
            ->whereIn('status', ['requested', 'approved'])
            ->sum('amount');
        $pendingDisbursements = $pendingPaymentDisbursements + $pendingRequestDisbursements;
        $balances = $this->balances();

        return [
            'barangay_funds' => $baseFunds,
            'total_collections' => $revenueCollected,
            'total_expenses' => $expensesPaid,
            'pending_disbursements' => $pendingDisbursements,
            'total_credits' => $balances['credits_total'],
            'total_debits' => $balances['debits_total'],
            'net_adjustments' => $balances['net_adjustments'],
            'available_funds' => $balances['available_funds'],
            'transactions_count' => (int) $summaryQuery->count(),
            'today_collections' => (float) Payment::query()
                ->where('transaction_type', 'revenue')
                ->where('workflow_status', 'paid')
                ->whereDate('paid_at', now()->toDateString())
                ->sum('amount'),
            'today_disbursements' => (float) Payment::query()
                ->where('transaction_type', 'expense')
                ->where('workflow_status', 'paid')
                ->whereDate('paid_at', now()->toDateString())
                ->sum('amount'),
        ];
    }

    private function extractFilters(Request $request, string $defaultSort = 'paid_at'): array
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', $defaultSort);
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'or_number',
            'transaction_type',
            'workflow_status',
            'service_type',
            'amount',
            'paid_at',
            'created_at',
            'resident_name',
        ];

        if (! in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }

        return [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'transaction_type' => $request->query('transaction_type'),
            'workflow_status' => $request->query('workflow_status'),
        ];
    }

    private function buildFilteredQuery(array $filters)
    {
        $search = $filters['search'];
        $sort = $filters['sort'];
        $direction = $filters['direction'];

        $query = $this->applySearch(Payment::query(), $search);
        $query = $this->applyTypeFilters($query, $filters);

        if ($sort === 'resident_name') {
            $query->leftJoin('residents', 'payments.resident_id', '=', 'residents.id')
                ->select('payments.*')
                ->orderBy('residents.last_name', $direction)
                ->orderBy('residents.first_name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query;
    }

    private function applySearch($query, string $search)
    {
        return $query->when($search !== '', function ($builder) use ($search) {
            $builder->where(function ($inner) use ($search) {
                $inner->where('or_number', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhere('workflow_status', 'like', "%{$search}%")
                    ->orWhere('revenue_source', 'like', "%{$search}%")
                    ->orWhere('expense_type', 'like', "%{$search}%")
                    ->orWhere('request_reference', 'like', "%{$search}%")
                    ->orWhere('voucher_number', 'like', "%{$search}%")
                    ->orWhere('service_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($resident) use ($search) {
                        $resident->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        });
    }

    private function balances(): array
    {
        $baseFunds = (float) (SystemSetting::current()->barangay_funds ?? 0);
        $totalCollections = (float) Payment::query()
            ->where('transaction_type', 'revenue')
            ->where('workflow_status', 'paid')
            ->sum('amount');
        $totalDisbursements = (float) Payment::query()
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->sum('amount');
        $pendingDisbursements = (float) Payment::query()
            ->where('transaction_type', 'expense')
            ->whereIn('workflow_status', ['requested', 'approved'])
            ->sum('amount');
        $pendingDisbursements += (float) DisbursementRequest::query()
            ->whereIn('status', ['requested', 'approved'])
            ->sum('amount');
        $credits = (float) FundAdjustment::query()
            ->where('adjustment_type', 'credit')
            ->sum('amount');
        $debits = (float) FundAdjustment::query()
            ->where('adjustment_type', 'debit')
            ->sum('amount');
        $netAdjustments = $credits - $debits;

        return [
            'base_funds' => $baseFunds,
            'collections_total' => $totalCollections,
            'disbursements_total' => $totalDisbursements,
            'pending_disbursements' => $pendingDisbursements,
            'credits_total' => $credits,
            'debits_total' => $debits,
            'net_adjustments' => $netAdjustments,
            'available_funds' => $baseFunds + $netAdjustments + $totalCollections - $totalDisbursements,
        ];
    }

    private function applyTypeFilters($query, array $filters)
    {
        if (! empty($filters['transaction_type']) && in_array($filters['transaction_type'], ['revenue', 'expense'], true)) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (! empty($filters['workflow_status']) && in_array($filters['workflow_status'], ['requested', 'approved', 'paid', 'rejected'], true)) {
            $query->where('workflow_status', $filters['workflow_status']);
        }

        return $query;
    }

    private function validatePayment(Request $request, ?Payment $payment = null): array
    {
        $validated = $request->validate([
            'resident_id' => ['nullable', 'exists:residents,id'],
            'disbursement_request_id' => ['nullable', 'exists:disbursement_requests,id'],
            'budget_allocation_id' => ['nullable', 'exists:budget_allocations,id'],
            'transaction_type' => ['required', Rule::in(['revenue', 'expense'])],
            'workflow_status' => ['required', Rule::in(['requested', 'approved', 'paid', 'rejected'])],
            'or_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payments', 'or_number')->ignore($payment?->id),
            ],
            'revenue_source' => [
                Rule::requiredIf(fn () => $request->input('transaction_type') === 'revenue'),
                'nullable',
                'string',
                'max:100',
            ],
            'expense_type' => [
                Rule::requiredIf(fn () => $request->input('transaction_type') === 'expense'),
                'nullable',
                'string',
                'max:100',
            ],
            'request_reference' => ['nullable', 'string', 'max:120'],
            'voucher_number' => ['nullable', 'string', 'max:120'],
            'service_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'approved_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($validated['transaction_type'] ?? null) === 'expense' && ! empty($validated['budget_allocation_id'])) {
            $allocation = BudgetAllocation::query()->find($validated['budget_allocation_id']);
            if (! $allocation || $allocation->status !== 'approved') {
                throw ValidationException::withMessages([
                    'budget_allocation_id' => 'Selected budget allocation must be approved.',
                ]);
            }

            if (! empty($validated['expense_type']) && $allocation->category !== $validated['expense_type']) {
                throw ValidationException::withMessages([
                    'expense_type' => 'Expense type must match the selected budget allocation category.',
                ]);
            }
        }

        return $validated;
    }

    private function ledgerImpact(Payment $payment): float
    {
        return $this->ledgerImpactByInput(
            (string) $payment->transaction_type,
            (string) $payment->workflow_status,
            (float) $payment->amount
        );
    }

    private function ledgerImpactByInput(string $transactionType, string $workflowStatus, float $amount): float
    {
        if ($workflowStatus !== 'paid') {
            return 0;
        }

        if ($transactionType === 'revenue') {
            return $amount;
        }

        return $transactionType === 'expense' ? -$amount : 0;
    }

    private function extractBudgetFilters(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'fiscal_year');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['fiscal_year', 'budget_type', 'category', 'allocated_amount', 'revised_amount', 'status', 'approved_at', 'created_at'];
        $fiscalYear = (int) $request->query('fiscal_year', now()->year);

        if (! in_array($sort, $sortable, true)) {
            $sort = 'fiscal_year';
        }

        return [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'fiscal_year' => $fiscalYear,
        ];
    }

    private function buildBudgetFilteredQuery(array $filters)
    {
        return BudgetAllocation::query()
            ->where('fiscal_year', $filters['fiscal_year'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($inner) use ($filters) {
                    $inner->where('category', 'like', '%'.$filters['search'].'%')
                        ->orWhere('budget_type', 'like', '%'.$filters['search'].'%')
                        ->orWhere('program_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('resolution_no', 'like', '%'.$filters['search'].'%')
                        ->orWhere('rejection_reason', 'like', '%'.$filters['search'].'%')
                        ->orWhere('remarks', 'like', '%'.$filters['search'].'%');
                });
            })
            ->orderBy($filters['sort'], $filters['direction']);
    }
}
