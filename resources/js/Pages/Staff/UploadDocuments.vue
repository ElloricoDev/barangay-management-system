<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canUpload = computed(() => permissions.value.includes("documents.upload"));
const canDelete = computed(
    () => permissions.value.includes("documents.delete") || permissions.value.includes("documents.upload")
);

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    residents: {
        type: Array,
        default: () => [],
    },
    certificates: {
        type: Array,
        default: () => [],
    },
    blotters: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: "", module: "", status: "", sort: "created_at", direction: "desc" }),
    },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/staff/upload-documents",
    filters: computed(() => props.filters),
    defaultSort: "created_at",
    buildParams: ({ search: nextSearch, sort, direction, filters }) => ({
        search: nextSearch,
        module: filters?.module ?? "",
        status: filters?.status ?? "",
        sort,
        direction,
    }),
});

const moduleFilter = computed({
    get: () => props.filters?.module ?? "",
    set: (value) => {
        router.get(
            "/staff/upload-documents",
            {
                search: props.filters?.search ?? "",
                module: value,
                status: props.filters?.status ?? "",
                sort: props.filters?.sort ?? "created_at",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const statusFilter = computed({
    get: () => props.filters?.status ?? "",
    set: (value) => {
        router.get(
            "/staff/upload-documents",
            {
                search: props.filters?.search ?? "",
                module: props.filters?.module ?? "",
                status: value,
                sort: props.filters?.sort ?? "created_at",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const uploadForm = useForm({
    title: "",
    module: "",
    resident_id: "",
    certificate_id: "",
    blotter_id: "",
    notes: "",
    file: null,
});

const onFileChange = (event) => {
    uploadForm.file = event.target.files?.[0] ?? null;
};

const submitUpload = () => {
    if (!canUpload.value) return;
    uploadForm.post("/staff/upload-documents", {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset("title", "module", "resident_id", "certificate_id", "blotter_id", "notes", "file");
        },
    });
};

const showDeleteModal = ref(false);
const selectedDocument = ref(null);

const openDeleteModal = (document) => {
    if (!canDelete.value) return;
    selectedDocument.value = document;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedDocument.value = null;
};

const confirmDelete = () => {
    if (!canDelete.value) return;
    if (!selectedDocument.value) return;
    router.delete(`/staff/documents/${selectedDocument.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const deleteMessage = computed(() =>
    selectedDocument.value
        ? `Delete ${selectedDocument.value.original_name}?`
        : "Delete selected document?"
);

const formatBytes = (value) => {
    const bytes = Number(value ?? 0);
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
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

const statusClasses = (status) => {
    if (status === "approved") return "bg-emerald-50 text-emerald-700 border-emerald-200";
    if (status === "rejected") return "bg-rose-50 text-rose-700 border-rose-200";
    return "bg-amber-50 text-amber-700 border-amber-200";
};
</script>

<template>
    <StaffLayout title="Upload Documents" :user-name="userName">
        <template #header>
            <PageHeader title="Upload Documents" subtitle="Attach supporting files for records and requests." icon="upload" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div v-if="canUpload" class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Upload New File</h3>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <input v-model="uploadForm.title" type="text" placeholder="Document title" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="uploadForm.errors.title" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.title }}</p>
                </div>
                <div>
                    <select v-model="uploadForm.module" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">Select module</option>
                        <option value="resident">Resident</option>
                        <option value="certificate">Certificate</option>
                        <option value="blotter">Blotter</option>
                        <option value="other">Other</option>
                    </select>
                    <p v-if="uploadForm.errors.module" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.module }}</p>
                </div>
                <div>
                    <input type="file" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" @change="onFileChange" />
                    <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.file }}</p>
                </div>
                <div>
                    <select v-model="uploadForm.resident_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">Resident (optional)</option>
                        <option v-for="resident in props.residents" :key="resident.id" :value="resident.id">
                            {{ resident.last_name }}, {{ resident.first_name }}
                        </option>
                    </select>
                    <p v-if="uploadForm.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.resident_id }}</p>
                </div>
                <div>
                    <select v-model="uploadForm.certificate_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">Certificate (optional)</option>
                        <option v-for="certificate in props.certificates" :key="certificate.id" :value="certificate.id">
                            #{{ certificate.id }} - {{ certificate.type }} ({{ certificate.status }})
                        </option>
                    </select>
                    <p v-if="uploadForm.errors.certificate_id" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.certificate_id }}</p>
                </div>
                <div>
                    <select v-model="uploadForm.blotter_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">Blotter (optional)</option>
                        <option v-for="blotter in props.blotters" :key="blotter.id" :value="blotter.id">
                            #{{ blotter.id }} - {{ blotter.complainant_name }} vs {{ blotter.respondent_name }}
                        </option>
                    </select>
                    <p v-if="uploadForm.errors.blotter_id" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.blotter_id }}</p>
                </div>
                <div class="md:col-span-3">
                    <input v-model="uploadForm.notes" type="text" placeholder="Notes (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="uploadForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.notes }}</p>
                </div>
            </div>
            <button type="button" class="mt-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600" @click="submitUpload">
                Upload File
            </button>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Title, filename, module..."
                    class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Module</label>
                <select
                    v-model="moduleFilter"
                    class="w-44 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="">All Modules</option>
                    <option value="resident">Resident</option>
                    <option value="certificate">Certificate</option>
                    <option value="blotter">Blotter</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                <select
                    v-model="statusFilter"
                    class="w-44 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('title')">Title {{ sortIndicator("title") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('module')">Module {{ sortIndicator("module") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('status')">Status {{ sortIndicator("status") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('original_name')">File {{ sortIndicator("original_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('file_size')">Size {{ sortIndicator("file_size") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('created_at')">Uploaded {{ sortIndicator("created_at") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="document in props.documents.data" :key="document.id">
                        <td class="px-4 py-3 text-slate-700">{{ document.title }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ document.module ?? "-" }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium"
                                :class="statusClasses(document.status)"
                            >
                                {{ document.status ?? "submitted" }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ document.original_name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ formatBytes(document.file_size) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(document.created_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a
                                    :href="`/staff/documents/${document.id}/download`"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                >
                                    Download
                                </a>
                                <button
                                    v-if="canDelete"
                                    type="button"
                                    class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                    @click="openDeleteModal(document)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.documents.data.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No uploaded documents found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.documents.links"
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

        <ConfirmActionModal
            :show="showDeleteModal"
            title="Delete Document"
            :message="deleteMessage"
            confirm-label="Delete"
            confirm-variant="danger"
            @cancel="closeDeleteModal"
            @confirm="confirmDelete"
        />
    </StaffLayout>
</template>
