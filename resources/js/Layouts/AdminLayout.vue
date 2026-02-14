<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AppShellLayout from "./AppShellLayout.vue";

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

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (permission) => permissions.value.includes(permission);

const navItems = computed(() => [
    { href: "/admin/residents", label: "Resident Management", icon: "residents", show: hasPermission("residents.view") },
    { href: "/admin/certificates", label: "Certificate Management", icon: "certificates", show: hasPermission("certificates.view") },
    { href: "/admin/blotter", label: "Blotter Cases", icon: "blotter", show: hasPermission("blotter.view") },
    { href: "/admin/financial-management", label: "Financial Management", icon: "financial", show: hasPermission("financial_management.view") },
    { href: "/admin/payment-processing", label: "Payment Processing", icon: "payment", show: hasPermission("payment_processing.view") },
    { href: "/admin/official-receipts", label: "Official Receipts", icon: "receipts", show: hasPermission("official_receipts.view") },
    { href: "/admin/collection-reports", label: "Collection Reports", icon: "collection", show: hasPermission("collection_reports.view") },
    { href: "/admin/transaction-history", label: "Transaction History", icon: "transactions", show: hasPermission("transaction_history.view") },
    { href: "/admin/financial-summary", label: "Financial Summary", icon: "summary", show: hasPermission("financial_summary.view") },
    { href: "/admin/youth-management", label: "Youth Management", icon: "youth", show: hasPermission("youth_management.view") },
    { href: "/admin/youth-residents", label: "Youth Residents", icon: "residents", show: hasPermission("youth_residents.view") },
    { href: "/admin/youth-programs", label: "Youth Programs", icon: "projects", show: hasPermission("youth_programs.view") },
    { href: "/admin/youth-reports", label: "Youth Reports", icon: "reports", show: hasPermission("youth_reports.view") },
    { href: "/admin/programs-projects", label: "Programs & Projects", icon: "projects", show: hasPermission("programs.view") },
    { href: "/admin/committee-reports", label: "Committee Reports", icon: "reports", show: hasPermission("committee_reports.view") },
    { href: "/admin/programs-monitoring", label: "Programs Monitoring", icon: "analytics", show: hasPermission("programs_monitoring.view") },
    { href: "/admin/reports-analytics", label: "Analytics (Trends)", icon: "analytics", show: hasPermission("reports_analytics.view") },
    { href: "/admin/reports", label: "Reports (Export)", icon: "reports", show: hasPermission("reports.view") },
    { href: "/admin/document-archive", label: "Document Archive", icon: "archive", show: hasPermission("document_archive.view") },
    { href: "/admin/users", label: "User Management", icon: "users", show: hasPermission("users.manage") },
    { href: "/admin/role-permissions", label: "Role Permissions", icon: "roles", show: hasPermission("roles.manage") },
    { href: "/admin/access-matrix", label: "Access Matrix", icon: "matrix", show: hasPermission("roles.manage") },
    { href: "/admin/audit-logs", label: "Audit Logs", icon: "audit", show: hasPermission("audit.view") },
    { href: "/admin/system-logs", label: "System Logs", icon: "logs", show: hasPermission("system.logs.view") },
    { href: "/admin/backup-restore", label: "Backup & Restore", icon: "backup", show: hasPermission("system.backup") },
    { href: "/admin/system-settings", label: "System Settings", icon: "settings", show: hasPermission("system.settings") },
]);
</script>

<template>
    <Head :title="props.title" />
    <AppShellLayout
        :title="props.title"
        :user-name="props.userName"
        panel-label="Admin Panel"
        workspace-label="Administrative workspace"
        home-href="/admin/dashboard"
        :nav-items="navItems"
        :show-account-link="true"
        account-href="/admin/account"
    >
        <template #header>
            <slot name="header" />
        </template>
        <slot />
    </AppShellLayout>
</template>
