<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

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

const isSidebarOpen = ref(false);
const page = usePage();
const barangayName = computed(() => page.props.systemSettings?.barangay_name ?? "Barangay Management System");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canViewResidents = computed(() => permissions.value.includes("residents.view"));
const canViewCertificates = computed(() => permissions.value.includes("certificates.view"));
const canViewBlotter = computed(() => permissions.value.includes("blotter.view"));
const canUploadDocuments = computed(() => permissions.value.includes("documents.upload"));
const canViewReports = computed(() => permissions.value.includes("reports.view"));
const canValidateData = computed(() => permissions.value.includes("data.validate"));
const canArchiveData = computed(() => permissions.value.includes("data.archive"));
const canViewDataQuality = computed(() => canValidateData.value || canArchiveData.value);
const showLogoutModal = ref(false);

const confirmLogout = () => {
    router.post("/logout");
};
</script>

<template>
    <Head :title="props.title" />

    <div class="min-h-screen bg-emerald-50 text-slate-800">
        <div class="flex min-h-screen">
            <aside
                class="fixed inset-y-0 left-0 z-40 w-64 transform bg-emerald-900 text-emerald-50 transition-transform duration-200 md:static md:translate-x-0"
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <div class="border-b border-emerald-800 px-6 py-4">
                    <p class="text-xs uppercase tracking-widest text-emerald-300">{{ barangayName }}</p>
                    <h2 class="text-lg font-semibold">Staff Panel</h2>
                </div>

                <nav class="space-y-1 p-3">
                    <Link href="/staff/dashboard" class="block rounded-lg bg-emerald-800 px-3 py-2 text-sm font-medium">
                        Dashboard
                    </Link>
                    <Link v-if="canViewResidents" href="/staff/residents" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Resident Encoding
                    </Link>
                    <Link v-if="canViewCertificates" href="/staff/certificates" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Certificate Requests
                    </Link>
                    <Link v-if="canViewBlotter" href="/staff/blotter" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Blotter Encoding
                    </Link>
                    <Link v-if="canUploadDocuments" href="/staff/upload-documents" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Upload Documents
                    </Link>
                    <Link v-if="canViewReports" href="/staff/dashboard" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Reports
                    </Link>
                    <Link v-if="canViewDataQuality" href="/staff/data-quality" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Data Quality
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
                                <p class="text-xs text-slate-500">Operational workspace</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-600">Signed in as {{ props.userName }}</span>
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

        <div v-if="showLogoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
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
