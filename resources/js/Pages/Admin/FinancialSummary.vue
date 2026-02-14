<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    summary: { type: Object, default: () => ({ total_collections: 0, transactions_count: 0, today_collections: 0 }) },
    serviceTotals: { type: Array, default: () => [] },
    monthlyTotals: { type: Array, default: () => [] },
});

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
</script>

<template>
    <AdminLayout title="Financial Summary" :user-name="userName">
        <template #header>
            <PageHeader title="Financial Summary" subtitle="Executive view of revenue and trends." icon="summary" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-4 md:grid-cols-3">
            <DashboardStatCard label="Total Collections" :value="formatMoney(props.summary.total_collections)" />
            <DashboardStatCard label="Transactions" :value="props.summary.transactions_count" />
            <DashboardStatCard label="Today Collections" :value="formatMoney(props.summary.today_collections)" />
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Service Mix</h3>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr><th>Service</th><th>Transactions</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.serviceTotals" :key="row.service_type">
                                <td>{{ row.service_type }}</td>
                                <td>{{ row.transactions_count }}</td>
                                <td class="font-medium text-slate-800">{{ formatMoney(row.total_amount) }}</td>
                            </tr>
                            <tr v-if="props.serviceTotals.length === 0"><td colspan="3" class="px-4 py-6 text-center text-slate-500">No service data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Monthly Trend</h3>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr><th>Month</th><th>Transactions</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.monthlyTotals" :key="row.month">
                                <td>{{ row.month }}</td>
                                <td>{{ row.transactions_count }}</td>
                                <td class="font-medium text-slate-800">{{ formatMoney(row.total_amount) }}</td>
                            </tr>
                            <tr v-if="props.monthlyTotals.length === 0"><td colspan="3" class="px-4 py-6 text-center text-slate-500">No monthly data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
