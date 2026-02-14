<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canDelete = computed(() => permissions.value.includes("documents.delete"));
const canApprove = computed(() => permissions.value.includes("documents.approve"));

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: "", module: "", status: "", sort: "created_at", direction: "desc" }),
    },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/document-archive",
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
            "/admin/document-archive",
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
            "/admin/document-archive",
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

const showDeleteModal = ref(false);
const selectedDocument = ref(null);
const showApprovalModal = ref(false);
const approvalAction = ref("approve");
const approvalReason = ref("");

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
    router.delete(`/admin/documents/${selectedDocument.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const openApprovalModal = (document, action) => {
    if (!canApprove.value) return;
    selectedDocument.value = document;
    approvalAction.value = action;
    approvalReason.value = "";
    showApprovalModal.value = true;
};

const closeApprovalModal = () => {
    showApprovalModal.value = false;
    selectedDocument.value = null;
    approvalReason.value = "";
};

const confirmApprovalAction = () => {
    if (!canApprove.value) return;
    if (!selectedDocument.value) return;

    const endpoint = approvalAction.value === "approve" ? "approve" : "reject";
    const payload = approvalAction.value === "reject" ? { reason: approvalReason.value } : {};

    router.patch(`/admin/documents/${selectedDocument.value.id}/${endpoint}`, payload, {
        preserveScroll: true,
        onSuccess: () => closeApprovalModal(),
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

const statusClasses = (status) => {
    if (status === "approved") return "bg-emerald-50 text-emerald-700 border-emerald-200";
    if (status === "rejected") return "bg-rose-50 text-rose-700 border-rose-200";
    return "bg-amber-50 text-amber-700 border-amber-200";
};
</script>

<template>
    <AdminLayout title="Document Archive" :user-name="userName">
        <template #header>
            <PageHeader title="Document Archive" subtitle="Central archive for uploaded supporting files." icon="archive" />
        </template>

        <FlashMessages :flash="page.props.flash" />

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
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Resident</th>
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
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">By</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="document in props.documents.data" :key="document.id">
                        <td class="px-4 py-3 text-slate-700">{{ document.title }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ document.module ?? "-" }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ document.resident ? `${document.resident.last_name}, ${document.resident.first_name}` : "-" }}
                        </td>
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
                        <td class="px-4 py-3 text-slate-700">{{ document.uploader?.name ?? "-" }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a
                                    :href="`/admin/documents/${document.id}/download`"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                >
                                    Download
                                </a>
                                <button
                                    v-if="canApprove && document.status !== 'approved'"
                                    type="button"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                    @click="openApprovalModal(document, 'approve')"
                                >
                                    Approve
                                </button>
                                <button
                                    v-if="canApprove && document.status !== 'rejected'"
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                    @click="openApprovalModal(document, 'reject')"
                                >
                                    Reject
                                </button>
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
                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">No documents found.</td>
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
                    link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700',
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

        <div v-if="showApprovalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">
                    {{ approvalAction === "approve" ? "Approve Document" : "Reject Document" }}
                </h3>
                <p class="mt-2 text-sm text-slate-600">
                    {{ approvalAction === "approve" ? "Approve" : "Reject" }}
                    <span class="font-medium">{{ selectedDocument?.original_name }}</span>?
                </p>
                <div v-if="approvalAction === 'reject'" class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reason (optional)</label>
                    <textarea
                        v-model="approvalReason"
                        rows="3"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                        placeholder="Enter reason for rejection"
                    />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeApprovalModal">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white"
                        :class="approvalAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700'"
                        @click="confirmApprovalAction"
                    >
                        {{ approvalAction === "approve" ? "Approve" : "Reject" }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
