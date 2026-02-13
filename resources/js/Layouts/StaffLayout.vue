<script setup>
import { ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";

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
                    <p class="text-xs uppercase tracking-widest text-emerald-300">Barangay</p>
                    <h2 class="text-lg font-semibold">Staff Panel</h2>
                </div>

                <nav class="space-y-1 p-3">
                    <Link href="/staff/dashboard" class="block rounded-lg bg-emerald-800 px-3 py-2 text-sm font-medium">
                        Dashboard
                    </Link>
                    <Link href="/staff/residents" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Residents
                    </Link>
                    <Link href="/staff/certificates" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Certificates
                    </Link>
                    <Link href="/staff/blotter" class="block rounded-lg px-3 py-2 text-sm hover:bg-emerald-800">
                        Blotter Cases
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

                        <div class="text-sm text-slate-600">Signed in as {{ props.userName }}</div>
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
    </div>
</template>
