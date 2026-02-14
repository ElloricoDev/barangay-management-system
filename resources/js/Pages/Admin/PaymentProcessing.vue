<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import PaymentFormFields from "../../Components/modules/PaymentFormFields.vue";
import PaymentTable from "../../Components/modules/PaymentTable.vue";
import ModalDialog from "../../Components/ui/ModalDialog.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canRecord = computed(() => permissions.value.includes("finance.record"));
const canGenerateReceipt = computed(() => permissions.value.includes("finance.receipts"));
const canRequestDisbursement = computed(() => permissions.value.includes("finance.disbursement.request"));
const canApproveDisbursement = computed(() => permissions.value.includes("finance.disbursement.approve"));
const canReleaseDisbursement = computed(() => permissions.value.includes("finance.disbursement.release"));

const props = defineProps({
    payments: { type: Object, required: true },
    residents: { type: Array, default: () => [] },
    disbursementRequests: { type: Array, default: () => [] },
    budgetAllocations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: "", sort: "paid_at", direction: "desc" }) },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/payment-processing",
    filters: computed(() => props.filters),
    defaultSort: "paid_at",
});

const createForm = useForm({
    resident_id: "",
    transaction_type: "revenue",
    workflow_status: "paid",
    or_number: "",
    revenue_source: "fines_fees",
    expense_type: "",
    request_reference: "",
    voucher_number: "",
    service_type: "certificate",
    description: "",
    amount: "",
    paid_at: "",
    approved_at: "",
    notes: "",
});
const editForm = useForm({
    resident_id: "",
    transaction_type: "revenue",
    workflow_status: "paid",
    or_number: "",
    revenue_source: "fines_fees",
    expense_type: "",
    request_reference: "",
    voucher_number: "",
    service_type: "certificate",
    description: "",
    amount: "",
    paid_at: "",
    approved_at: "",
    notes: "",
});
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedPayment = ref(null);
const selectedDisbursement = ref(null);
const showRejectDisbursementModal = ref(false);
const showReleaseDisbursementModal = ref(false);

const disbursementForm = useForm({
    budget_allocation_id: "",
    request_reference: "",
    expense_type: "administrative",
    purpose: "",
    amount: "",
    voucher_number: "",
    remarks: "",
});
const rejectDisbursementForm = useForm({
    rejection_reason: "",
});
const releaseDisbursementForm = useForm({
    or_number: "",
    description: "",
    voucher_number: "",
    paid_at: "",
    notes: "",
});

const submitCreate = () => {
    if (!canRecord.value) return;
    createForm.post("/admin/payments", {
        preserveScroll: true,
        onSuccess: () => createForm.reset(
            "resident_id",
            "or_number",
            "expense_type",
            "request_reference",
            "voucher_number",
            "service_type",
            "description",
            "amount",
            "paid_at",
            "approved_at",
            "notes"
        ),
    });
};

const openEditModal = (payment) => {
    if (!canRecord.value) return;
    selectedPayment.value = payment;
    editForm.resident_id = payment.resident_id ?? "";
    editForm.transaction_type = payment.transaction_type ?? "revenue";
    editForm.workflow_status = payment.workflow_status ?? "paid";
    editForm.or_number = payment.or_number ?? "";
    editForm.revenue_source = payment.revenue_source ?? "fines_fees";
    editForm.expense_type = payment.expense_type ?? "";
    editForm.request_reference = payment.request_reference ?? "";
    editForm.voucher_number = payment.voucher_number ?? "";
    editForm.service_type = payment.service_type;
    editForm.description = payment.description;
    editForm.amount = payment.amount;
    editForm.paid_at = payment.paid_at ? payment.paid_at.slice(0, 16) : "";
    editForm.approved_at = payment.approved_at ? payment.approved_at.slice(0, 16) : "";
    editForm.notes = payment.notes ?? "";
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedPayment.value = null;
};

const submitEdit = () => {
    if (!canRecord.value) return;
    if (!selectedPayment.value) return;
    editForm.put(`/admin/payments/${selectedPayment.value.id}`, { preserveScroll: true, onSuccess: () => closeEditModal() });
};

const openDeleteModal = (payment) => {
    if (!canRecord.value) return;
    selectedPayment.value = payment;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    selectedPayment.value = null;
    showDeleteModal.value = false;
};

const confirmDelete = () => {
    if (!canRecord.value) return;
    if (!selectedPayment.value) return;
    router.delete(`/admin/payments/${selectedPayment.value.id}`, { preserveScroll: true, onSuccess: () => closeDeleteModal() });
};

const submitDisbursementRequest = () => {
    if (!canRequestDisbursement.value) return;
    disbursementForm.post("/admin/disbursement-requests", {
        preserveScroll: true,
        onSuccess: () => disbursementForm.reset("budget_allocation_id", "request_reference", "purpose", "amount", "voucher_number", "remarks"),
    });
};

const approveDisbursement = (request) => {
    if (!canApproveDisbursement.value) return;
    router.patch(`/admin/disbursement-requests/${request.id}/approve`, {}, { preserveScroll: true });
};

const openRejectDisbursementModal = (request) => {
    if (!canApproveDisbursement.value) return;
    selectedDisbursement.value = request;
    rejectDisbursementForm.rejection_reason = "";
    showRejectDisbursementModal.value = true;
};

const closeRejectDisbursementModal = () => {
    selectedDisbursement.value = null;
    showRejectDisbursementModal.value = false;
};

const submitRejectDisbursement = () => {
    if (!selectedDisbursement.value) return;
    rejectDisbursementForm.patch(`/admin/disbursement-requests/${selectedDisbursement.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => closeRejectDisbursementModal(),
    });
};

const openReleaseDisbursementModal = (request) => {
    if (!canReleaseDisbursement.value) return;
    selectedDisbursement.value = request;
    releaseDisbursementForm.or_number = "";
    releaseDisbursementForm.description = request.purpose ?? "";
    releaseDisbursementForm.voucher_number = request.voucher_number ?? "";
    releaseDisbursementForm.paid_at = "";
    releaseDisbursementForm.notes = request.remarks ?? "";
    showReleaseDisbursementModal.value = true;
};

const closeReleaseDisbursementModal = () => {
    selectedDisbursement.value = null;
    showReleaseDisbursementModal.value = false;
};

const submitReleaseDisbursement = () => {
    if (!selectedDisbursement.value) return;
    releaseDisbursementForm.patch(`/admin/disbursement-requests/${selectedDisbursement.value.id}/release`, {
        preserveScroll: true,
        onSuccess: () => closeReleaseDisbursementModal(),
    });
};

const deleteMessage = computed(() => selectedPayment.value ? `Delete payment with OR number ${selectedPayment.value.or_number}?` : "Delete selected payment?");

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
};
const pretty = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
const statusClass = (status) => {
    if (status === "approved") return "bg-emerald-50 text-emerald-700 border-emerald-200";
    if (status === "rejected") return "bg-rose-50 text-rose-700 border-rose-200";
    if (status === "released") return "bg-sky-50 text-sky-700 border-sky-200";
    return "bg-amber-50 text-amber-700 border-amber-200";
};
</script>

<template>
    <AdminLayout title="Payment Processing" :user-name="userName">
        <template #header>
            <PageHeader title="Payment Processing" subtitle="Record and maintain financial transactions." icon="payment">
                <template #actions>
                    <input v-model="search" type="text" placeholder="Search payment..." class="ui-input max-w-xs" />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div v-if="canRecord" class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Record Payment</h3>
            <PaymentFormFields :form="createForm" :residents="props.residents" />
            <button type="button" class="ui-btn ui-btn--primary mt-3 px-4 py-2 font-medium" @click="submitCreate">Save Payment</button>
        </div>

        <div v-if="canRequestDisbursement" class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Disbursement Request</h3>
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <select v-model="disbursementForm.budget_allocation_id" class="ui-input">
                        <option value="">Budget Allocation (optional)</option>
                        <option v-for="allocation in props.budgetAllocations" :key="allocation.id" :value="allocation.id">
                            {{ allocation.fiscal_year }} - {{ pretty(allocation.budget_type) }} - {{ pretty(allocation.category) }} {{ allocation.program_name ? "(" + allocation.program_name + ")" : "" }}
                        </option>
                    </select>
                    <p v-if="disbursementForm.errors.budget_allocation_id" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.budget_allocation_id }}</p>
                </div>
                <div>
                    <select v-model="disbursementForm.expense_type" class="ui-input">
                        <option value="administrative">Administrative</option>
                        <option value="operations">Operations</option>
                        <option value="social_services">Social Services</option>
                        <option value="infrastructure">Infrastructure</option>
                        <option value="contingency">Contingency</option>
                        <option value="other_expense">Other Expense</option>
                    </select>
                    <p v-if="disbursementForm.errors.expense_type" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.expense_type }}</p>
                </div>
                <div>
                    <input v-model="disbursementForm.request_reference" type="text" class="ui-input" placeholder="Request Ref. (optional)" />
                    <p v-if="disbursementForm.errors.request_reference" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.request_reference }}</p>
                </div>
                <div>
                    <input v-model="disbursementForm.voucher_number" type="text" class="ui-input" placeholder="Voucher No. (optional)" />
                    <p v-if="disbursementForm.errors.voucher_number" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.voucher_number }}</p>
                </div>
                <div class="md:col-span-2">
                    <input v-model="disbursementForm.purpose" type="text" class="ui-input" placeholder="Purpose / Activity" />
                    <p v-if="disbursementForm.errors.purpose" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.purpose }}</p>
                </div>
                <div>
                    <input v-model="disbursementForm.amount" type="number" step="0.01" min="0.01" class="ui-input" placeholder="Amount" />
                    <p v-if="disbursementForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.amount }}</p>
                </div>
                <div>
                    <input v-model="disbursementForm.remarks" type="text" class="ui-input" placeholder="Remarks (optional)" />
                    <p v-if="disbursementForm.errors.remarks" class="mt-1 text-xs text-rose-600">{{ disbursementForm.errors.remarks }}</p>
                </div>
            </div>
            <button type="button" class="ui-btn ui-btn--primary mt-3 px-4 py-2 font-medium" @click="submitDisbursementRequest">Submit Request</button>
        </div>

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Disbursement Requests</h3>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Budget Allocation</th>
                            <th>Expense Type</th>
                            <th>Purpose</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Requested At</th>
                            <th>Approved At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in props.disbursementRequests" :key="item.id">
                            <td>{{ item.request_reference }}</td>
                            <td>
                                <span v-if="item.budget_allocation">
                                    {{ item.budget_allocation.fiscal_year }} - {{ pretty(item.budget_allocation.category) }}
                                </span>
                                <span v-else>-</span>
                            </td>
                            <td>{{ pretty(item.expense_type) }}</td>
                            <td>{{ item.purpose }}</td>
                            <td class="font-medium text-slate-800">{{ formatMoney(item.amount) }}</td>
                            <td>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">
                                    {{ pretty(item.status) }}
                                </span>
                            </td>
                            <td>{{ item.requester?.name ?? "-" }}</td>
                            <td>{{ formatDate(item.requested_at) }}</td>
                            <td>{{ formatDate(item.approved_at) }}</td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="canApproveDisbursement && item.status === 'requested'"
                                        type="button"
                                        class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                        @click="approveDisbursement(item)"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        v-if="canApproveDisbursement && (item.status === 'requested' || item.status === 'approved')"
                                        type="button"
                                        class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50"
                                        @click="openRejectDisbursementModal(item)"
                                    >
                                        Reject
                                    </button>
                                    <button
                                        v-if="canReleaseDisbursement && item.status === 'approved'"
                                        type="button"
                                        class="rounded-md border border-sky-300 px-2 py-1 text-xs text-sky-700 hover:bg-sky-50"
                                        @click="openReleaseDisbursementModal(item)"
                                    >
                                        Release
                                    </button>
                                    <span v-if="item.status === 'released'" class="text-xs text-slate-500">
                                        Posted OR {{ item.released_payment?.or_number ?? "-" }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.disbursementRequests.length === 0">
                            <td colspan="10" class="px-4 py-6 text-center text-slate-500">No disbursement requests found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PaymentTable :payments="props.payments.data" :sort-indicator="sortIndicator" :format-money="formatMoney" :format-date="formatDate" :show-actions="canRecord || canGenerateReceipt" @sort="sortBy">
            <template #actions="{ payment }">
                <div class="flex gap-2">
                    <a v-if="canGenerateReceipt" :href="`/admin/payments/${payment.id}/receipt`" target="_blank" class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Receipt</a>
                    <button v-if="canRecord" type="button" class="ui-btn ui-btn--ghost px-2 py-1 text-xs" @click="openEditModal(payment)">Edit</button>
                    <button v-if="canRecord" type="button" class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="openDeleteModal(payment)">Delete</button>
                </div>
            </template>
        </PaymentTable>

        <PaginationLinks :links="props.payments.links" />

        <ModalDialog :show="showEditModal" title="Edit Payment" max-width-class="max-w-3xl" @close="closeEditModal">
            <PaymentFormFields :form="editForm" :residents="props.residents" />
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeEditModal">Cancel</button>
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2 font-medium" @click="submitEdit">Save Changes</button>
            </div>
        </ModalDialog>

        <ModalDialog :show="showRejectDisbursementModal" title="Reject Disbursement Request" @close="closeRejectDisbursementModal">
            <textarea v-model="rejectDisbursementForm.rejection_reason" rows="4" class="ui-input" placeholder="Rejection reason (required)" />
            <p v-if="rejectDisbursementForm.errors.rejection_reason" class="mt-1 text-xs text-rose-600">{{ rejectDisbursementForm.errors.rejection_reason }}</p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeRejectDisbursementModal">Cancel</button>
                <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="submitRejectDisbursement">Reject</button>
            </div>
        </ModalDialog>

        <ModalDialog :show="showReleaseDisbursementModal" title="Release Disbursement" @close="closeReleaseDisbursementModal">
            <div class="grid gap-3">
                <div>
                    <input v-model="releaseDisbursementForm.or_number" type="text" class="ui-input" placeholder="OR Number (required)" />
                    <p v-if="releaseDisbursementForm.errors.or_number" class="mt-1 text-xs text-rose-600">{{ releaseDisbursementForm.errors.or_number }}</p>
                </div>
                <div>
                    <input v-model="releaseDisbursementForm.voucher_number" type="text" class="ui-input" placeholder="Voucher No. (optional)" />
                    <p v-if="releaseDisbursementForm.errors.voucher_number" class="mt-1 text-xs text-rose-600">{{ releaseDisbursementForm.errors.voucher_number }}</p>
                </div>
                <div>
                    <input v-model="releaseDisbursementForm.description" type="text" class="ui-input" placeholder="Description (optional)" />
                    <p v-if="releaseDisbursementForm.errors.description" class="mt-1 text-xs text-rose-600">{{ releaseDisbursementForm.errors.description }}</p>
                </div>
                <div>
                    <input v-model="releaseDisbursementForm.paid_at" type="datetime-local" class="ui-input" />
                    <p v-if="releaseDisbursementForm.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ releaseDisbursementForm.errors.paid_at }}</p>
                </div>
                <div>
                    <textarea v-model="releaseDisbursementForm.notes" rows="3" class="ui-input" placeholder="Notes (optional)" />
                    <p v-if="releaseDisbursementForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ releaseDisbursementForm.errors.notes }}</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeReleaseDisbursementModal">Cancel</button>
                <button type="button" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700" @click="submitReleaseDisbursement">Release</button>
            </div>
        </ModalDialog>

        <ConfirmActionModal :show="showDeleteModal" title="Delete Payment" :message="deleteMessage" confirm-label="Delete" confirm-variant="danger" @cancel="closeDeleteModal" @confirm="confirmDelete" />
    </AdminLayout>
</template>
