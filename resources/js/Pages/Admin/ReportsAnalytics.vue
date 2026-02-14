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
    timeline: { type: Array, default: () => [] },
    certificateStatus: { type: Array, default: () => [] },
    blotterStatus: { type: Array, default: () => [] },
});

const title = computed(() => "Reports & Analytics");

const applyFilters = (event) => {
    const data = new FormData(event.target);
    router.get(
        "/admin/reports-analytics",
        {
            date_from: data.get("date_from") ?? "",
            date_to: data.get("date_to") ?? "",
        },
        { preserveState: true, replace: true }
    );
};

const maxCollections = computed(() =>
    Math.max(1, ...props.timeline.map((row) => Number(row.collections ?? 0)))
);

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

</script>

<template>
    <AdminLayout :title="title" :user-name="userName">
        <template #header>
            <PageHeader :title="title" subtitle="Trend and distribution insights for planning and monitoring." icon="analytics" />
        </template>

        <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-3" @submit.prevent="applyFilters">
            <input name="date_from" :value="props.filters.date_from" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
            <input name="date_to" :value="props.filters.date_to" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
            <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Apply Date Range</button>
        </form>

        <div class="mb-4 flex justify-end">
            <a href="/admin/reports" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Open Reports</a>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Collections (All Time)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ formatMoney(props.kpis.collections_total) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Collections (Range)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ formatMoney(props.kpis.collections_range) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Audit Events (Range)</p><p class="mt-2 text-xl font-bold text-slate-800">{{ props.kpis.audit_events_range ?? 0 }}</p></div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Monthly Collections Trend</h3>
                <div class="space-y-2">
                    <div v-for="row in props.timeline" :key="row.period" class="grid grid-cols-[80px_1fr_130px] items-center gap-2 text-xs">
                        <span class="text-slate-600">{{ row.period }}</span>
                        <div class="h-3 rounded bg-slate-100">
                            <div class="h-3 rounded bg-emerald-500" :style="{ width: `${Math.max(2, (Number(row.collections || 0) / maxCollections) * 100)}%` }"></div>
                        </div>
                        <span class="text-right font-medium text-slate-700">{{ formatMoney(row.collections) }}</span>
                    </div>
                    <p v-if="props.timeline.length === 0" class="text-sm text-slate-500">No timeline data.</p>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Records Summary</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded bg-slate-50 px-3 py-2 text-sm text-slate-700">Residents: <span class="font-semibold text-slate-900">{{ props.kpis.residents ?? 0 }}</span></div>
                    <div class="rounded bg-slate-50 px-3 py-2 text-sm text-slate-700">Certificates: <span class="font-semibold text-slate-900">{{ props.kpis.certificates ?? 0 }}</span></div>
                    <div class="rounded bg-slate-50 px-3 py-2 text-sm text-slate-700">Blotters: <span class="font-semibold text-slate-900">{{ props.kpis.blotters ?? 0 }}</span></div>
                    <div class="rounded bg-slate-50 px-3 py-2 text-sm text-slate-700">Users: <span class="font-semibold text-slate-900">{{ props.kpis.users ?? 0 }}</span></div>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Certificate Status Breakdown</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="row in props.certificateStatus" :key="row.status" class="flex justify-between rounded bg-slate-50 px-3 py-2">
                        <span class="text-slate-700">{{ row.status }}</span>
                        <span class="font-medium text-slate-800">{{ row.total }}</span>
                    </li>
                    <li v-if="props.certificateStatus.length === 0" class="text-slate-500">No certificate data.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Blotter Status Breakdown</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="row in props.blotterStatus" :key="row.status" class="flex justify-between rounded bg-slate-50 px-3 py-2">
                        <span class="text-slate-700">{{ row.status }}</span>
                        <span class="font-medium text-slate-800">{{ row.total }}</span>
                    </li>
                    <li v-if="props.blotterStatus.length === 0" class="text-slate-500">No blotter data.</li>
                </ul>
            </div>
        </div>

    </AdminLayout>
</template>
