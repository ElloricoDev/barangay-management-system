<script setup>
import { computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    filters: { type: Object, default: () => ({ date_from: "", date_to: "" }) },
    kpis: { type: Object, default: () => ({}) },
    recentActivity: { type: Array, default: () => [] },
    topServices: { type: Array, default: () => [] },
});

const applyFilters = (event) => {
    const data = new FormData(event.target);
    router.get(
        "/admin/reports",
        {
            date_from: data.get("date_from") ?? "",
            date_to: data.get("date_to") ?? "",
        },
        { preserveState: true, replace: true }
    );
};

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};
</script>

<template>
    <AdminLayout title="Reports" :user-name="userName">
        <template #header>
            <PageHeader title="Reports" subtitle="Operational report tables for export and review." icon="reports" />
        </template>

        <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-3" @submit.prevent="applyFilters">
            <input name="date_from" :value="props.filters.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
            <input name="date_to" :value="props.filters.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Apply Date Range</button>
        </form>

        <div class="mb-4 flex justify-end">
            <a
                :href="`/admin/reports/export?date_from=${encodeURIComponent(props.filters.date_from || '')}&date_to=${encodeURIComponent(props.filters.date_to || '')}`"
                class="rounded-md border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50"
            >
                Export CSV
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Residents</p><p class="mt-2 text-2xl font-bold text-slate-800">{{ props.kpis.residents ?? 0 }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Certificates</p><p class="mt-2 text-2xl font-bold text-slate-800">{{ props.kpis.certificates ?? 0 }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Blotters</p><p class="mt-2 text-2xl font-bold text-slate-800">{{ props.kpis.blotters ?? 0 }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Users</p><p class="mt-2 text-2xl font-bold text-slate-800">{{ props.kpis.users ?? 0 }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Collections (Range)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ formatMoney(props.kpis.collections_range) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Collections (All Time)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ formatMoney(props.kpis.collections_total) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Audit Events (Range)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ props.kpis.audit_events_range ?? 0 }}</p></div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Top Service Types (Range)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Service</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Transactions</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="service in props.topServices" :key="service.service_type">
                            <td class="px-3 py-2 text-slate-700">{{ service.service_type }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ service.transactions }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ formatMoney(service.amount) }}</td>
                        </tr>
                        <tr v-if="props.topServices.length === 0"><td colspan="3" class="px-3 py-5 text-center text-slate-500">No services in selected range.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Recent Activity (Range)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">User</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Action</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Module</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.recentActivity" :key="row.id">
                            <td class="px-3 py-2 text-slate-700">{{ formatDate(row.created_at) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ row.user?.name ?? "System" }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ row.action }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ row.auditable_type?.split('\\').pop() }}</td>
                        </tr>
                        <tr v-if="props.recentActivity.length === 0"><td colspan="4" class="px-3 py-5 text-center text-slate-500">No activity in selected range.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
