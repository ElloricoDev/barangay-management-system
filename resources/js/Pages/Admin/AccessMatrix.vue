<script setup>
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";
import { permissionLabel } from "../../Utils/permissionLabels";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    allPermissions: {
        type: Array,
        default: () => [],
    },
    matrix: {
        type: Array,
        default: () => [],
    },
});

const selectedRole = ref(props.matrix[0]?.role ?? "");
const search = ref("");

const selectedRow = computed(() => props.matrix.find((item) => item.role === selectedRole.value) ?? null);

const filteredPermissions = computed(() => {
    if (!selectedRow.value) return [];
    if (!search.value.trim()) return selectedRow.value.effective_permissions;

    const query = search.value.toLowerCase();
    return selectedRow.value.effective_permissions.filter((permission) =>
        permission.toLowerCase().includes(query) ||
        permissionLabel(permission).toLowerCase().includes(query)
    );
});

const normalizedRoleLabel = (role) =>
    role
        .split("_")
        .map((chunk) => chunk.charAt(0).toUpperCase() + chunk.slice(1))
        .join(" ");

const sidebarChecks = [
    { module: "Dashboard", permissions: ["dashboard.view"] },
    { module: "Resident Management", permissions: ["residents.view"] },
    { module: "Certificate Management", permissions: ["certificates.view"] },
    { module: "Blotter Records", permissions: ["blotter.view"] },
    { module: "Financial Management", permissions: ["financial_management.view"] },
    { module: "Payment Processing", permissions: ["payment_processing.view"] },
    { module: "Official Receipts", permissions: ["official_receipts.view"] },
    { module: "Collection Reports", permissions: ["collection_reports.view"] },
    { module: "Transaction History", permissions: ["transaction_history.view"] },
    { module: "Financial Summary", permissions: ["financial_summary.view"] },
    { module: "Financial Statements", permissions: ["finance.statements.view"] },
    { module: "Financial Submissions", permissions: ["finance.submissions.view"] },
    { module: "Youth Management", permissions: ["youth_management.view"] },
    { module: "Youth Residents", permissions: ["youth_residents.view"] },
    { module: "Youth Programs", permissions: ["youth_programs.view"] },
    { module: "Youth Reports", permissions: ["youth_reports.view"] },
    { module: "Programs & Projects", permissions: ["programs.view"] },
    { module: "Committee Reports", permissions: ["committee_reports.view"] },
    { module: "Programs Monitoring", permissions: ["programs_monitoring.view"] },
    { module: "Analytics (Trends)", permissions: ["reports_analytics.view"] },
    { module: "Reports (Export)", permissions: ["reports.view"] },
    { module: "Document Archive", permissions: ["document_archive.view"] },
    { module: "Upload Documents", permissions: ["documents.upload"] },
    { module: "Data Quality", permissions: ["data.validate", "data.archive"] },
    { module: "User Management", permissions: ["users.manage"] },
    { module: "Role Permissions", permissions: ["roles.manage"] },
    { module: "Audit Logs", permissions: ["audit.view"] },
    { module: "System Logs", permissions: ["system.logs.view"] },
    { module: "Backup & Restore", permissions: ["system.backup"] },
    { module: "System Settings", permissions: ["system.settings"] },
];

const simulationResults = computed(() => {
    if (!selectedRow.value) return [];
    const allowed = new Set(selectedRow.value.effective_permissions ?? []);

    return sidebarChecks.map((item) => ({
        module: item.module,
        required: item.permissions,
        allowed: item.permissions.some((permission) => allowed.has(permission)),
    }));
});
</script>

<template>
    <AdminLayout title="Access Matrix" :user-name="userName">
        <template #header>
            <PageHeader title="Access Matrix" subtitle="Review effective permission coverage per role." icon="matrix">
                <template #actions>
                    <a href="/admin/access-matrix/export" class="ui-btn ui-btn--ghost px-3 py-2">Export CSV</a>
                </template>
            </PageHeader>
        </template>

        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Roles</h3>
                <div class="space-y-1">
                    <button
                        v-for="row in props.matrix"
                        :key="row.role"
                        type="button"
                        class="block w-full rounded-md px-3 py-2 text-left text-sm"
                        :class="selectedRole === row.role ? 'bg-slate-800 text-white' : 'hover:bg-slate-200 text-slate-700'"
                        @click="selectedRole = row.role"
                    >
                        {{ normalizedRoleLabel(row.role) }}
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <div v-if="selectedRow" class="mb-3 grid gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Effective</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">{{ selectedRow.effective_permissions.length }}</p>
                    </div>
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-emerald-700">Added Overrides</p>
                        <p class="mt-1 text-lg font-semibold text-emerald-800">{{ selectedRow.added_permissions.length }}</p>
                    </div>
                    <div class="rounded-md border border-rose-200 bg-rose-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-rose-700">Removed Defaults</p>
                        <p class="mt-1 text-lg font-semibold text-rose-800">{{ selectedRow.removed_permissions.length }}</p>
                    </div>
                </div>

                <div class="mb-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Filter permissions..."
                        class="ui-input"
                    />
                </div>

                <div class="max-h-[460px] overflow-y-auto rounded-md border border-slate-200 p-3">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div
                            v-for="permission in filteredPermissions"
                            :key="permission"
                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700"
                        >
                            {{ permissionLabel(permission) }}
                        </div>
                    </div>
                    <p v-if="filteredPermissions.length === 0" class="text-sm text-slate-500">No permissions match the filter.</p>
                </div>

                <div class="mt-4 rounded-md border border-slate-200 p-3">
                    <h4 class="mb-2 text-sm font-semibold text-slate-800">Sidebar Simulation</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Module</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Required Permission(s)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in simulationResults" :key="row.module">
                                    <td class="px-3 py-2 text-slate-700">{{ row.module }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                                            :class="row.allowed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"
                                        >
                                            {{ row.allowed ? "Allowed" : "Blocked" }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ row.required.map(permissionLabel).join(" or ") }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
