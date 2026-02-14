<script setup>
import { computed, reactive, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";
import ModalDialog from "../../Components/ui/ModalDialog.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canManage = computed(() => permissions.value.includes("finance.submissions.manage"));
const canReview = computed(() => permissions.value.includes("finance.submissions.review"));

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ search: "", agency: "", report_type: "", status: "", sort: "created_at", direction: "desc" }),
    },
    submissions: {
        type: Object,
        required: true,
    },
    submissionAgencies: {
        type: Array,
        default: () => [],
    },
    submissionReportTypes: {
        type: Array,
        default: () => [],
    },
    submissionStatuses: {
        type: Array,
        default: () => [],
    },
    financeDocuments: {
        type: Array,
        default: () => [],
    },
});

const filterForm = reactive({
    search: props.filters?.search ?? "",
    agency: props.filters?.agency ?? "",
    report_type: props.filters?.report_type ?? "",
    status: props.filters?.status ?? "",
    sort: props.filters?.sort ?? "created_at",
    direction: props.filters?.direction ?? "desc",
});

const createForm = useForm({
    agency: props.submissionAgencies?.[0] ?? "coa",
    report_type: props.submissionReportTypes?.[0] ?? "annual_budget",
    period_start: "",
    period_end: "",
    reference_no: "",
    document_id: "",
    remarks: "",
});

const returnForm = useForm({
    review_notes: "",
});

const showReturnModal = ref(false);
const selectedSubmission = ref(null);

const applyFilters = () => {
    router.get("/admin/financial-submissions", filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.search = "";
    filterForm.agency = "";
    filterForm.report_type = "";
    filterForm.status = "";
    filterForm.sort = "created_at";
    filterForm.direction = "desc";
    applyFilters();
};

const submitRecord = () => {
    if (!canManage.value) return;
    createForm.post("/admin/financial-submissions", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("period_start", "period_end", "reference_no", "document_id", "remarks"),
    });
};

const submitToAgency = (submission) => {
    if (!canManage.value) return;
    router.patch(`/admin/financial-submissions/${submission.id}/submit`, {}, { preserveScroll: true });
};

const acknowledgeSubmission = (submission) => {
    if (!canReview.value) return;
    router.patch(`/admin/financial-submissions/${submission.id}/acknowledge`, {}, { preserveScroll: true });
};

const openReturnModal = (submission) => {
    if (!canReview.value) return;
    selectedSubmission.value = submission;
    returnForm.review_notes = "";
    showReturnModal.value = true;
};

const closeReturnModal = () => {
    selectedSubmission.value = null;
    showReturnModal.value = false;
};

const submitReturn = () => {
    if (!selectedSubmission.value) return;
    returnForm.patch(`/admin/financial-submissions/${selectedSubmission.value.id}/return`, {
        preserveScroll: true,
        onSuccess: () => closeReturnModal(),
    });
};

const pretty = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());

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

const statusClass = (status) => {
    if (status === "submitted") return "bg-sky-50 text-sky-700 border-sky-200";
    if (status === "acknowledged") return "bg-emerald-50 text-emerald-700 border-emerald-200";
    if (status === "returned") return "bg-amber-50 text-amber-700 border-amber-200";
    return "bg-slate-100 text-slate-700 border-slate-200";
};

const documentLabel = (document) => `#${document.id} - ${document.title} (${pretty(document.module ?? "other")})`;
</script>

<template>
    <AdminLayout title="Financial Submissions" :user-name="userName">
        <template #header>
            <PageHeader title="Financial Submissions" subtitle="Track COA/DBM report submissions and review outcomes." icon="reports" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-4 grid gap-3 rounded-lg border border-slate-200 p-4 md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
                <input v-model="filterForm.search" type="text" class="ui-input" placeholder="Reference, report type, notes..." />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Agency</label>
                <select v-model="filterForm.agency" class="ui-input">
                    <option value="">All Agencies</option>
                    <option v-for="agency in props.submissionAgencies" :key="agency" :value="agency">{{ pretty(agency) }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Report Type</label>
                <select v-model="filterForm.report_type" class="ui-input">
                    <option value="">All Report Types</option>
                    <option v-for="reportType in props.submissionReportTypes" :key="reportType" :value="reportType">{{ pretty(reportType) }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                <select v-model="filterForm.status" class="ui-input">
                    <option value="">All Statuses</option>
                    <option v-for="status in props.submissionStatuses" :key="status" :value="status">{{ pretty(status) }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-3 py-2" @click="applyFilters">Apply</button>
                <button type="button" class="ui-btn ui-btn--ghost px-3 py-2" @click="resetFilters">Reset</button>
            </div>
        </div>

        <div v-if="canManage" class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Submission Record</h3>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <select v-model="createForm.agency" class="ui-input">
                        <option v-for="agency in props.submissionAgencies" :key="agency" :value="agency">{{ pretty(agency) }}</option>
                    </select>
                    <p v-if="createForm.errors.agency" class="mt-1 text-xs text-rose-600">{{ createForm.errors.agency }}</p>
                </div>
                <div>
                    <select v-model="createForm.report_type" class="ui-input">
                        <option v-for="reportType in props.submissionReportTypes" :key="reportType" :value="reportType">{{ pretty(reportType) }}</option>
                    </select>
                    <p v-if="createForm.errors.report_type" class="mt-1 text-xs text-rose-600">{{ createForm.errors.report_type }}</p>
                </div>
                <div>
                    <input v-model="createForm.reference_no" type="text" class="ui-input" placeholder="Reference No. (optional)" />
                    <p v-if="createForm.errors.reference_no" class="mt-1 text-xs text-rose-600">{{ createForm.errors.reference_no }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Period Start</label>
                    <input v-model="createForm.period_start" type="date" class="ui-input" />
                    <p v-if="createForm.errors.period_start" class="mt-1 text-xs text-rose-600">{{ createForm.errors.period_start }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Period End</label>
                    <input v-model="createForm.period_end" type="date" class="ui-input" />
                    <p v-if="createForm.errors.period_end" class="mt-1 text-xs text-rose-600">{{ createForm.errors.period_end }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Supporting Document</label>
                    <select v-model="createForm.document_id" class="ui-input">
                        <option value="">Select approved document</option>
                        <option v-for="document in props.financeDocuments" :key="document.id" :value="document.id">{{ documentLabel(document) }}</option>
                    </select>
                    <p v-if="createForm.errors.document_id" class="mt-1 text-xs text-rose-600">{{ createForm.errors.document_id }}</p>
                </div>
                <div class="md:col-span-3">
                    <textarea v-model="createForm.remarks" rows="2" class="ui-input" placeholder="Remarks (optional)" />
                    <p v-if="createForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ createForm.errors.remarks }}</p>
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" @click="submitRecord">Save Record</button>
            </div>
        </div>

        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Agency</th>
                        <th>Report Type</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Document</th>
                        <th>Prepared By</th>
                        <th>Submitted At</th>
                        <th>Reviewed At</th>
                        <th>Review Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="submission in props.submissions.data" :key="submission.id">
                        <td>{{ submission.reference_no }}</td>
                        <td>{{ pretty(submission.agency) }}</td>
                        <td>{{ pretty(submission.report_type) }}</td>
                        <td>{{ submission.period_start }} to {{ submission.period_end }}</td>
                        <td>
                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(submission.status)">
                                {{ pretty(submission.status) }}
                            </span>
                        </td>
                        <td>{{ submission.document?.title ?? "-" }}</td>
                        <td>{{ submission.creator?.name ?? "-" }}</td>
                        <td>{{ formatDate(submission.submitted_at) }}</td>
                        <td>{{ formatDate(submission.reviewed_at) }}</td>
                        <td class="max-w-xs truncate">{{ submission.review_notes ?? "-" }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="canManage && (submission.status === 'draft' || submission.status === 'returned')"
                                    type="button"
                                    class="rounded-md border border-sky-300 px-2 py-1 text-xs text-sky-700 hover:bg-sky-50"
                                    @click="submitToAgency(submission)"
                                >
                                    Submit
                                </button>
                                <button
                                    v-if="canReview && submission.status === 'submitted'"
                                    type="button"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                    @click="acknowledgeSubmission(submission)"
                                >
                                    Acknowledge
                                </button>
                                <button
                                    v-if="canReview && submission.status === 'submitted'"
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                    @click="openReturnModal(submission)"
                                >
                                    Return
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.submissions.data.length === 0">
                        <td colspan="11" class="px-4 py-6 text-center text-slate-500">No submission records found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks :links="props.submissions.links" />

        <ModalDialog :show="showReturnModal" title="Return Submission" @close="closeReturnModal">
            <textarea v-model="returnForm.review_notes" rows="4" class="ui-input" placeholder="Return reason (required)" />
            <p v-if="returnForm.errors.review_notes" class="mt-1 text-xs text-rose-600">{{ returnForm.errors.review_notes }}</p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeReturnModal">Cancel</button>
                <button type="button" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700" @click="submitReturn">
                    Return
                </button>
            </div>
        </ModalDialog>
    </AdminLayout>
</template>
