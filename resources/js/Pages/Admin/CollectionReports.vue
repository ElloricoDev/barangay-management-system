<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import PaymentTable from "../../Components/modules/PaymentTable.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canExportReports = computed(() => permissions.value.includes("finance.reports.export"));

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: "", sort: "paid_at", direction: "desc" }) },
    summary: { type: Object, default: () => ({ total_collections: 0, transactions_count: 0, today_collections: 0 }) },
    serviceTotals: { type: Array, default: () => [] },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/collection-reports",
    filters: computed(() => props.filters),
    defaultSort: "paid_at",
});

const queryParams = computed(() => ({
    search: props.filters?.search ?? "",
    sort: props.filters?.sort ?? "paid_at",
    direction: props.filters?.direction ?? "desc",
}));

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
};
</script>

<template>
    <AdminLayout title="Collection Reports" :user-name="userName">
        <template #header>
            <PageHeader title="Collection Reports" subtitle="Analyze and export collection data." icon="collection">
                <template #actions>
                    <div class="flex items-center gap-2">
                    <input v-model="search" type="text" placeholder="Search reports..." class="ui-input max-w-xs" />
                    <a v-if="canExportReports" :href="`/admin/payments/export?search=${encodeURIComponent(queryParams.search)}&sort=${encodeURIComponent(queryParams.sort)}&direction=${encodeURIComponent(queryParams.direction)}`" class="ui-btn ui-btn--ghost px-3 py-2">Export CSV</a>
                    </div>
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <DashboardStatCard label="Total Collections" :value="formatMoney(props.summary.total_collections)" />
            <DashboardStatCard label="Transactions" :value="props.summary.transactions_count" />
            <DashboardStatCard label="Today Collections" :value="formatMoney(props.summary.today_collections)" />
        </div>

        <div class="mb-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">By Service Type</h3>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr><th>Service Type</th><th>Transactions</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.serviceTotals" :key="row.service_type">
                            <td>{{ row.service_type }}</td>
                            <td>{{ row.transactions_count }}</td>
                            <td class="font-medium text-slate-800">{{ formatMoney(row.total_amount) }}</td>
                        </tr>
                        <tr v-if="props.serviceTotals.length === 0"><td colspan="3" class="px-4 py-6 text-center text-slate-500">No service totals available.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PaymentTable :payments="props.payments.data" :sort-indicator="sortIndicator" :format-money="formatMoney" :format-date="formatDate" @sort="sortBy" />
        <PaginationLinks :links="props.payments.links" />
    </AdminLayout>
</template>
