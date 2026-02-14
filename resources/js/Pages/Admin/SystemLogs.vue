<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    actions: {
        type: Array,
        default: () => [],
    },
    appLogTail: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: "", action: "", date_from: "", date_to: "" }),
    },
});

const applyFilters = (event) => {
    const data = new FormData(event.target);
    router.get(
        "/admin/system-logs",
        {
            search: data.get("search") ?? "",
            action: data.get("action") ?? "",
            date_from: data.get("date_from") ?? "",
            date_to: data.get("date_to") ?? "",
        },
        { preserveState: true, replace: true }
    );
};

const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};
</script>

<template>
    <AdminLayout title="System Logs" :user-name="userName">
        <template #header>
            <PageHeader title="System Logs" subtitle="Audit activity and application log tail." icon="logs" />
        </template>

        <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-5" @submit.prevent="applyFilters">
            <input
                name="search"
                :value="props.filters.search"
                type="text"
                placeholder="Search action/user..."
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
            <select
                name="action"
                :value="props.filters.action"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            >
                <option value="">All actions</option>
                <option v-for="action in props.actions" :key="action" :value="action">{{ action }}</option>
            </select>
            <input
                name="date_from"
                :value="props.filters.date_from"
                type="date"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
            <input
                name="date_to"
                :value="props.filters.date_to"
                type="date"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
            <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Apply Filters
            </button>
        </form>

        <div class="mb-5 overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">User</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Action</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Module</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Record ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="log in props.logs.data" :key="log.id">
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(log.created_at) }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ log.user?.name ?? "System" }}
                            <span class="text-xs text-slate-500">{{ log.user?.email ?? "" }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ log.action }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ log.auditable_type?.split("\\").pop() }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ log.auditable_id }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ log.ip_address ?? "-" }}</td>
                    </tr>
                    <tr v-if="props.logs.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <Link
                v-for="link in props.logs.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1 text-sm"
                :class="[
                    link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <div class="rounded-lg border border-slate-200">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold text-slate-800">Application Log Tail (`storage/logs/laravel.log`)</h3>
            </div>
            <pre class="max-h-[360px] overflow-y-auto bg-slate-950 p-4 text-xs text-slate-200">{{ props.appLogTail.join("\n") }}</pre>
        </div>
    </AdminLayout>
</template>
