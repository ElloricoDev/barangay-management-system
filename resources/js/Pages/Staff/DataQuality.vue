<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canArchive = computed(() => permissions.value.includes("data.archive"));

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ search: "", archived: "active" }),
    },
    residents: {
        type: Object,
        required: true,
    },
    duplicates: {
        type: Array,
        default: () => [],
    },
});

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/staff/data-quality",
            {
                search: value,
                archived: props.filters?.archived ?? "active",
            },
            { preserveState: true, replace: true }
        );
    },
});

const archivedFilter = computed({
    get: () => props.filters?.archived ?? "active",
    set: (value) => {
        router.get(
            "/staff/data-quality",
            {
                search: props.filters?.search ?? "",
                archived: value,
            },
            { preserveState: true, replace: true }
        );
    },
});

const showArchiveModal = ref(false);
const selectedResident = ref(null);
const archiveMode = ref(true);
const archiveReason = ref("");

const openArchiveModal = (resident, archive) => {
    selectedResident.value = resident;
    archiveMode.value = archive;
    archiveReason.value = "";
    showArchiveModal.value = true;
};

const closeArchiveModal = () => {
    showArchiveModal.value = false;
    selectedResident.value = null;
    archiveReason.value = "";
};

const confirmArchiveAction = () => {
    if (!selectedResident.value) return;

    router.patch(
        `/staff/data-quality/residents/${selectedResident.value.id}/archive`,
        {
            archive: archiveMode.value,
            reason: archiveReason.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => closeArchiveModal(),
        }
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
    <StaffLayout title="Data Quality" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Data Quality</h2>
                <p class="text-sm text-slate-500">Detect duplicate residents and archive outdated records.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <h3 class="text-sm font-semibold text-amber-900">Potential Duplicate Residents</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-800">
                <li v-for="group in props.duplicates" :key="group.signature">
                    {{ group.signature }} - {{ group.count }} records (IDs: {{ group.resident_ids.join(", ") }})
                </li>
                <li v-if="props.duplicates.length === 0">No potential duplicates found.</li>
            </ul>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Name, contact..."
                    class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Show</label>
                <select
                    v-model="archivedFilter"
                    class="w-44 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="active">Active Only</option>
                    <option value="archived">Archived Only</option>
                    <option value="all">All</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Birthdate</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Contact</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Archived At</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="resident in props.residents.data" :key="resident.id">
                        <td class="px-4 py-3 text-slate-700">
                            {{ resident.last_name }}, {{ resident.first_name }} {{ resident.middle_name ?? "" }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.birthdate }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.contact_number ?? "-" }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                                :class="resident.archived_at ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                            >
                                {{ resident.archived_at ? "Archived" : "Active" }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(resident.archived_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button
                                    v-if="canArchive && !resident.archived_at"
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                    @click="openArchiveModal(resident, true)"
                                >
                                    Archive
                                </button>
                                <button
                                    v-if="canArchive && resident.archived_at"
                                    type="button"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                    @click="openArchiveModal(resident, false)"
                                >
                                    Restore
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.residents.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No residents found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.residents.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1 text-sm"
                :class="[
                    link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-300 bg-white text-slate-700',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <div v-if="showArchiveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">
                    {{ archiveMode ? "Archive Resident" : "Restore Resident" }}
                </h3>
                <p class="mt-2 text-sm text-slate-600">
                    {{ archiveMode ? "Archive" : "Restore" }}
                    <span class="font-medium">
                        {{ selectedResident?.last_name }}, {{ selectedResident?.first_name }}
                    </span>?
                </p>
                <div v-if="archiveMode" class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reason (optional)</label>
                    <textarea
                        v-model="archiveReason"
                        rows="3"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                        placeholder="Enter archive reason"
                    />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeArchiveModal">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white"
                        :class="archiveMode ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                        @click="confirmArchiveAction"
                    >
                        {{ archiveMode ? "Archive" : "Restore" }}
                    </button>
                </div>
            </div>
        </div>
    </StaffLayout>
</template>

