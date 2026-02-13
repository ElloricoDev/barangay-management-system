<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            pending_certificates: 0,
            approved_certificates: 0,
            open_blotters: 0,
            settled_blotters: 0,
        }),
    },
    recentPendingCertificates: {
        type: Array,
        default: () => [],
    },
    recentOpenBlotters: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <StaffLayout title="Staff Dashboard" :user-name="userName">
        <template #header>
            <div class="mb-4 flex items-center justify-between border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Daily Tasks</h2>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                    Staff Access
                </span>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Pending Certificates</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ props.stats.pending_certificates }}</p>
            </div>

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Open Blotter Cases</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ props.stats.open_blotters }}</p>
            </div>

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Approved Certificates</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ props.stats.approved_certificates }}</p>
            </div>

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Settled Blotter Cases</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ props.stats.settled_blotters }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-emerald-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Pending Certificates</h3>
                    <Link href="/staff/certificates" class="text-sm text-emerald-700 hover:underline">View all</Link>
                </div>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="certificate in props.recentPendingCertificates" :key="certificate.id">
                        {{ certificate.resident?.last_name }}, {{ certificate.resident?.first_name }} - {{ certificate.type }}
                    </li>
                    <li v-if="props.recentPendingCertificates.length === 0" class="text-slate-500">No pending records.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-emerald-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Open Blotter Cases</h3>
                    <Link href="/staff/blotter" class="text-sm text-emerald-700 hover:underline">View all</Link>
                </div>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="blotter in props.recentOpenBlotters" :key="blotter.id">
                        {{ blotter.complainant_name }} vs {{ blotter.respondent_name }}
                    </li>
                    <li v-if="props.recentOpenBlotters.length === 0" class="text-slate-500">No open cases.</li>
                </ul>
            </div>
        </div>
    </StaffLayout>
</template>
