<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import DashboardStatCard from "../../Components/ui/DashboardStatCard.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import ModalDialog from "../../Components/ui/ModalDialog.vue";
import { useListQuery } from "../../Composables/useListQuery";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canManage = computed(() => permissions.value.includes("finance.budget.manage"));
const canSubmit = computed(() => permissions.value.includes("finance.budget.submit"));
const canApprove = computed(() => permissions.value.includes("finance.budget.approve"));
const canShowActions = computed(() => canManage.value || canSubmit.value || canApprove.value);

const props = defineProps({
    filters: { type: Object, default: () => ({ search: "", sort: "fiscal_year", direction: "desc", fiscal_year: new Date().getFullYear() }) },
    allocations: { type: Object, required: true },
    summary: { type: Object, default: () => ({ fiscal_year: new Date().getFullYear(), total_budget: 0, total_utilized: 0, total_pending: 0, total_available: 0 }) },
    categories: { type: Array, default: () => [] },
    budgetTypes: { type: Array, default: () => [] },
    budgetStatuses: { type: Array, default: () => [] },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/budget-planning",
    filters: computed(() => props.filters),
    defaultSort: "fiscal_year",
    buildParams: ({ search, sort, direction }) => ({
        search,
        sort,
        direction,
        fiscal_year: fiscalYear.value,
    }),
});

const fiscalYear = ref(Number(props.filters?.fiscal_year ?? new Date().getFullYear()));
const years = computed(() => {
    const current = new Date().getFullYear();
    return [current - 1, current, current + 1, current + 2];
});

const baseForm = {
    fiscal_year: fiscalYear.value,
    budget_type: props.budgetTypes[0] ?? "annual",
    category: props.categories[0] ?? "administrative",
    program_name: "",
    resolution_no: "",
    allocated_amount: "",
    revised_amount: "",
    remarks: "",
    status: "draft",
};

const createForm = useForm({ ...baseForm });
const editForm = useForm({ ...baseForm });
const approveForm = useForm({
    resolution_no: "",
    remarks: "",
    approved_at: "",
});
const rejectForm = useForm({
    rejection_reason: "",
});

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const selectedRow = ref(null);

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const pretty = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
const statusClass = (status) => {
    if (status === "approved") return "bg-emerald-50 text-emerald-700 border-emerald-200";
    if (status === "rejected") return "bg-rose-50 text-rose-700 border-rose-200";
    if (status === "for_council_approval") return "bg-amber-50 text-amber-700 border-amber-200";
    return "bg-slate-100 text-slate-700 border-slate-200";
};
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
};

const reloadByYear = () => {
    router.get("/admin/budget-planning", { ...props.filters, fiscal_year: fiscalYear.value }, { preserveState: true, preserveScroll: true, replace: true });
};

const submitCreate = () => {
    if (!canManage.value) return;
    createForm.fiscal_year = fiscalYear.value;
    createForm.post("/admin/budget-planning", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("program_name", "resolution_no", "allocated_amount", "revised_amount", "remarks"),
    });
};

const openEdit = (row) => {
    if (!canManage.value) return;
    selectedRow.value = row;
    editForm.fiscal_year = row.fiscal_year;
    editForm.budget_type = row.budget_type ?? "annual";
    editForm.category = row.category;
    editForm.program_name = row.program_name ?? "";
    editForm.resolution_no = row.resolution_no ?? "";
    editForm.allocated_amount = row.allocated_amount;
    editForm.revised_amount = row.revised_amount;
    editForm.remarks = row.remarks ?? "";
    editForm.status = row.status ?? "draft";
    showEditModal.value = true;
};

const saveEdit = () => {
    if (!selectedRow.value) return;
    editForm.put(`/admin/budget-planning/${selectedRow.value.id}`, { preserveScroll: true, onSuccess: () => (showEditModal.value = false) });
};

const openDelete = (row) => {
    if (!canManage.value) return;
    selectedRow.value = row;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!selectedRow.value) return;
    router.delete(`/admin/budget-planning/${selectedRow.value.id}`, { preserveScroll: true, onSuccess: () => (showDeleteModal.value = false) });
};

const submitForApproval = (row) => {
    if (!canSubmit.value) return;
    router.patch(`/admin/budget-planning/${row.id}/submit`, {}, { preserveScroll: true });
};

const openApprove = (row) => {
    if (!canApprove.value) return;
    selectedRow.value = row;
    approveForm.resolution_no = row.resolution_no ?? "";
    approveForm.remarks = row.remarks ?? "";
    approveForm.approved_at = "";
    showApproveModal.value = true;
};

const submitApprove = () => {
    if (!selectedRow.value) return;
    approveForm.patch(`/admin/budget-planning/${selectedRow.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => (showApproveModal.value = false),
    });
};

const openReject = (row) => {
    if (!canApprove.value) return;
    selectedRow.value = row;
    rejectForm.rejection_reason = "";
    showRejectModal.value = true;
};

const submitReject = () => {
    if (!selectedRow.value) return;
    rejectForm.patch(`/admin/budget-planning/${selectedRow.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (showRejectModal.value = false),
    });
};
</script>

<template>
    <AdminLayout title="Budget Planning" :user-name="userName">
        <template #header>
            <PageHeader title="Budget Planning" subtitle="Annual and supplemental budget allocations by category." icon="summary">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <select v-model="fiscalYear" class="ui-input max-w-[120px]" @change="reloadByYear">
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
                        <input v-model="search" type="text" placeholder="Search budget..." class="ui-input max-w-xs" />
                    </div>
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <DashboardStatCard label="Total Budget" :value="formatMoney(props.summary.total_budget)" />
            <DashboardStatCard label="Utilized" :value="formatMoney(props.summary.total_utilized)" />
            <DashboardStatCard label="Pending Requests" :value="formatMoney(props.summary.total_pending)" />
            <DashboardStatCard label="Available Balance" :value="formatMoney(props.summary.total_available)" />
        </div>

        <div v-if="canManage" class="mt-4 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Add Budget Allocation</h3>
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <select v-model="createForm.budget_type" class="ui-input">
                        <option v-for="type in props.budgetTypes" :key="type" :value="type">{{ pretty(type) }}</option>
                    </select>
                    <p v-if="createForm.errors.budget_type" class="mt-1 text-xs text-rose-600">{{ createForm.errors.budget_type }}</p>
                </div>
                <div>
                    <select v-model="createForm.category" class="ui-input">
                        <option v-for="category in props.categories" :key="category" :value="category">{{ pretty(category) }}</option>
                    </select>
                    <p v-if="createForm.errors.category" class="mt-1 text-xs text-rose-600">{{ createForm.errors.category }}</p>
                </div>
                <div>
                    <input v-model="createForm.program_name" type="text" class="ui-input" placeholder="Program / Activity (optional)" />
                    <p v-if="createForm.errors.program_name" class="mt-1 text-xs text-rose-600">{{ createForm.errors.program_name }}</p>
                </div>
                <div>
                    <input v-model="createForm.resolution_no" type="text" class="ui-input" placeholder="Resolution No. (optional)" />
                    <p v-if="createForm.errors.resolution_no" class="mt-1 text-xs text-rose-600">{{ createForm.errors.resolution_no }}</p>
                </div>
                <div>
                    <input v-model="createForm.allocated_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Allocated Amount" />
                    <p v-if="createForm.errors.allocated_amount" class="mt-1 text-xs text-rose-600">{{ createForm.errors.allocated_amount }}</p>
                </div>
                <div>
                    <input v-model="createForm.revised_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Revised Amount (optional)" />
                    <p v-if="createForm.errors.revised_amount" class="mt-1 text-xs text-rose-600">{{ createForm.errors.revised_amount }}</p>
                </div>
                <div class="md:col-span-2">
                    <input v-model="createForm.remarks" type="text" class="ui-input" placeholder="Remarks (optional)" />
                    <p v-if="createForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ createForm.errors.remarks }}</p>
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" @click="submitCreate">Save Allocation</button>
            </div>
        </div>

        <div class="ui-table-wrap mt-4">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><button type="button" @click="sortBy('fiscal_year')">Year {{ sortIndicator("fiscal_year") }}</button></th>
                        <th><button type="button" @click="sortBy('budget_type')">Type {{ sortIndicator("budget_type") }}</button></th>
                        <th><button type="button" @click="sortBy('category')">Category {{ sortIndicator("category") }}</button></th>
                        <th>Program</th>
                        <th>Resolution</th>
                        <th><button type="button" @click="sortBy('allocated_amount')">Allocated {{ sortIndicator("allocated_amount") }}</button></th>
                        <th><button type="button" @click="sortBy('revised_amount')">Revised {{ sortIndicator("revised_amount") }}</button></th>
                        <th>Utilized</th>
                        <th>Pending</th>
                        <th>Available</th>
                        <th><button type="button" @click="sortBy('status')">Status {{ sortIndicator("status") }}</button></th>
                        <th><button type="button" @click="sortBy('approved_at')">Approved At {{ sortIndicator("approved_at") }}</button></th>
                        <th v-if="canShowActions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in props.allocations.data" :key="row.id">
                        <td>{{ row.fiscal_year }}</td>
                        <td>{{ pretty(row.budget_type) }}</td>
                        <td>{{ pretty(row.category) }}</td>
                        <td>{{ row.program_name || "-" }}</td>
                        <td>{{ row.resolution_no || "-" }}</td>
                        <td>{{ formatMoney(row.allocated_amount) }}</td>
                        <td>{{ formatMoney(row.effective_budget) }}</td>
                        <td>{{ formatMoney(row.utilized_amount) }}</td>
                        <td>{{ formatMoney(row.pending_amount) }}</td>
                        <td>{{ formatMoney(row.available_amount) }}</td>
                        <td>
                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(row.status)">
                                {{ pretty(row.status) }}
                            </span>
                        </td>
                        <td>{{ formatDate(row.approved_at) }}</td>
                        <td v-if="canShowActions">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="canManage && row.status !== 'approved' && row.status !== 'for_council_approval'"
                                    type="button"
                                    class="ui-btn ui-btn--ghost px-2 py-1 text-xs"
                                    @click="openEdit(row)"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="canManage && row.status !== 'approved'"
                                    type="button"
                                    class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                    @click="openDelete(row)"
                                >
                                    Delete
                                </button>
                                <button
                                    v-if="canSubmit && (row.status === 'draft' || row.status === 'rejected')"
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                    @click="submitForApproval(row)"
                                >
                                    Submit
                                </button>
                                <button
                                    v-if="canApprove && row.status === 'for_council_approval'"
                                    type="button"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                    @click="openApprove(row)"
                                >
                                    Approve
                                </button>
                                <button
                                    v-if="canApprove && row.status === 'for_council_approval'"
                                    type="button"
                                    class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                    @click="openReject(row)"
                                >
                                    Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.allocations.data.length === 0">
                        <td :colspan="canShowActions ? 13 : 12" class="px-4 py-6 text-center text-slate-500">No budget allocations found for this year.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks :links="props.allocations.links" />

        <ModalDialog :show="showEditModal" title="Edit Budget Allocation" @close="showEditModal = false">
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <select v-model="editForm.budget_type" class="ui-input">
                        <option v-for="type in props.budgetTypes" :key="type" :value="type">{{ pretty(type) }}</option>
                    </select>
                    <p v-if="editForm.errors.budget_type" class="mt-1 text-xs text-rose-600">{{ editForm.errors.budget_type }}</p>
                </div>
                <div>
                    <select v-model="editForm.category" class="ui-input">
                        <option v-for="category in props.categories" :key="category" :value="category">{{ pretty(category) }}</option>
                    </select>
                    <p v-if="editForm.errors.category" class="mt-1 text-xs text-rose-600">{{ editForm.errors.category }}</p>
                </div>
                <div>
                    <input v-model="editForm.program_name" type="text" class="ui-input" placeholder="Program / Activity" />
                    <p v-if="editForm.errors.program_name" class="mt-1 text-xs text-rose-600">{{ editForm.errors.program_name }}</p>
                </div>
                <div>
                    <input v-model="editForm.resolution_no" type="text" class="ui-input" placeholder="Resolution No." />
                    <p v-if="editForm.errors.resolution_no" class="mt-1 text-xs text-rose-600">{{ editForm.errors.resolution_no }}</p>
                </div>
                <div>
                    <input v-model="editForm.allocated_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Allocated Amount" />
                    <p v-if="editForm.errors.allocated_amount" class="mt-1 text-xs text-rose-600">{{ editForm.errors.allocated_amount }}</p>
                </div>
                <div>
                    <input v-model="editForm.revised_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Revised Amount" />
                    <p v-if="editForm.errors.revised_amount" class="mt-1 text-xs text-rose-600">{{ editForm.errors.revised_amount }}</p>
                </div>
                <div class="md:col-span-2">
                    <input v-model="editForm.remarks" type="text" class="ui-input" placeholder="Remarks" />
                    <p v-if="editForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ editForm.errors.remarks }}</p>
                </div>
                <div>
                    <select v-model="editForm.status" class="ui-input">
                        <option value="draft">Draft</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <p v-if="editForm.errors.status" class="mt-1 text-xs text-rose-600">{{ editForm.errors.status }}</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="showEditModal = false">Cancel</button>
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" @click="saveEdit">Save Changes</button>
            </div>
        </ModalDialog>

        <ModalDialog :show="showApproveModal" title="Approve Budget Allocation" @close="showApproveModal = false">
            <div class="grid gap-3">
                <div>
                    <input v-model="approveForm.resolution_no" type="text" class="ui-input" placeholder="Resolution No. (required)" />
                    <p v-if="approveForm.errors.resolution_no" class="mt-1 text-xs text-rose-600">{{ approveForm.errors.resolution_no }}</p>
                </div>
                <div>
                    <input v-model="approveForm.approved_at" type="datetime-local" class="ui-input" />
                    <p v-if="approveForm.errors.approved_at" class="mt-1 text-xs text-rose-600">{{ approveForm.errors.approved_at }}</p>
                </div>
                <div>
                    <textarea v-model="approveForm.remarks" rows="3" class="ui-input" placeholder="Approval remarks (optional)" />
                    <p v-if="approveForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ approveForm.errors.remarks }}</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="showApproveModal = false">Cancel</button>
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" @click="submitApprove">Approve</button>
            </div>
        </ModalDialog>

        <ModalDialog :show="showRejectModal" title="Reject Budget Allocation" @close="showRejectModal = false">
            <div class="grid gap-3">
                <div>
                    <textarea v-model="rejectForm.rejection_reason" rows="4" class="ui-input" placeholder="Rejection reason (required)" />
                    <p v-if="rejectForm.errors.rejection_reason" class="mt-1 text-xs text-rose-600">{{ rejectForm.errors.rejection_reason }}</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="showRejectModal = false">Cancel</button>
                <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="submitReject">Reject</button>
            </div>
        </ModalDialog>

        <ConfirmActionModal
            :show="showDeleteModal"
            title="Delete Budget Allocation"
            message="Delete this budget allocation record?"
            confirm-label="Delete"
            confirm-variant="danger"
            @cancel="showDeleteModal = false"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
