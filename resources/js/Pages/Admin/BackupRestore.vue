<script setup>
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canRestore = computed(() => permissions.value.includes("backup.restore"));

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
});

const showDeleteModal = ref(false);
const showRestoreModal = ref(false);
const selectedFile = ref(null);

const createBackup = () => {
    router.post("/admin/backup-restore/create", {}, { preserveScroll: true });
};

const openDeleteModal = (file) => {
    selectedFile.value = file;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    selectedFile.value = null;
    showDeleteModal.value = false;
};

const confirmDelete = () => {
    if (!selectedFile.value) return;
    router.delete(`/admin/backup-restore/${encodeURIComponent(selectedFile.value.name)}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const openRestoreModal = (file) => {
    selectedFile.value = file;
    showRestoreModal.value = true;
};

const closeRestoreModal = () => {
    selectedFile.value = null;
    showRestoreModal.value = false;
};

const confirmRestore = () => {
    if (!selectedFile.value) return;
    router.post(
        `/admin/backup-restore/${encodeURIComponent(selectedFile.value.name)}/restore`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => closeRestoreModal(),
        }
    );
};

const formatBytes = (value) => {
    const bytes = Number(value ?? 0);
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (timestamp) => {
    if (!timestamp) return "-";
    return new Date(Number(timestamp) * 1000).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};
</script>

<template>
    <AdminLayout title="Backup & Restore" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Backup & Restore</h2>
                <p class="text-sm text-slate-500">Create, download, restore, and delete system backup snapshots.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Backup Files</h3>
            <button type="button" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="createBackup">
                Create Backup
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">File</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Size</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Created</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="file in props.files" :key="file.path">
                        <td class="px-4 py-3 text-slate-700">{{ file.name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ formatBytes(file.size) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(file.last_modified) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a
                                    :href="`/admin/backup-restore/${encodeURIComponent(file.name)}/download`"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                >
                                    Download
                                </a>
                                <button
                                    v-if="canRestore"
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                    @click="openRestoreModal(file)"
                                >
                                    Restore
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                    @click="openDeleteModal(file)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.files.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No backup files available.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Delete Backup</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Delete backup file <span class="font-medium">{{ selectedFile?.name }}</span>?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeDeleteModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="confirmDelete">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showRestoreModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Restore Backup</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Restoring <span class="font-medium">{{ selectedFile?.name }}</span> will overwrite current database data.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeRestoreModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700" @click="confirmRestore">
                        Restore
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
