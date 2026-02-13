<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canRecord = computed(() => permissions.value.includes("finance.record"));
const canGenerateReceipt = computed(() => permissions.value.includes("finance.receipts"));
const canExportReports = computed(() => permissions.value.includes("finance.reports.export"));

const props = defineProps({
    activeSection: {
        type: String,
        default: "financial_management",
    },
    payments: {
        type: Object,
        required: true,
    },
    residents: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            total_collections: 0,
            transactions_count: 0,
            today_collections: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({ search: "", sort: "paid_at", direction: "desc" }),
    },
});

const sectionMeta = {
    financial_management: { title: "Financial Management", subtitle: "All payment and collection records." },
    payment_processing: { title: "Payment Processing", subtitle: "Record and update payments for services." },
    official_receipts: { title: "Official Receipts", subtitle: "Track receipt references and issued payments." },
    collection_reports: { title: "Collection Reports", subtitle: "Review collections with filters and sorting." },
    transaction_history: { title: "Transaction History", subtitle: "Inspect chronological payment transactions." },
    financial_summary: { title: "Financial Summary", subtitle: "View aggregate collections and totals." },
};

const activeMeta = computed(() => sectionMeta[props.activeSection] ?? sectionMeta.financial_management);
const currentPath = computed(() => page.url.split("?")[0]);
const currentQuery = computed(() => ({
    search: props.filters?.search ?? "",
    sort: props.filters?.sort ?? "paid_at",
    direction: props.filters?.direction ?? "desc",
}));

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            currentPath.value,
            {
                search: value,
                sort: props.filters?.sort ?? "paid_at",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const sortBy = (column) => {
    const isCurrent = (props.filters?.sort ?? "paid_at") === column;
    const nextDirection = isCurrent && (props.filters?.direction ?? "desc") === "asc" ? "desc" : "asc";

    router.get(
        currentPath.value,
        {
            search: props.filters?.search ?? "",
            sort: column,
            direction: nextDirection,
        },
        { preserveState: true, replace: true }
    );
};

const sortIndicator = (column) => {
    if ((props.filters?.sort ?? "paid_at") !== column) return "";
    return (props.filters?.direction ?? "desc") === "asc" ? "^" : "v";
};

const createForm = useForm({
    resident_id: "",
    or_number: "",
    service_type: "certificate",
    description: "",
    amount: "",
    paid_at: "",
    notes: "",
});

const submitCreate = () => {
    createForm.post("/admin/payments", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("resident_id", "or_number", "service_type", "description", "amount", "paid_at", "notes"),
    });
};

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedPayment = ref(null);

const editForm = useForm({
    resident_id: "",
    or_number: "",
    service_type: "certificate",
    description: "",
    amount: "",
    paid_at: "",
    notes: "",
});

const openEditModal = (payment) => {
    selectedPayment.value = payment;
    editForm.resident_id = payment.resident_id ?? "";
    editForm.or_number = payment.or_number;
    editForm.service_type = payment.service_type;
    editForm.description = payment.description;
    editForm.amount = payment.amount;
    editForm.paid_at = payment.paid_at ? payment.paid_at.slice(0, 16) : "";
    editForm.notes = payment.notes ?? "";
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedPayment.value = null;
    editForm.reset("resident_id", "or_number", "service_type", "description", "amount", "paid_at", "notes");
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!selectedPayment.value) return;

    editForm.put(`/admin/payments/${selectedPayment.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const openDeleteModal = (payment) => {
    selectedPayment.value = payment;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedPayment.value = null;
};

const confirmDelete = () => {
    if (!selectedPayment.value) return;

    router.delete(`/admin/payments/${selectedPayment.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return "-";
    const date = new Date(value);
    return date.toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
};
</script>

<template>
    <AdminLayout :title="activeMeta.title" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">{{ activeMeta.title }}</h2>
                <p class="text-sm text-slate-500">{{ activeMeta.subtitle }}</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Collections</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ formatMoney(props.summary.total_collections) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Transactions</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ props.summary.transactions_count }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Today Collections</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ formatMoney(props.summary.today_collections) }}</p>
            </div>
        </div>

        <div v-if="canRecord" class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Record Payment</h3>
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <select v-model="createForm.resident_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">Resident (optional)</option>
                        <option v-for="resident in props.residents" :key="resident.id" :value="resident.id">
                            {{ resident.last_name }}, {{ resident.first_name }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ createForm.errors.resident_id }}</p>
                </div>
                <div>
                    <input v-model="createForm.or_number" type="text" placeholder="OR Number" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.or_number" class="mt-1 text-xs text-rose-600">{{ createForm.errors.or_number }}</p>
                </div>
                <div>
                    <select v-model="createForm.service_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="certificate">Certificate</option>
                        <option value="clearance_fee">Clearance Fee</option>
                        <option value="blotter_fee">Blotter Fee</option>
                        <option value="other">Other Service</option>
                    </select>
                    <p v-if="createForm.errors.service_type" class="mt-1 text-xs text-rose-600">{{ createForm.errors.service_type }}</p>
                </div>
                <div>
                    <input v-model="createForm.description" type="text" placeholder="Description" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.description" class="mt-1 text-xs text-rose-600">{{ createForm.errors.description }}</p>
                </div>
                <div>
                    <input v-model="createForm.amount" type="number" step="0.01" min="0.01" placeholder="Amount" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ createForm.errors.amount }}</p>
                </div>
                <div>
                    <input v-model="createForm.paid_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ createForm.errors.paid_at }}</p>
                </div>
                <div class="md:col-span-2">
                    <input v-model="createForm.notes" type="text" placeholder="Notes (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ createForm.errors.notes }}</p>
                </div>
            </div>
            <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitCreate">
                Save Payment
            </button>
        </div>

        <div class="mb-4 flex items-end justify-between gap-3">
            <h3 class="font-semibold text-slate-800">Payments List</h3>
            <div class="flex w-full max-w-2xl items-center justify-end gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search payment..."
                    class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
                <a
                    v-if="canExportReports"
                    :href="`/admin/payments/export?search=${encodeURIComponent(currentQuery.search)}&sort=${encodeURIComponent(currentQuery.sort)}&direction=${encodeURIComponent(currentQuery.direction)}`"
                    class="rounded-md border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50"
                >
                    Export CSV
                </a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('or_number')">OR No. {{ sortIndicator("or_number") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('resident_name')">Resident {{ sortIndicator("resident_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('service_type')">Service {{ sortIndicator("service_type") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Description</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('amount')">Amount {{ sortIndicator("amount") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('paid_at')">Paid At {{ sortIndicator("paid_at") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Collector</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="payment in props.payments.data" :key="payment.id">
                        <td class="px-4 py-3 text-slate-700">{{ payment.or_number }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ payment.resident ? `${payment.resident.last_name}, ${payment.resident.first_name}` : "-" }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ payment.service_type }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ payment.description }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ formatMoney(payment.amount) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ formatDate(payment.paid_at) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ payment.collector?.name ?? "-" }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a
                                    v-if="canGenerateReceipt"
                                    :href="`/admin/payments/${payment.id}/receipt`"
                                    target="_blank"
                                    class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                >
                                    Receipt
                                </a>
                                <button v-if="canRecord" type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="openEditModal(payment)">
                                    Edit
                                </button>
                                <button v-if="canRecord" type="button" class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="openDeleteModal(payment)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.payments.data.length === 0">
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No payment records found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.payments.links"
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

        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Payment</h3>
                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <select v-model="editForm.resident_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Resident (optional)</option>
                            <option v-for="resident in props.residents" :key="resident.id" :value="resident.id">
                                {{ resident.last_name }}, {{ resident.first_name }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ editForm.errors.resident_id }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.or_number" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.or_number" class="mt-1 text-xs text-rose-600">{{ editForm.errors.or_number }}</p>
                    </div>
                    <div>
                        <select v-model="editForm.service_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="certificate">Certificate</option>
                            <option value="clearance_fee">Clearance Fee</option>
                            <option value="blotter_fee">Blotter Fee</option>
                            <option value="other">Other Service</option>
                        </select>
                        <p v-if="editForm.errors.service_type" class="mt-1 text-xs text-rose-600">{{ editForm.errors.service_type }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.description" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.description" class="mt-1 text-xs text-rose-600">{{ editForm.errors.description }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.amount" type="number" step="0.01" min="0.01" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.amount" class="mt-1 text-xs text-rose-600">{{ editForm.errors.amount }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.paid_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ editForm.errors.paid_at }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <input v-model="editForm.notes" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ editForm.errors.notes }}</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeEditModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitEdit">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Delete Payment</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Delete payment with OR number <span class="font-medium">{{ selectedPayment?.or_number }}</span>?
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
    </AdminLayout>
</template>
