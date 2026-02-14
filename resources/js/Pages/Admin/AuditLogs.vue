<script setup>
import { computed, reactive, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";
import ModalDialog from "../../Components/ui/ModalDialog.vue";
import { actionLabel, moduleLabel } from "../../Utils/activityLabels";
import { permissionLabel } from "../../Utils/permissionLabels";

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

const selectedLog = ref(null);

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

const isDateOnlyString = (value) => {
    if (typeof value !== "string") return false;
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
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
    if (isPermissionField(key)) {
        if (Array.isArray(value)) return value.map((item) => permissionLabel(String(item))).join(", ");
        if (typeof value === "string") {
            const items = toStringList(value);
            if (items.length > 1) return items.map((item) => permissionLabel(item)).join(", ");
            return permissionLabel(value);
        }
    }
    if (typeof value === "boolean") return value ? "Yes" : "No";
    if (isDateOnlyString(value)) {
        const [year, month, day] = value.split("-");
        const date = new Date(Number(year), Number(month) - 1, Number(day));
        return date.toLocaleDateString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    }
    if (isIsoDateString(value)) return formatDate(value);
    if (Array.isArray(value)) return value.map((item) => formatValue(key, item)).join(", ");
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
};

const normalizeChangePayload = (payload) => {
    if (!payload) return {};
    if (typeof payload === "object" && !Array.isArray(payload)) return payload;

    if (typeof payload === "string") {
        try {
            const parsed = JSON.parse(payload);
            if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
                return parsed;
            }
            return { value: parsed };
        } catch {
            return { value: payload };
        }
    }

    return { value: payload };
};

const changedFields = (log) => {
    const before = normalizeChangePayload(log.before);
    const after = normalizeChangePayload(log.after);
    const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));

    return keys
        .filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
        .map((key) => ({
            key,
            label: formatFieldLabel(key),
            beforeRaw: before[key],
            afterRaw: after[key],
            before: formatValue(key, before[key]),
            after: formatValue(key, after[key]),
        }));
};

const toStringList = (value) => {
    if (Array.isArray(value)) return value.map((item) => String(item).trim()).filter(Boolean);
    if (typeof value === "string") {
        return value
            .split(",")
            .map((item) => item.trim())
            .filter(Boolean);
    }
    return [];
};

const listDiff = (beforeValue, afterValue) => {
    const beforeSet = new Set(toStringList(beforeValue));
    const afterSet = new Set(toStringList(afterValue));
    const added = Array.from(afterSet).filter((item) => !beforeSet.has(item));
    const removed = Array.from(beforeSet).filter((item) => !afterSet.has(item));
    return { added, removed };
};

const isPermissionField = (key) => {
    const normalized = String(key ?? "").toLowerCase();
    return normalized === "permissions" || normalized.endsWith("_permissions");
};

const compactChanges = (log) => {
    return changedFields(log).slice(0, 2);
};

const openDetails = (log) => {
    selectedLog.value = log;
};

const closeDetails = () => {
    selectedLog.value = null;
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
            <PageHeader title="Audit Logs" subtitle="Review approval history and sensitive system actions." icon="audit" />
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
                        {{ actionLabel(action) }}
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
                        <td class="px-4 py-3 text-slate-700">{{ actionLabel(log.action) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ moduleLabel(log.auditable_type) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ log.auditable_id }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <div v-if="changedFields(log).length > 0" class="space-y-2 text-xs">
                                <div v-for="change in compactChanges(log)" :key="change.key" class="rounded border border-slate-200 bg-slate-50 px-2 py-1.5">
                                    <p class="font-semibold text-slate-700">{{ change.label }}</p>
                                    <div v-if="isPermissionField(change.key)" class="mt-1 space-y-1">
                                        <div v-if="listDiff(change.beforeRaw, change.afterRaw).added.length > 0" class="flex flex-wrap gap-1">
                                            <span class="text-slate-500">+ Added:</span>
                                            <span
                                                v-for="item in listDiff(change.beforeRaw, change.afterRaw).added.slice(0, 4)"
                                                :key="`${change.key}-added-${item}`"
                                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700"
                                            >
                                                {{ permissionLabel(item) }}
                                            </span>
                                            <span v-if="listDiff(change.beforeRaw, change.afterRaw).added.length > 4" class="text-slate-500">
                                                +{{ listDiff(change.beforeRaw, change.afterRaw).added.length - 4 }} more
                                            </span>
                                        </div>
                                        <div v-if="listDiff(change.beforeRaw, change.afterRaw).removed.length > 0" class="flex flex-wrap gap-1">
                                            <span class="text-slate-500">- Removed:</span>
                                            <span
                                                v-for="item in listDiff(change.beforeRaw, change.afterRaw).removed.slice(0, 4)"
                                                :key="`${change.key}-removed-${item}`"
                                                class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-700"
                                            >
                                                {{ permissionLabel(item) }}
                                            </span>
                                            <span v-if="listDiff(change.beforeRaw, change.afterRaw).removed.length > 4" class="text-slate-500">
                                                +{{ listDiff(change.beforeRaw, change.afterRaw).removed.length - 4 }} more
                                            </span>
                                        </div>
                                        <p
                                            v-if="listDiff(change.beforeRaw, change.afterRaw).added.length === 0 && listDiff(change.beforeRaw, change.afterRaw).removed.length === 0"
                                            class="text-slate-500"
                                        >
                                            Updated list values.
                                        </p>
                                    </div>
                                    <div v-else class="mt-1 space-y-1">
                                        <p class="rounded bg-rose-50 px-2 py-1 text-rose-700">
                                            <span class="font-medium">Before:</span> {{ change.before }}
                                        </p>
                                        <p class="rounded bg-emerald-50 px-2 py-1 text-emerald-700">
                                            <span class="font-medium">After:</span> {{ change.after }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    v-if="changedFields(log).length > 2"
                                    type="button"
                                    class="text-xs font-medium text-slate-600 hover:underline"
                                    @click="openDetails(log)"
                                >
                                    View all {{ changedFields(log).length }} changes
                                </button>
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

        <ModalDialog :show="!!selectedLog" title="Audit Change Details" max-width-class="max-w-4xl" @close="closeDetails">
            <div v-if="selectedLog" class="space-y-2 text-xs">
                <div v-for="change in changedFields(selectedLog)" :key="change.key" class="rounded border border-slate-200 bg-slate-50 p-2">
                    <p class="mb-1 font-semibold text-slate-700">{{ change.label }}</p>
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
        </ModalDialog>
    </AdminLayout>
</template>
