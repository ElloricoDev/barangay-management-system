<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");

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
        default: () => ({ search: "", module: "", sort: "created_at", direction: "desc" }),
    },
});

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/staff/upload-documents",
            {
                search: value,
                module: props.filters?.module ?? "",
                sort: props.filters?.sort ?? "created_at",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const moduleFilter = computed({
    get: () => props.filters?.module ?? "",
    set: (value) => {
        router.get(
            "/staff/upload-documents",
            {
                search: props.filters?.search ?? "",
                module: value,
                sort: props.filters?.sort ?? "created_at",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const sortBy = (column) => {
    const isCurrent = (props.filters?.sort ?? "created_at") === column;
    const nextDirection = isCurrent && (props.filters?.direction ?? "desc") === "asc" ? "desc" : "asc";

    router.get(
        "/staff/upload-documents",
        {
            search: props.filters?.search ?? "",
            module: props.filters?.module ?? "",
            sort: column,
            direction: nextDirection,
        },
        { preserveState: true, replace: true }
    );
};

const sortIndicator = (column) => {
    if ((props.filters?.sort ?? "created_at") !== column) return "";
    return (props.filters?.direction ?? "desc") === "asc" ? "^" : "v";
};

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
    selectedDocument.value = document;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedDocument.value = null;
};

const confirmDelete = () => {
    if (!selectedDocument.value) return;
    router.delete(`/staff/documents/${selectedDocument.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

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
</script>

<template>
    <StaffLayout title="Upload Documents" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Upload Documents</h2>
                <p class="text-sm text-slate-500">Attach supporting files for records and requests.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
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
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No uploaded documents found.</td>
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

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Delete Document</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Delete <span class="font-medium">{{ selectedDocument?.original_name }}</span>?
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
    </StaffLayout>
</template>
