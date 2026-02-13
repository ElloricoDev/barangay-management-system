<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
    title: {
        type: String,
        default: "Admin Dashboard",
    },
    userName: {
        type: String,
        default: "Admin",
    },
});

const isSidebarOpen = ref(false);
const page = usePage();
const barangayName = computed(() => page.props.systemSettings?.barangay_name ?? "Barangay Management System");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canViewResidents = computed(() => permissions.value.includes("residents.view"));
const canViewCertificates = computed(() => permissions.value.includes("certificates.view"));
const canViewBlotter = computed(() => permissions.value.includes("blotter.view"));
const canViewFinancial = computed(() => permissions.value.includes("financial_management.view"));
const canViewPaymentProcessing = computed(() => permissions.value.includes("payment_processing.view"));
const canViewOfficialReceipts = computed(() => permissions.value.includes("official_receipts.view"));
const canViewCollectionReports = computed(() => permissions.value.includes("collection_reports.view"));
const canViewTransactionHistory = computed(() => permissions.value.includes("transaction_history.view"));
const canViewFinancialSummary = computed(() => permissions.value.includes("financial_summary.view"));
const canViewYouth = computed(() => permissions.value.includes("youth_management.view"));
const canViewYouthResidents = computed(() => permissions.value.includes("youth_residents.view"));
const canViewYouthPrograms = computed(() => permissions.value.includes("youth_programs.view"));
const canViewYouthReports = computed(() => permissions.value.includes("youth_reports.view"));
const canViewPrograms = computed(() => permissions.value.includes("programs.view"));
const canViewCommitteeReports = computed(() => permissions.value.includes("committee_reports.view"));
const canViewProgramsMonitoring = computed(() => permissions.value.includes("programs_monitoring.view"));
const canViewReportsAnalytics = computed(() => permissions.value.includes("reports_analytics.view"));
const canViewReports = computed(() => permissions.value.includes("reports.view"));
const canViewDocumentArchive = computed(() => permissions.value.includes("document_archive.view"));
const canManageUsers = computed(() => permissions.value.includes("users.manage"));
const canManageRoles = computed(() => permissions.value.includes("roles.manage"));
const canViewSystemLogs = computed(() => permissions.value.includes("system.logs.view"));
const canBackup = computed(() => permissions.value.includes("system.backup"));
const canAccessSystemSettings = computed(() => permissions.value.includes("system.settings"));
const canViewAudit = computed(() => permissions.value.includes("audit.view"));
const showLogoutModal = ref(false);

const confirmLogout = () => {
    router.post("/logout");
};
</script>

<template>
    <Head :title="props.title" />

    <div class="min-h-screen bg-slate-100 text-slate-800">
        <div class="flex min-h-screen">
            <aside
                class="fixed inset-y-0 left-0 z-40 w-64 transform bg-slate-900 text-slate-100 transition-transform duration-200 md:static md:translate-x-0"
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="border-b border-slate-800 px-6 py-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400">{{ barangayName }}</p>
                    <h2 class="text-lg font-semibold">Admin Panel</h2>
                </div>

                <nav class="space-y-1 p-3">
                    <Link href="/admin/dashboard" class="block rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium">
                        Dashboard
                    </Link>
                    <Link v-if="canViewResidents" href="/admin/residents" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Resident Management
                    </Link>
                    <Link v-if="canViewCertificates" href="/admin/certificates" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Certificate Management
                    </Link>
                    <Link v-if="canViewBlotter" href="/admin/blotter" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Blotter Cases
                    </Link>
                    <Link v-if="canViewFinancial" href="/admin/financial-management" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Financial Management
                    </Link>
                    <Link v-if="canViewPaymentProcessing" href="/admin/payment-processing" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Payment Processing
                    </Link>
                    <Link v-if="canViewOfficialReceipts" href="/admin/official-receipts" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Official Receipts
                    </Link>
                    <Link v-if="canViewCollectionReports" href="/admin/collection-reports" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Collection Reports
                    </Link>
                    <Link v-if="canViewTransactionHistory" href="/admin/transaction-history" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Transaction History
                    </Link>
                    <Link v-if="canViewFinancialSummary" href="/admin/financial-summary" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Financial Summary
                    </Link>
                    <Link v-if="canViewYouth" href="/admin/youth-management" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Youth Management (SK)
                    </Link>
                    <Link v-if="canViewYouthResidents" href="/admin/youth-residents" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Youth Residents
                    </Link>
                    <Link v-if="canViewYouthPrograms" href="/admin/youth-programs" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Youth Programs
                    </Link>
                    <Link v-if="canViewYouthReports" href="/admin/youth-reports" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Youth Reports
                    </Link>
                    <Link v-if="canViewPrograms" href="/admin/programs-projects" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Programs & Projects
                    </Link>
                    <Link v-if="canViewCommitteeReports" href="/admin/committee-reports" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Committee Reports
                    </Link>
                    <Link v-if="canViewProgramsMonitoring" href="/admin/programs-monitoring" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Programs Monitoring
                    </Link>
                    <Link v-if="canViewReportsAnalytics" href="/admin/reports-analytics" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Reports & Analytics
                    </Link>
                    <Link v-if="canViewReports" href="/admin/reports" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Reports
                    </Link>
                    <Link v-if="canViewDocumentArchive" href="/admin/document-archive" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Document Archive
                    </Link>
                    <Link v-if="canManageUsers" href="/admin/users" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        User Management
                    </Link>
                    <Link v-if="canManageRoles" href="/admin/role-permissions" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Role Permissions
                    </Link>
                    <Link v-if="canViewAudit" href="/admin/audit-logs" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Audit Logs
                    </Link>
                    <Link v-if="canViewSystemLogs" href="/admin/system-logs" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        System Logs
                    </Link>
                    <Link v-if="canBackup" href="/admin/backup-restore" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        Backup & Restore
                    </Link>
                    <Link v-if="canAccessSystemSettings" href="/admin/system-settings" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        System Settings
                    </Link>
                    <Link href="/admin/account" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">
                        My Account
                    </Link>
                </nav>
            </aside>

            <div
                v-if="isSidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 md:hidden"
                @click="isSidebarOpen = false"
            />

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 border-b bg-white/90 backdrop-blur">
                    <div class="flex items-center justify-between px-4 py-3 md:px-6">
                        <div class="flex items-center gap-3">
                            <button
                                class="rounded-md border px-2 py-1 text-sm md:hidden"
                                @click="isSidebarOpen = !isSidebarOpen"
                            >
                                Menu
                            </button>
                            <div>
                                <h1 class="text-lg font-semibold">{{ props.title }}</h1>
                                <p class="text-xs text-slate-500">Administrative workspace</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-600">Signed in as {{ props.userName }}</span>
                            <Link
                                href="/admin/account"
                                class="rounded-md border border-slate-300 px-3 py-1 text-sm text-slate-700 hover:bg-slate-100"
                            >
                                My Account
                            </Link>
                            <button
                                type="button"
                                class="rounded-md border border-rose-300 px-3 py-1 text-sm text-rose-700 hover:bg-rose-50"
                                @click="showLogoutModal = true"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </header>

                <main class="flex-1 p-4 md:p-6">
                    <div class="rounded-xl border bg-white p-4 shadow-sm md:p-6">
                        <slot name="header" />
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <div
            v-if="showLogoutModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        >
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Confirm Logout</h3>
                <p class="mt-2 text-sm text-slate-600">Are you sure you want to logout?</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100"
                        @click="showLogoutModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                        @click="confirmLogout"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
