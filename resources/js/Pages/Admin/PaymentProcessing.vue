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

const props = defineProps({
    payments: { type: Object, required: true },
    residents: { type: Array, default: () => [] },
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

const deleteMessage = computed(() => selectedPayment.value ? `Delete payment with OR number ${selectedPayment.value.or_number}?` : "Delete selected payment?");

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
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

        <ConfirmActionModal :show="showDeleteModal" title="Delete Payment" :message="deleteMessage" confirm-label="Delete" confirm-variant="danger" @cancel="closeDeleteModal" @confirm="confirmDelete" />
    </AdminLayout>
</template>
