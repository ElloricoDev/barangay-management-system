<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canManageDelegation = computed(() => permissions.value.includes("delegation.manage"));

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            residents: 0,
            certificates: 0,
            blotters: 0,
            users: 0,
            pending_certificates: 0,
            open_blotters: 0,
        }),
    },
    recentResidents: {
        type: Array,
        default: () => [],
    },
    recentCertificates: {
        type: Array,
        default: () => [],
    },
    delegation: {
        type: Object,
        default: () => ({
            staff_can_approve: false,
        }),
    },
});

const toggleDelegation = () => {
    router.patch("/admin/delegation/toggle", {}, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout title="Admin Dashboard" :user-name="userName">
        <template #header>
            <div class="mb-4 flex items-center justify-between border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Overview</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    Admin Access
                </span>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Residents</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ props.stats.residents }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Certificates</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ props.stats.certificates }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Blotter Cases</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ props.stats.blotters }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Users</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ props.stats.users }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Recent Residents</h3>
                    <Link href="/admin/residents" class="text-sm text-slate-600 hover:underline">View all</Link>
                </div>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="resident in props.recentResidents" :key="resident.id">
                        {{ resident.last_name }}, {{ resident.first_name }}
                    </li>
                    <li v-if="props.recentResidents.length === 0" class="text-slate-500">No records yet.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">Recent Certificates</h3>
                    <Link href="/admin/certificates" class="text-sm text-slate-600 hover:underline">View all</Link>
                </div>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="certificate in props.recentCertificates" :key="certificate.id">
                        {{ certificate.resident?.last_name }}, {{ certificate.resident?.first_name }} - {{ certificate.type }}
                    </li>
                    <li v-if="props.recentCertificates.length === 0" class="text-slate-500">No records yet.</li>
                </ul>
            </div>
        </div>

        <div v-if="canManageDelegation" class="mt-4 rounded-lg border border-slate-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800">Delegation Toggle</h3>
                    <p class="text-sm text-slate-500">
                        Temporary override to allow staff to approve/reject certificates and blotter cases.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md px-4 py-2 text-sm font-medium text-white"
                    :class="props.delegation.staff_can_approve ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                    @click="toggleDelegation"
                >
                    {{ props.delegation.staff_can_approve ? "Disable Delegation" : "Enable Delegation" }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
