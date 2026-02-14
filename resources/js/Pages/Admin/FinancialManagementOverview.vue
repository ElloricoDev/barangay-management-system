<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (permission) => permissions.value.includes(permission);
const canViewPaymentProcessing = computed(() => hasPermission("payment_processing.view"));
const canViewOfficialReceipts = computed(() => hasPermission("official_receipts.view"));
const canViewCollectionReports = computed(() => hasPermission("collection_reports.view"));
const canViewTransactionHistory = computed(() => hasPermission("transaction_history.view"));
const canViewFinancialSummary = computed(() => hasPermission("financial_summary.view"));

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({ total_collections: 0, transactions_count: 0, today_collections: 0 }),
    },
    recentPayments: {
        type: Array,
        default: () => [],
    },
});

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
</script>

<template>
    <AdminLayout title="Financial Management" :user-name="userName">
        <template #header>
            <PageHeader title="Financial Management" subtitle="Finance workspace overview and quick navigation." icon="financial" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-4 md:grid-cols-3">
            <DashboardStatCard label="Total Collections" :value="formatMoney(props.summary.total_collections)" />
            <DashboardStatCard label="Transactions" :value="props.summary.transactions_count" />
            <DashboardStatCard label="Today Collections" :value="formatMoney(props.summary.today_collections)" />
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <Link v-if="canViewPaymentProcessing" href="/admin/payment-processing" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Payment Processing</Link>
            <Link v-if="canViewOfficialReceipts" href="/admin/official-receipts" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Official Receipts</Link>
            <Link v-if="canViewCollectionReports" href="/admin/collection-reports" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Collection Reports</Link>
            <Link v-if="canViewTransactionHistory" href="/admin/transaction-history" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Transaction History</Link>
            <Link v-if="canViewFinancialSummary" href="/admin/financial-summary" class="ui-btn ui-btn--ghost px-4 py-3 text-center">Financial Summary</Link>
        </div>

        <div class="mt-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Recent Transactions</h3>
            <ul class="space-y-2 text-sm text-slate-700">
                <li v-for="payment in props.recentPayments" :key="payment.id" class="flex items-center justify-between">
                    <span>OR {{ payment.or_number }} - {{ payment.service_type }}</span>
                    <span class="font-medium">{{ formatMoney(payment.amount) }}</span>
                </li>
                <li v-if="props.recentPayments.length === 0" class="text-slate-500">No recent transactions.</li>
            </ul>
        </div>
    </AdminLayout>
</template>
