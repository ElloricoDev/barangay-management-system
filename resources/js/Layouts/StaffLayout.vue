<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AppShellLayout from "./AppShellLayout.vue";

const props = defineProps({
    title: {
        type: String,
        default: "Staff Dashboard",
    },
    userName: {
        type: String,
        default: "Staff",
    },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (permission) => permissions.value.includes(permission);

const navItems = computed(() => [
    { href: "/staff/residents", label: "Resident Encoding", icon: "residents", show: hasPermission("residents.view") },
    { href: "/staff/certificates", label: "Certificate Requests", icon: "certificates", show: hasPermission("certificates.view") },
    { href: "/staff/blotter", label: "Blotter Encoding", icon: "blotter", show: hasPermission("blotter.view") },
    { href: "/staff/upload-documents", label: "Upload Documents", icon: "upload", show: hasPermission("documents.upload") },
    {
        href: "/staff/data-quality",
        label: "Data Quality",
        icon: "quality",
        show: hasPermission("data.validate") || hasPermission("data.archive"),
    },
]);
</script>

<template>
    <Head :title="props.title" />
    <AppShellLayout
        :title="props.title"
        :user-name="props.userName"
        panel-label="Staff Panel"
        workspace-label="Operational workspace"
        home-href="/staff/dashboard"
        :nav-items="navItems"
    >
        <template #header>
            <slot name="header" />
        </template>
        <slot />
    </AppShellLayout>
</template>
