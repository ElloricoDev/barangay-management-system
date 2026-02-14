<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\DisbursementRequest;
use App\Models\Document;
use App\Models\FinancialSubmission;
use App\Models\FundAdjustment;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\SystemSetting;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
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

    private const SUBMISSION_AGENCIES = [
        'coa',
        'dbm',
    ];

    private const SUBMISSION_REPORT_TYPES = [
        'annual_budget',
        'supplemental_budget',
        'trial_balance',
        'statement_of_expenditures',
        'cash_receipts_disbursements',
        'collection_report',
        'other',
    ];

    private const SUBMISSION_STATUSES = [
        'draft',
        'submitted',
        'acknowledged',
        'returned',
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
                    'requestDocument:id,title,module,original_name,status',
                    'voucherDocument:id,title,module,original_name,status',
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
            'financeDocuments' => Document::query()
                ->where('status', 'approved')
                ->where(function ($query) {
                    $query->whereIn('module', ['financial', 'other'])
                        ->orWhereNull('module');
                })
                ->with('uploader:id,name')
                ->latest()
                ->limit(300)
                ->get(['id', 'title', 'module', 'original_name', 'status', 'uploaded_by', 'created_at']),
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

    public function financialStatements(Request $request): Response
    {
        $filters = $this->extractStatementFilters($request);
        [$dateFrom, $dateTo] = $this->statementDateRange($filters);

        return Inertia::render('Admin/FinancialStatements', [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'statement' => $filters['statement'],
            ],
            'trialBalance' => $this->buildTrialBalance($dateFrom, $dateTo),
            'statementOfExpenditures' => $this->buildStatementOfExpenditures($dateFrom, $dateTo),
            'cashReceiptsDisbursements' => $this->buildCashReceiptsDisbursements($dateFrom, $dateTo),
        ]);
    }

    public function exportFinancialStatements(Request $request): StreamedResponse
    {
        $filters = $this->extractStatementFilters($request);
        [$dateFrom, $dateTo] = $this->statementDateRange($filters);

        $statement = in_array($filters['statement'], ['all', 'trial_balance', 'statement_of_expenditures', 'cash_receipts_disbursements'], true)
            ? $filters['statement']
            : 'all';

        $trialBalance = $this->buildTrialBalance($dateFrom, $dateTo);
        $statementOfExpenditures = $this->buildStatementOfExpenditures($dateFrom, $dateTo);
        $cashReceiptsDisbursements = $this->buildCashReceiptsDisbursements($dateFrom, $dateTo);

        $filename = 'financial-statements-'.$statement.'-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use (
            $statement,
            $dateFrom,
            $dateTo,
            $trialBalance,
            $statementOfExpenditures,
            $cashReceiptsDisbursements
        ) {
            $format = fn ($value) => number_format((float) $value, 2, '.', '');

            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Barangay Financial Statements']);
            fputcsv($handle, ['Period', $dateFrom->toDateString().' to '.$dateTo->toDateString()]);
            fputcsv($handle, []);

            if (in_array($statement, ['all', 'trial_balance'], true)) {
                fputcsv($handle, ['Trial Balance']);
                fputcsv($handle, ['Account', 'Debit', 'Credit', 'Transactions']);
                foreach ($trialBalance['rows'] as $row) {
                    fputcsv($handle, [
                        $row['account'],
                        $row['debit'] > 0 ? $format($row['debit']) : '',
                        $row['credit'] > 0 ? $format($row['credit']) : '',
                        $row['transactions'] > 0 ? $row['transactions'] : '',
                    ]);
                }
                fputcsv($handle, ['TOTAL', $format($trialBalance['totals']['debit_total']), $format($trialBalance['totals']['credit_total']), '']);
                fputcsv($handle, ['Difference', $format($trialBalance['totals']['difference']), '', '']);
                fputcsv($handle, []);
            }

            if (in_array($statement, ['all', 'statement_of_expenditures'], true)) {
                fputcsv($handle, ['Statement of Expenditures']);
                fputcsv($handle, ['Expense Type', 'Transactions', 'Total Amount']);
                foreach ($statementOfExpenditures['by_type'] as $row) {
                    fputcsv($handle, [
                        $row['expense_type'],
                        $row['transactions'],
                        $format($row['total_amount']),
                    ]);
                }
                fputcsv($handle, ['TOTAL', $statementOfExpenditures['totals']['transactions'], $format($statementOfExpenditures['totals']['total_amount'])]);
                fputcsv($handle, []);
                fputcsv($handle, ['Monthly Expenditures']);
                fputcsv($handle, ['Month', 'Transactions', 'Total Amount']);
                foreach ($statementOfExpenditures['by_month'] as $row) {
                    fputcsv($handle, [
                        $row['month'],
                        $row['transactions'],
                        $format($row['total_amount']),
                    ]);
                }
                fputcsv($handle, []);
            }

            if (in_array($statement, ['all', 'cash_receipts_disbursements'], true)) {
                fputcsv($handle, ['Cash Receipts and Disbursements']);
                fputcsv($handle, ['Opening Balance', $format($cashReceiptsDisbursements['opening_balance'])]);
                fputcsv($handle, []);
                fputcsv($handle, [
                    'Month',
                    'Opening Balance',
                    'Revenue Receipts',
                    'Credit Adjustments',
                    'Total Receipts',
                    'Expense Disbursements',
                    'Debit Adjustments',
                    'Total Disbursements',
                    'Net Change',
                    'Closing Balance',
                ]);
                foreach ($cashReceiptsDisbursements['rows'] as $row) {
                    fputcsv($handle, [
                        $row['month'],
                        $format($row['opening_balance']),
                        $format($row['revenue_receipts']),
                        $format($row['credit_adjustments']),
                        $format($row['total_receipts']),
                        $format($row['expense_disbursements']),
                        $format($row['debit_adjustments']),
                        $format($row['total_disbursements']),
                        $format($row['net_change']),
                        $format($row['closing_balance']),
                    ]);
                }
                fputcsv($handle, [
                    'TOTAL',
                    '',
                    $format($cashReceiptsDisbursements['totals']['revenue_receipts']),
                    $format($cashReceiptsDisbursements['totals']['credit_adjustments']),
                    $format($cashReceiptsDisbursements['totals']['total_receipts']),
                    $format($cashReceiptsDisbursements['totals']['expense_disbursements']),
                    $format($cashReceiptsDisbursements['totals']['debit_adjustments']),
                    $format($cashReceiptsDisbursements['totals']['total_disbursements']),
                    $format($cashReceiptsDisbursements['totals']['net_change']),
                    $format($cashReceiptsDisbursements['closing_balance']),
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    public function financialSubmissions(Request $request): Response
    {
        $filters = $this->extractSubmissionFilters($request);

        return Inertia::render('Admin/FinancialSubmissions', [
            'filters' => $filters,
            'submissions' => $this->buildSubmissionFilteredQuery($filters)
                ->with([
                    'document:id,title,module,original_name,status',
                    'creator:id,name',
                    'submitter:id,name',
                    'reviewer:id,name',
                ])
                ->paginate(12)
                ->withQueryString(),
            'submissionAgencies' => self::SUBMISSION_AGENCIES,
            'submissionReportTypes' => self::SUBMISSION_REPORT_TYPES,
            'submissionStatuses' => self::SUBMISSION_STATUSES,
            'financeDocuments' => Document::query()
                ->where('status', 'approved')
                ->where(function ($query) {
                    $query->whereIn('module', ['financial', 'other'])
                        ->orWhereNull('module');
                })
                ->with('uploader:id,name')
                ->latest()
                ->limit(300)
                ->get(['id', 'title', 'module', 'original_name', 'status', 'uploaded_by', 'created_at']),
        ]);
    }

    public function storeFinancialSubmission(Request $request)
    {
        $validated = $request->validate([
            'agency' => ['required', Rule::in(self::SUBMISSION_AGENCIES)],
            'report_type' => ['required', Rule::in(self::SUBMISSION_REPORT_TYPES)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reference_no' => ['nullable', 'string', 'max:120', Rule::unique('financial_submissions', 'reference_no')],
            'document_id' => ['required', 'exists:documents,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $document = $this->validatedApprovedDocument((int) $validated['document_id'], 'document_id');
        if (! in_array((string) $document->module, ['financial', 'other', ''], true) && $document->module !== null) {
            throw ValidationException::withMessages([
                'document_id' => 'Submission document must be tagged as financial or other.',
            ]);
        }

        $referenceNo = $validated['reference_no']
            ?? 'FS-'.strtoupper((string) $validated['agency']).'-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $submission = FinancialSubmission::create([
            'agency' => $validated['agency'],
            'report_type' => $validated['report_type'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'reference_no' => $referenceNo,
            'status' => 'draft',
            'document_id' => $document->id,
            'created_by' => $request->user()->id,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'remarks' => $validated['remarks'] ?? null,
            'review_notes' => null,
        ]);

        AuditLogger::log(
            $request,
            'finance.submissions.create',
            FinancialSubmission::class,
            $submission->id,
            null,
            $submission->only([
                'agency',
                'report_type',
                'period_start',
                'period_end',
                'reference_no',
                'status',
                'document_id',
                'remarks',
            ])
        );

        return redirect()->back()->with('success', 'Financial submission record created.');
    }

    public function submitFinancialSubmission(Request $request, FinancialSubmission $financialSubmission)
    {
        if (! in_array($financialSubmission->status, ['draft', 'returned'], true)) {
            return redirect()->back()->with('error', 'Only draft or returned submissions can be submitted.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'submitted_at' => ['nullable', 'date'],
        ]);

        $this->validatedApprovedDocument((int) $financialSubmission->document_id, 'document_id');

        $before = $financialSubmission->only([
            'status',
            'submitted_by',
            'submitted_at',
            'reviewed_by',
            'reviewed_at',
            'review_notes',
            'remarks',
        ]);

        $financialSubmission->update([
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'submitted_at' => $validated['submitted_at'] ?? now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'remarks' => $validated['remarks'] ?? $financialSubmission->remarks,
        ]);

        AuditLogger::log(
            $request,
            'finance.submissions.submit',
            FinancialSubmission::class,
            $financialSubmission->id,
            $before,
            $financialSubmission->only([
                'status',
                'submitted_by',
                'submitted_at',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
                'remarks',
            ])
        );

        return redirect()->back()->with('success', 'Financial submission marked as submitted.');
    }

    public function acknowledgeFinancialSubmission(Request $request, FinancialSubmission $financialSubmission)
    {
        if ($financialSubmission->status !== 'submitted') {
            return redirect()->back()->with('error', 'Only submitted records can be acknowledged.');
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
            'reviewed_at' => ['nullable', 'date'],
        ]);

        $before = $financialSubmission->only([
            'status',
            'reviewed_by',
            'reviewed_at',
            'review_notes',
        ]);

        $financialSubmission->update([
            'status' => 'acknowledged',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => $validated['reviewed_at'] ?? now(),
            'review_notes' => $validated['review_notes'] ?? $financialSubmission->review_notes,
        ]);

        AuditLogger::log(
            $request,
            'finance.submissions.acknowledge',
            FinancialSubmission::class,
            $financialSubmission->id,
            $before,
            $financialSubmission->only([
                'status',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
            ])
        );

        return redirect()->back()->with('success', 'Financial submission acknowledged.');
    }

    public function returnFinancialSubmission(Request $request, FinancialSubmission $financialSubmission)
    {
        if ($financialSubmission->status !== 'submitted') {
            return redirect()->back()->with('error', 'Only submitted records can be returned.');
        }

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $before = $financialSubmission->only([
            'status',
            'reviewed_by',
            'reviewed_at',
            'review_notes',
        ]);

        $financialSubmission->update([
            'status' => 'returned',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        AuditLogger::log(
            $request,
            'finance.submissions.return',
            FinancialSubmission::class,
            $financialSubmission->id,
            $before,
            $financialSubmission->only([
                'status',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
            ])
        );

        return redirect()->back()->with('success', 'Financial submission returned for revisions.');
    }

    public function storeDisbursementRequest(Request $request)
    {
        $validated = $request->validate([
            'budget_allocation_id' => ['nullable', 'exists:budget_allocations,id'],
            'request_document_id' => ['required', 'exists:documents,id'],
            'request_reference' => ['nullable', 'string', 'max:120', Rule::unique('disbursement_requests', 'request_reference')],
            'expense_type' => ['required', Rule::in(self::BUDGET_CATEGORIES)],
            'purpose' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_number' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $requestDocument = $this->validatedApprovedDocument((int) $validated['request_document_id'], 'request_document_id');

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
            'request_document_id' => $requestDocument->id,
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
            'voucher_document_id' => null,
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
                'request_document_id',
                'request_reference',
                'expense_type',
                'purpose',
                'amount',
                'status',
                'requested_by',
                'requested_at',
                'voucher_number',
                'voucher_document_id',
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

        if (! $disbursementRequest->request_document_id) {
            throw ValidationException::withMessages([
                'request_document_id' => 'Approved disbursements require an approved request document.',
            ]);
        }
        $this->validatedApprovedDocument((int) $disbursementRequest->request_document_id, 'request_document_id');

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

        if (! $disbursementRequest->request_document_id) {
            throw ValidationException::withMessages([
                'request_document_id' => 'Disbursement release requires an approved request document.',
            ]);
        }
        $this->validatedApprovedDocument((int) $disbursementRequest->request_document_id, 'request_document_id');

        $validated = $request->validate([
            'or_number' => ['required', 'string', 'max:100', Rule::unique('payments', 'or_number')],
            'description' => ['nullable', 'string', 'max:255'],
            'voucher_number' => ['nullable', 'string', 'max:120'],
            'voucher_document_id' => ['required', 'exists:documents,id'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $voucherDocument = $this->validatedApprovedDocument((int) $validated['voucher_document_id'], 'voucher_document_id');

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
            'voucher_document_id',
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
                'voucher_document_id' => $voucherDocument->id,
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
                'voucher_document_id',
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

    private function extractStatementFilters(Request $request): array
    {
        return [
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'statement' => trim((string) $request->query('statement', 'all')),
        ];
    }

    private function statementDateRange(array $filters): array
    {
        $dateFrom = $filters['date_from'] !== ''
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfYear();
        $dateTo = $filters['date_to'] !== ''
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    private function extractSubmissionFilters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['agency', 'report_type', 'status', 'period_start', 'period_end', 'submitted_at', 'reviewed_at', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $agency = trim((string) $request->query('agency', ''));
        if (! in_array($agency, self::SUBMISSION_AGENCIES, true)) {
            $agency = '';
        }

        $reportType = trim((string) $request->query('report_type', ''));
        if (! in_array($reportType, self::SUBMISSION_REPORT_TYPES, true)) {
            $reportType = '';
        }

        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, self::SUBMISSION_STATUSES, true)) {
            $status = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'agency' => $agency,
            'report_type' => $reportType,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function buildSubmissionFilteredQuery(array $filters)
    {
        $search = (string) ($filters['search'] ?? '');

        return FinancialSubmission::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('agency', 'like', "%{$search}%")
                        ->orWhere('report_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhere('review_notes', 'like', "%{$search}%")
                        ->orWhereHas('creator', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('submitter', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('reviewer', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(($filters['agency'] ?? '') !== '', function ($query) use ($filters) {
                $query->where('agency', $filters['agency']);
            })
            ->when(($filters['report_type'] ?? '') !== '', function ($query) use ($filters) {
                $query->where('report_type', $filters['report_type']);
            })
            ->when(($filters['status'] ?? '') !== '', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderBy((string) $filters['sort'], (string) $filters['direction']);
    }

    private function validatedApprovedDocument(int $documentId, string $field): Document
    {
        $document = Document::query()->find($documentId);
        if (! $document) {
            throw ValidationException::withMessages([
                $field => 'Selected document was not found.',
            ]);
        }

        if ($document->status !== 'approved') {
            throw ValidationException::withMessages([
                $field => 'Selected document must be approved before this action.',
            ]);
        }

        return $document;
    }

    private function buildTrialBalance(Carbon $dateFrom, Carbon $dateTo): array
    {
        $openingBalance = $this->openingBalanceAt($dateFrom);

        $revenueRows = Payment::query()
            ->where('transaction_type', 'revenue')
            ->where('workflow_status', 'paid')
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(NULLIF(revenue_source, ""), "other_income") as account')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('account')
            ->orderBy('account')
            ->get();

        $expenseRows = Payment::query()
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(NULLIF(expense_type, ""), "other_expense") as account')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('account')
            ->orderBy('account')
            ->get();

        $creditAdjustments = (float) FundAdjustment::query()
            ->where('adjustment_type', 'credit')
            ->whereBetween('adjusted_at', [$dateFrom, $dateTo])
            ->sum('amount');
        $debitAdjustments = (float) FundAdjustment::query()
            ->where('adjustment_type', 'debit')
            ->whereBetween('adjusted_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $periodRevenue = (float) $revenueRows->sum('total_amount');
        $periodExpense = (float) $expenseRows->sum('total_amount');
        $closingBalance = $openingBalance + $periodRevenue + $creditAdjustments - $periodExpense - $debitAdjustments;

        $rows = [];
        $addRow = function (string $account, float $amount, string $defaultSide, int $transactions = 0) use (&$rows): void {
            if ($amount === 0.0) {
                return;
            }

            $debit = 0.0;
            $credit = 0.0;
            if ($defaultSide === 'debit') {
                if ($amount >= 0) {
                    $debit = $amount;
                } else {
                    $credit = abs($amount);
                }
            } else {
                if ($amount >= 0) {
                    $credit = $amount;
                } else {
                    $debit = abs($amount);
                }
            }

            $rows[] = [
                'account' => $account,
                'debit' => $debit,
                'credit' => $credit,
                'transactions' => $transactions,
            ];
        };

        $addRow('Cash on Hand (Closing)', $closingBalance, 'debit');
        $addRow('Opening Fund Balance', $openingBalance, 'credit');

        foreach ($revenueRows as $row) {
            $addRow('Revenue - '.str_replace('_', ' ', (string) $row->account), (float) $row->total_amount, 'credit', (int) $row->transactions);
        }
        foreach ($expenseRows as $row) {
            $addRow('Expense - '.str_replace('_', ' ', (string) $row->account), (float) $row->total_amount, 'debit', (int) $row->transactions);
        }
        $addRow('Fund Adjustment - Credit', $creditAdjustments, 'credit');
        $addRow('Fund Adjustment - Debit', $debitAdjustments, 'debit');

        $debitTotal = (float) collect($rows)->sum('debit');
        $creditTotal = (float) collect($rows)->sum('credit');

        return [
            'rows' => $rows,
            'totals' => [
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'difference' => $debitTotal - $creditTotal,
            ],
        ];
    }

    private function buildStatementOfExpenditures(Carbon $dateFrom, Carbon $dateTo): array
    {
        $expenseRows = Payment::query()
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->orderBy('paid_at')
            ->get(['id', 'paid_at', 'expense_type', 'description', 'request_reference', 'voucher_number', 'amount']);

        $byType = $expenseRows
            ->groupBy(fn ($row) => (string) ($row->expense_type ?: 'other_expense'))
            ->map(fn ($items, $expenseType) => [
                'expense_type' => $expenseType,
                'transactions' => (int) $items->count(),
                'total_amount' => (float) $items->sum('amount'),
            ])
            ->sortByDesc('total_amount')
            ->values()
            ->all();

        $byMonth = $expenseRows
            ->groupBy(fn ($row) => optional($row->paid_at)->format('Y-m'))
            ->map(fn ($items, $month) => [
                'month' => $month,
                'transactions' => (int) $items->count(),
                'total_amount' => (float) $items->sum('amount'),
            ])
            ->sortBy('month')
            ->values()
            ->all();

        return [
            'by_type' => $byType,
            'by_month' => $byMonth,
            'items' => $expenseRows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'paid_at' => optional($row->paid_at)->toDateTimeString(),
                    'expense_type' => $row->expense_type ?: 'other_expense',
                    'description' => $row->description,
                    'request_reference' => $row->request_reference,
                    'voucher_number' => $row->voucher_number,
                    'amount' => (float) $row->amount,
                ];
            })->values()->all(),
            'totals' => [
                'transactions' => (int) $expenseRows->count(),
                'total_amount' => (float) $expenseRows->sum('amount'),
            ],
        ];
    }

    private function buildCashReceiptsDisbursements(Carbon $dateFrom, Carbon $dateTo): array
    {
        $openingBalance = $this->openingBalanceAt($dateFrom);
        $runningBalance = $openingBalance;

        $paymentRows = Payment::query()
            ->where('workflow_status', 'paid')
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->orderBy('paid_at')
            ->get(['paid_at', 'transaction_type', 'amount']);

        $adjustmentRows = FundAdjustment::query()
            ->whereBetween('adjusted_at', [$dateFrom, $dateTo])
            ->orderBy('adjusted_at')
            ->get(['adjusted_at', 'adjustment_type', 'amount']);

        $monthCursor = $dateFrom->copy()->startOfMonth();
        $monthEnd = $dateTo->copy()->endOfMonth();
        $rows = [];

        while ($monthCursor->lte($monthEnd)) {
            $period = $monthCursor->format('Y-m');

            $revenueReceipts = (float) $paymentRows
                ->filter(fn ($row) => optional($row->paid_at)->format('Y-m') === $period && $row->transaction_type === 'revenue')
                ->sum('amount');
            $expenseDisbursements = (float) $paymentRows
                ->filter(fn ($row) => optional($row->paid_at)->format('Y-m') === $period && $row->transaction_type === 'expense')
                ->sum('amount');
            $creditAdjustments = (float) $adjustmentRows
                ->filter(fn ($row) => optional($row->adjusted_at)->format('Y-m') === $period && $row->adjustment_type === 'credit')
                ->sum('amount');
            $debitAdjustments = (float) $adjustmentRows
                ->filter(fn ($row) => optional($row->adjusted_at)->format('Y-m') === $period && $row->adjustment_type === 'debit')
                ->sum('amount');

            $totalReceipts = $revenueReceipts + $creditAdjustments;
            $totalDisbursements = $expenseDisbursements + $debitAdjustments;
            $netChange = $totalReceipts - $totalDisbursements;
            $closingBalance = $runningBalance + $netChange;

            $rows[] = [
                'month' => $period,
                'opening_balance' => $runningBalance,
                'revenue_receipts' => $revenueReceipts,
                'credit_adjustments' => $creditAdjustments,
                'total_receipts' => $totalReceipts,
                'expense_disbursements' => $expenseDisbursements,
                'debit_adjustments' => $debitAdjustments,
                'total_disbursements' => $totalDisbursements,
                'net_change' => $netChange,
                'closing_balance' => $closingBalance,
            ];

            $runningBalance = $closingBalance;
            $monthCursor->addMonth();
        }

        return [
            'opening_balance' => $openingBalance,
            'rows' => $rows,
            'totals' => [
                'revenue_receipts' => (float) collect($rows)->sum('revenue_receipts'),
                'credit_adjustments' => (float) collect($rows)->sum('credit_adjustments'),
                'total_receipts' => (float) collect($rows)->sum('total_receipts'),
                'expense_disbursements' => (float) collect($rows)->sum('expense_disbursements'),
                'debit_adjustments' => (float) collect($rows)->sum('debit_adjustments'),
                'total_disbursements' => (float) collect($rows)->sum('total_disbursements'),
                'net_change' => (float) collect($rows)->sum('net_change'),
            ],
            'closing_balance' => $runningBalance,
        ];
    }

    private function openingBalanceAt(Carbon $dateFrom): float
    {
        $baseFunds = (float) (SystemSetting::current()->barangay_funds ?? 0);
        $revenueBefore = (float) Payment::query()
            ->where('transaction_type', 'revenue')
            ->where('workflow_status', 'paid')
            ->where('paid_at', '<', $dateFrom)
            ->sum('amount');
        $expenseBefore = (float) Payment::query()
            ->where('transaction_type', 'expense')
            ->where('workflow_status', 'paid')
            ->where('paid_at', '<', $dateFrom)
            ->sum('amount');
        $creditsBefore = (float) FundAdjustment::query()
            ->where('adjustment_type', 'credit')
            ->where('adjusted_at', '<', $dateFrom)
            ->sum('amount');
        $debitsBefore = (float) FundAdjustment::query()
            ->where('adjustment_type', 'debit')
            ->where('adjusted_at', '<', $dateFrom)
            ->sum('amount');

        return $baseFunds + $revenueBefore + $creditsBefore - $expenseBefore - $debitsBefore;
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
