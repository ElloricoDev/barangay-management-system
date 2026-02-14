<script setup>
import { computed, reactive } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canExport = computed(() => permissions.value.includes("finance.reports.export"));

const props = defineProps({
    filters: { type: Object, default: () => ({ date_from: "", date_to: "", statement: "all" }) },
    trialBalance: { type: Object, default: () => ({ rows: [], totals: { debit_total: 0, credit_total: 0, difference: 0 } }) },
    statementOfExpenditures: { type: Object, default: () => ({ by_type: [], by_month: [], totals: { transactions: 0, total_amount: 0 } }) },
    cashReceiptsDisbursements: {
        type: Object,
        default: () => ({
            opening_balance: 0,
            rows: [],
            totals: {
                revenue_receipts: 0,
                credit_adjustments: 0,
                total_receipts: 0,
                expense_disbursements: 0,
                debit_adjustments: 0,
                total_disbursements: 0,
                net_change: 0,
            },
            closing_balance: 0,
        }),
    },
});

const form = reactive({
    date_from: props.filters?.date_from ?? "",
    date_to: props.filters?.date_to ?? "",
    statement: props.filters?.statement ?? "all",
});

const showTrialBalance = computed(() => form.statement === "all" || form.statement === "trial_balance");
const showExpenditures = computed(() => form.statement === "all" || form.statement === "statement_of_expenditures");
const showCashbook = computed(() => form.statement === "all" || form.statement === "cash_receipts_disbursements");

const applyFilters = () => {
    router.get("/admin/financial-statements", form, { preserveState: true, preserveScroll: true, replace: true });
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (form.date_from) params.set("date_from", form.date_from);
    if (form.date_to) params.set("date_to", form.date_to);
    if (form.statement) params.set("statement", form.statement);
    const query = params.toString();
    return query ? `/admin/financial-statements/export?${query}` : "/admin/financial-statements/export";
});

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));

const pretty = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <AdminLayout title="Financial Statements" :user-name="userName">
        <template #header>
            <PageHeader title="Financial Statements" subtitle="Trial balance, expenditures, and cash receipts-disbursements." icon="reports">
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="form.date_from" type="date" class="ui-input" />
                        <input v-model="form.date_to" type="date" class="ui-input" />
                        <select v-model="form.statement" class="ui-input">
                            <option value="all">All Statements</option>
                            <option value="trial_balance">Trial Balance</option>
                            <option value="statement_of_expenditures">Statement of Expenditures</option>
                            <option value="cash_receipts_disbursements">Cash Receipts & Disbursements</option>
                        </select>
                        <button type="button" class="ui-btn ui-btn--ghost px-3 py-2" @click="applyFilters">Apply</button>
                        <a v-if="canExport" :href="exportUrl" class="ui-btn ui-btn--ghost px-3 py-2">Export CSV</a>
                    </div>
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <DashboardStatCard label="Expenditures Total" :value="formatMoney(props.statementOfExpenditures.totals.total_amount)" />
            <DashboardStatCard label="Expense Transactions" :value="props.statementOfExpenditures.totals.transactions" />
            <DashboardStatCard label="Cash Opening" :value="formatMoney(props.cashReceiptsDisbursements.opening_balance)" />
            <DashboardStatCard label="Cash Closing" :value="formatMoney(props.cashReceiptsDisbursements.closing_balance)" />
        </div>

        <div v-if="showTrialBalance" class="mb-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Trial Balance</h3>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.trialBalance.rows" :key="row.account">
                            <td>{{ pretty(row.account) }}</td>
                            <td>{{ formatMoney(row.debit) }}</td>
                            <td>{{ formatMoney(row.credit) }}</td>
                            <td>{{ row.transactions || "-" }}</td>
                        </tr>
                        <tr v-if="props.trialBalance.rows.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No trial balance data.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold">
                            <td>Total</td>
                            <td>{{ formatMoney(props.trialBalance.totals.debit_total) }}</td>
                            <td>{{ formatMoney(props.trialBalance.totals.credit_total) }}</td>
                            <td>-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div v-if="showExpenditures" class="mb-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Statement of Expenditures</h3>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Expense Type</th>
                                <th>Transactions</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.statementOfExpenditures.by_type" :key="row.expense_type">
                                <td>{{ pretty(row.expense_type) }}</td>
                                <td>{{ row.transactions }}</td>
                                <td>{{ formatMoney(row.total_amount) }}</td>
                            </tr>
                            <tr v-if="props.statementOfExpenditures.by_type.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">No expenditure totals.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Transactions</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.statementOfExpenditures.by_month" :key="row.month">
                                <td>{{ row.month }}</td>
                                <td>{{ row.transactions }}</td>
                                <td>{{ formatMoney(row.total_amount) }}</td>
                            </tr>
                            <tr v-if="props.statementOfExpenditures.by_month.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">No monthly expenditures.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showCashbook" class="rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Cash Receipts and Disbursements</h3>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Opening</th>
                            <th>Revenue Receipts</th>
                            <th>Credit Adjustments</th>
                            <th>Total Receipts</th>
                            <th>Expense Disbursements</th>
                            <th>Debit Adjustments</th>
                            <th>Total Disbursements</th>
                            <th>Net Change</th>
                            <th>Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.cashReceiptsDisbursements.rows" :key="row.month">
                            <td>{{ row.month }}</td>
                            <td>{{ formatMoney(row.opening_balance) }}</td>
                            <td>{{ formatMoney(row.revenue_receipts) }}</td>
                            <td>{{ formatMoney(row.credit_adjustments) }}</td>
                            <td>{{ formatMoney(row.total_receipts) }}</td>
                            <td>{{ formatMoney(row.expense_disbursements) }}</td>
                            <td>{{ formatMoney(row.debit_adjustments) }}</td>
                            <td>{{ formatMoney(row.total_disbursements) }}</td>
                            <td>{{ formatMoney(row.net_change) }}</td>
                            <td>{{ formatMoney(row.closing_balance) }}</td>
                        </tr>
                        <tr v-if="props.cashReceiptsDisbursements.rows.length === 0">
                            <td colspan="10" class="px-4 py-6 text-center text-slate-500">No cash statement rows.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold">
                            <td>Total</td>
                            <td>-</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.revenue_receipts) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.credit_adjustments) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.total_receipts) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.expense_disbursements) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.debit_adjustments) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.total_disbursements) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.totals.net_change) }}</td>
                            <td>{{ formatMoney(props.cashReceiptsDisbursements.closing_balance) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
