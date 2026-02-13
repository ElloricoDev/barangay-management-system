<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Resident;
use App\Models\SystemSetting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinancialManagementController extends Controller
{
    public function financialManagement(Request $request): Response
    {
        return $this->renderSection($request, 'financial_management');
    }

    public function paymentProcessing(Request $request): Response
    {
        return $this->renderSection($request, 'payment_processing');
    }

    public function officialReceipts(Request $request): Response
    {
        return $this->renderSection($request, 'official_receipts');
    }

    public function collectionReports(Request $request): Response
    {
        return $this->renderSection($request, 'collection_reports');
    }

    public function transactionHistory(Request $request): Response
    {
        return $this->renderSection($request, 'transaction_history');
    }

    public function financialSummary(Request $request): Response
    {
        return $this->renderSection($request, 'financial_summary');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $payments = $this->buildFilteredQuery($request)
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
                'OR Number',
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
                    $payment->or_number,
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
        $validated = $request->validate([
            'resident_id' => ['nullable', 'exists:residents,id'],
            'or_number' => ['required', 'string', 'max:100', Rule::unique('payments', 'or_number')],
            'service_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = Payment::create([
            'resident_id' => $validated['resident_id'] ?? null,
            'certificate_id' => null,
            'collected_by' => $request->user()->id,
            'or_number' => $validated['or_number'],
            'service_type' => $validated['service_type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            $request,
            'finance.payment.create',
            Payment::class,
            $payment->id,
            null,
            $payment->only(['resident_id', 'or_number', 'service_type', 'description', 'amount', 'paid_at', 'notes'])
        );

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'resident_id' => ['nullable', 'exists:residents,id'],
            'or_number' => ['required', 'string', 'max:100', Rule::unique('payments', 'or_number')->ignore($payment->id)],
            'service_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $payment->only(['resident_id', 'or_number', 'service_type', 'description', 'amount', 'paid_at', 'notes']);

        $payment->update([
            'resident_id' => $validated['resident_id'] ?? null,
            'or_number' => $validated['or_number'],
            'service_type' => $validated['service_type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log(
            $request,
            'finance.payment.update',
            Payment::class,
            $payment->id,
            $before,
            $payment->only(['resident_id', 'or_number', 'service_type', 'description', 'amount', 'paid_at', 'notes'])
        );

        return redirect()->back()->with('success', 'Payment updated successfully.');
    }

    public function destroy(Request $request, Payment $payment)
    {
        $before = $payment->only(['resident_id', 'or_number', 'service_type', 'description', 'amount', 'paid_at', 'notes']);

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

    private function renderSection(Request $request, string $section): Response
    {
        $filters = $this->extractFilters($request);
        $search = $filters['search'];

        $payments = $this->buildFilteredQuery($request)
            ->with([
                'resident:id,first_name,last_name',
                'collector:id,name',
            ])
            ->paginate(10)
            ->withQueryString();

        $summaryQuery = Payment::query();
        if ($search !== '') {
            $summaryQuery->where(function ($inner) use ($search) {
                $inner->where('or_number', 'like', "%{$search}%")
                    ->orWhere('service_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $summary = [
            'total_collections' => (float) $summaryQuery->sum('amount'),
            'transactions_count' => (int) $summaryQuery->count(),
            'today_collections' => (float) Payment::query()
                ->whereDate('paid_at', now()->toDateString())
                ->sum('amount'),
        ];

        return Inertia::render('Admin/FinancialManagement', [
            'activeSection' => $section,
            'filters' => $filters,
            'payments' => $payments,
            'residents' => Resident::query()
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(300)
                ->get(),
            'summary' => $summary,
        ]);
    }

    private function extractFilters(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'paid_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['or_number', 'service_type', 'amount', 'paid_at', 'created_at', 'resident_name'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'paid_at';
        }

        return [
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function buildFilteredQuery(Request $request)
    {
        $filters = $this->extractFilters($request);
        $search = $filters['search'];
        $sort = $filters['sort'];
        $direction = $filters['direction'];

        $query = Payment::query()
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('or_number', 'like', "%{$search}%")
                        ->orWhere('service_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('resident', function ($resident) use ($search) {
                            $resident->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            });

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
}
