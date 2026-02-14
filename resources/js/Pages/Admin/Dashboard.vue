<script setup>
import { computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import DashboardListCard from "../../Components/ui/DashboardListCard.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

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
            <PageHeader title="Overview" icon="dashboard">
                <template #actions>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Admin Access</span>
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-4 md:grid-cols-4">
            <DashboardStatCard label="Residents" :value="props.stats.residents" />
            <DashboardStatCard label="Certificates" :value="props.stats.certificates" />
            <DashboardStatCard label="Blotter Cases" :value="props.stats.blotters" />
            <DashboardStatCard label="Users" :value="props.stats.users" />
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <DashboardListCard title="Recent Residents" href="/admin/residents" :items="props.recentResidents">
                <template #item="{ item }">
                    {{ item.last_name }}, {{ item.first_name }}
                </template>
            </DashboardListCard>

            <DashboardListCard title="Recent Certificates" href="/admin/certificates" :items="props.recentCertificates">
                <template #item="{ item }">
                    {{ item.resident?.last_name }}, {{ item.resident?.first_name }} - {{ item.type }}
                </template>
            </DashboardListCard>
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
