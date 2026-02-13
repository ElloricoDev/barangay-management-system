<script setup>
import { computed, reactive } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    auditLogs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            user: "",
            action: "",
            module: "",
            date_from: "",
            date_to: "",
        }),
    },
    availableActions: {
        type: Array,
        default: () => [],
    },
});

const form = reactive({
    user: props.filters?.user ?? "",
    action: props.filters?.action ?? "",
    module: props.filters?.module ?? "",
    date_from: props.filters?.date_from ?? "",
    date_to: props.filters?.date_to ?? "",
});

const applyFilters = () => {
    router.get("/admin/audit-logs", form, { preserveState: true, replace: true });
};

const resetFilters = () => {
    form.user = "";
    form.action = "";
    form.module = "";
    form.date_from = "";
    form.date_to = "";
    router.get("/admin/audit-logs", {}, { preserveState: true, replace: true });
};

const moduleLabel = (auditableType) => {
    if (!auditableType) return "-";
    const parts = auditableType.split("\\");
    return parts[parts.length - 1];
};

const formatDate = (value) => {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "2-digit",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(date);
};

const isSensitiveKey = (key) => {
    if (!key) return false;
    const normalized = String(key).toLowerCase();
    return ["password", "token", "secret", "remember", "otp", "pin"].some((sensitive) =>
        normalized.includes(sensitive)
    );
};

const isIsoDateString = (value) => {
    if (typeof value !== "string") return false;
    return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(value);
};

const formatFieldLabel = (key) => {
    return String(key)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatValue = (key, value) => {
    if (isSensitiveKey(key)) return "[MASKED]";
    if (value === null || value === undefined) return "-";
    if (isIsoDateString(value)) return formatDate(value);
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
};

const changedFields = (log) => {
    const before = log.before ?? {};
    const after = log.after ?? {};
    const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));

    return keys
        .filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
        .map((key) => ({
            key,
            label: formatFieldLabel(key),
            before: formatValue(key, before[key]),
            after: formatValue(key, after[key]),
        }));
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (form.user) params.set("user", form.user);
    if (form.action) params.set("action", form.action);
    if (form.module) params.set("module", form.module);
    if (form.date_from) params.set("date_from", form.date_from);
    if (form.date_to) params.set("date_to", form.date_to);

    const query = params.toString();

    return query ? `/admin/audit-logs/export?${query}` : "/admin/audit-logs/export";
});
</script>

<template>
    <AdminLayout title="Audit Logs" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Audit Logs</h2>
                <p class="text-sm text-slate-500">Review approval history and sensitive system actions.</p>
            </div>
        </template>

        <div class="mb-4 rounded-lg border border-slate-200 p-4">
            <div class="grid gap-3 md:grid-cols-5">
                <input
                    v-model="form.user"
                    type="text"
                    placeholder="Filter by user"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />

                <select
                    v-model="form.action"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="">All actions</option>
                    <option v-for="action in props.availableActions" :key="action" :value="action">
                        {{ action }}
                    </option>
                </select>

                <select
                    v-model="form.module"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="">All modules</option>
                    <option value="certificate">Certificate</option>
                    <option value="blotter">Blotter</option>
                    <option value="user">User</option>
                </select>

                <input
                    v-model="form.date_from"
                    type="date"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />

                <input
                    v-model="form.date_to"
                    type="date"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
            </div>

            <div class="mt-3 flex gap-2">
                <button
                    type="button"
                    class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                    @click="applyFilters"
                >
                    Apply Filters
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100"
                    @click="resetFilters"
                >
                    Reset
                </button>
                <a
                    :href="exportUrl"
                    class="rounded-md border border-emerald-300 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                >
                    Export CSV
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">User</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Action</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Module</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Record ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="log in props.auditLogs.data" :key="log.id">
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(log.created_at) }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ log.user?.name ?? "System" }}
                            <span v-if="log.user?.email" class="text-xs text-slate-500">({{ log.user.email }})</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ log.action }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ moduleLabel(log.auditable_type) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ log.auditable_id }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <div v-if="changedFields(log).length > 0" class="space-y-1 text-xs">
                                <div v-for="change in changedFields(log)" :key="change.key" class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
                                    <div class="mb-1 font-semibold text-slate-700">{{ change.label }}</div>
                                    <div class="grid gap-1">
                                        <div class="rounded bg-rose-50 px-2 py-1 text-rose-700">
                                            <span class="font-medium">Before:</span> {{ change.before }}
                                        </div>
                                        <div class="rounded bg-emerald-50 px-2 py-1 text-emerald-700">
                                            <span class="font-medium">After:</span> {{ change.after }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500">
                                No field changes captured.
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.auditLogs.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No audit logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.auditLogs.links"
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
    </AdminLayout>
</template>
