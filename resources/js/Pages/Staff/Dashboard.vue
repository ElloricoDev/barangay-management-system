<script setup>
import { computed } from "vue";
import StaffLayout from "../../Layouts/StaffLayout.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import DashboardListCard from "../../Components/ui/DashboardListCard.vue";
import { usePage } from "@inertiajs/vue3";
import PageHeader from "../../Components/ui/PageHeader.vue";

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
            <PageHeader title="Daily Tasks" icon="dashboard">
                <template #actions>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                        Staff Access
                    </span>
                </template>
            </PageHeader>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <DashboardStatCard label="Pending Certificates" :value="props.stats.pending_certificates" />
            <DashboardStatCard label="Open Blotter Cases" :value="props.stats.open_blotters" />
            <DashboardStatCard label="Approved Certificates" :value="props.stats.approved_certificates" />
            <DashboardStatCard label="Settled Blotter Cases" :value="props.stats.settled_blotters" />
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <DashboardListCard
                title="Pending Certificates"
                href="/staff/certificates"
                :items="props.recentPendingCertificates"
                empty-text="No pending records."
            >
                <template #item="{ item }">
                    {{ item.resident?.last_name }}, {{ item.resident?.first_name }} - {{ item.type }}
                </template>
            </DashboardListCard>

            <DashboardListCard
                title="Open Blotter Cases"
                href="/staff/blotter"
                :items="props.recentOpenBlotters"
                empty-text="No open cases."
            >
                <template #item="{ item }">
                    {{ item.complainant_name }} vs {{ item.respondent_name }}
                </template>
            </DashboardListCard>
        </div>
    </StaffLayout>
</template>
