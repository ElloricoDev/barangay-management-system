<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
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
    private const BUDGET_CATEGORIES = [
        'administrative',
        'operations',
        'social_services',
        'infrastructure',
        'contingency',
        'other_expense',
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

        $allocations = $this->buildBudgetFilteredQuery($filters)
            ->with('creator:id,name')
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
        ]);
    }

    public function storeBudget(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'category' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'program_name' => ['nullable', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'revised_amount' => ['nullable', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);

        $allocation = BudgetAllocation::create([
            'fiscal_year' => $validated['fiscal_year'],
            'category' => $validated['category'],
            'program_name' => $validated['program_name'] ?? null,
            'allocated_amount' => $validated['allocated_amount'],
            'revised_amount' => $validated['revised_amount'] ?? $validated['allocated_amount'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.create',
            BudgetAllocation::class,
            $allocation->id,
            null,
            $allocation->only([
                'fiscal_year',
                'category',
                'program_name',
                'allocated_amount',
                'revised_amount',
                'remarks',
                'status',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation created.');
    }

    public function updateBudget(Request $request, BudgetAllocation $allocation)
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'category' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'program_name' => ['nullable', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'revised_amount' => ['nullable', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);

        $before = $allocation->only([
            'fiscal_year',
            'category',
            'program_name',
            'allocated_amount',
            'revised_amount',
            'remarks',
            'status',
        ]);

        $allocation->update([
            'fiscal_year' => $validated['fiscal_year'],
            'category' => $validated['category'],
            'program_name' => $validated['program_name'] ?? null,
            'allocated_amount' => $validated['allocated_amount'],
            'revised_amount' => $validated['revised_amount'] ?? $validated['allocated_amount'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        AuditLogger::log(
            $request,
            'finance.budget.update',
            BudgetAllocation::class,
            $allocation->id,
            $before,
            $allocation->only([
                'fiscal_year',
                'category',
                'program_name',
                'allocated_amount',
                'revised_amount',
                'remarks',
                'status',
            ])
        );

        return redirect()->back()->with('success', 'Budget allocation updated.');
    }

    public function destroyBudget(Request $request, BudgetAllocation $allocation)
    {
        $before = $allocation->only([
            'fiscal_year',
            'category',
            'program_name',
            'allocated_amount',
            'revised_amount',
            'remarks',
            'status',
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

    public function paymentProcessing(Request $request): Response
    {
        $filters = $this->extractFilters($request, 'paid_at');

        return Inertia::render('Admin/PaymentProcessing', [
            'filters' => $filters,
            'payments' => $this->buildFilteredQuery($filters)
                ->with(['resident:id,first_name,last_name', 'collector:id,name', 'approver:id,name'])
                ->paginate(10)
                ->withQueryString(),
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
        $pendingDisbursements = (float) (clone $summaryQuery)
            ->where('transaction_type', 'expense')
            ->whereIn('workflow_status', ['requested', 'approved'])
            ->sum('amount');
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
        return $request->validate([
            'resident_id' => ['nullable', 'exists:residents,id'],
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
        $sortable = ['fiscal_year', 'category', 'allocated_amount', 'revised_amount', 'status', 'created_at'];
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
                        ->orWhere('program_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('remarks', 'like', '%'.$filters['search'].'%');
                });
            })
            ->orderBy($filters['sort'], $filters['direction']);
    }
}
