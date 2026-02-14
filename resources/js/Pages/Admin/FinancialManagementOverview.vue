<script setup>
import { computed } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (permission) => permissions.value.includes(permission);
const canViewPaymentProcessing = computed(() => hasPermission("payment_processing.view"));
const canViewBudgetPlanning = computed(() => hasPermission("finance.budget.view"));
const canViewOfficialReceipts = computed(() => hasPermission("official_receipts.view"));
const canViewCollectionReports = computed(() => hasPermission("collection_reports.view"));
const canViewTransactionHistory = computed(() => hasPermission("transaction_history.view"));
const canViewFinancialSummary = computed(() => hasPermission("financial_summary.view"));
const canViewFinancialStatements = computed(() => hasPermission("finance.statements.view"));
const canAdjustFunds = computed(() => hasPermission("finance.funds.adjust"));

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            barangay_funds: 0,
            total_collections: 0,
            total_expenses: 0,
            pending_disbursements: 0,
            total_credits: 0,
            total_debits: 0,
            net_adjustments: 0,
            available_funds: 0,
            transactions_count: 0,
            today_collections: 0,
            today_disbursements: 0,
        }),
    },
    recentPayments: {
        type: Array,
        default: () => [],
    },
    recentFundAdjustments: {
        type: Array,
        default: () => [],
    },
});

const adjustmentForm = useForm({
    adjustment_type: "credit",
    amount: "",
    reason: "",
    reference_no: "",
    remarks: "",
});

const submitAdjustment = () => {
    if (!canAdjustFunds.value) return;

    adjustmentForm.post("/admin/financial-management/adjust-funds", {
        preserveScroll: true,
        onSuccess: () => adjustmentForm.reset("amount", "reason", "reference_no", "remarks"),
    });
};

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const formatDateTime = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};
</script>

<template>
    <AdminLayout title="Financial Management" :user-name="userName">
        <template #header>
            <PageHeader title="Financial Management" subtitle="Finance workspace overview and quick navigation." icon="financial" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <DashboardStatCard label="Opening Funds" :value="formatMoney(props.summary.barangay_funds)" />
            <DashboardStatCard label="Total Collections" :value="formatMoney(props.summary.total_collections)" />
            <DashboardStatCard label="Total Disbursements" :value="formatMoney(props.summary.total_expenses)" />
            <DashboardStatCard label="Pending Disbursements" :value="formatMoney(props.summary.pending_disbursements)" />
            <DashboardStatCard label="Fund Credits" :value="formatMoney(props.summary.total_credits)" />
            <DashboardStatCard label="Fund Debits" :value="formatMoney(props.summary.total_debits)" />
            <DashboardStatCard label="Net Adjustments" :value="formatMoney(props.summary.net_adjustments)" />
            <DashboardStatCard label="Available Funds" :value="formatMoney(props.summary.available_funds)" />
            <DashboardStatCard label="Transactions" :value="props.summary.transactions_count" />
            <DashboardStatCard label="Today Collections" :value="formatMoney(props.summary.today_collections)" />
            <DashboardStatCard label="Today Disbursements" :value="formatMoney(props.summary.today_disbursements)" />
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <Link v-if="canViewBudgetPlanning" href="/admin/budget-planning" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Budget Planning</Link>
            <Link v-if="canViewPaymentProcessing" href="/admin/payment-processing" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Payment Processing</Link>
            <Link v-if="canViewOfficialReceipts" href="/admin/official-receipts" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Official Receipts</Link>
            <Link v-if="canViewCollectionReports" href="/admin/collection-reports" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Collection Reports</Link>
            <Link v-if="canViewTransactionHistory" href="/admin/transaction-history" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Transaction History</Link>
            <Link v-if="canViewFinancialSummary" href="/admin/financial-summary" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Financial Summary</Link>
            <Link v-if="canViewFinancialStatements" href="/admin/financial-statements" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Financial Statements</Link>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Recent Transactions</h3>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="payment in props.recentPayments" :key="payment.id" class="flex items-center justify-between">
                        <span>OR {{ payment.or_number }} - {{ payment.service_type }}</span>
                        <span class="font-medium">{{ formatMoney(payment.amount) }}</span>
                    </li>
                    <li v-if="props.recentPayments.length === 0" class="text-slate-500">No recent transactions.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Fund Adjustment Ledger</h3>
                    <span class="text-xs text-slate-500">Credit/Debit audit trail</span>
                </div>
                <div class="ui-table-wrap max-h-72">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.recentFundAdjustments" :key="item.id">
                                <td>{{ formatDateTime(item.adjusted_at) }}</td>
                                <td>
                                    <span :class="item.adjustment_type === 'credit' ? 'text-emerald-700' : 'text-rose-700'" class="font-medium">
                                        {{ item.adjustment_type === "credit" ? "Credit" : "Debit" }}
                                    </span>
                                </td>
                                <td class="font-medium">{{ formatMoney(item.amount) }}</td>
                                <td>{{ item.reason }}</td>
                                <td>{{ item.adjusted_by?.name ?? "-" }}</td>
                            </tr>
                            <tr v-if="props.recentFundAdjustments.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">No fund adjustments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="canAdjustFunds" class="mt-4 rounded-lg border border-slate-200 p-4">
            <h3 class="font-semibold text-slate-800">Adjust Funds</h3>
            <p class="mt-1 text-xs text-slate-500">Use credit for additional budget and debit for approved deductions. Every entry is audited.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Adjustment Type</label>
                    <select v-model="adjustmentForm.adjustment_type" class="ui-input">
                        <option value="credit">Credit (Add Funds)</option>
                        <option value="debit">Debit (Deduct Funds)</option>
                    </select>
                    <p v-if="adjustmentForm.errors.adjustment_type" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.adjustment_type }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Amount (PHP)</label>
                    <input v-model="adjustmentForm.amount" type="number" min="0.01" step="0.01" class="ui-input" />
                    <p v-if="adjustmentForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Reason</label>
                    <input v-model="adjustmentForm.reason" type="text" class="ui-input" placeholder="e.g. Supplemental budget release" />
                    <p v-if="adjustmentForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.reason }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Reference No. (optional)</label>
                    <input v-model="adjustmentForm.reference_no" type="text" class="ui-input" placeholder="Voucher / resolution / memo no." />
                    <p v-if="adjustmentForm.errors.reference_no" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.reference_no }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Remarks (optional)</label>
                    <textarea v-model="adjustmentForm.remarks" rows="2" class="ui-input" placeholder="Additional context for review and audit"></textarea>
                    <p v-if="adjustmentForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.remarks }}</p>
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" :disabled="adjustmentForm.processing" @click="submitAdjustment">
                    Save Adjustment
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
