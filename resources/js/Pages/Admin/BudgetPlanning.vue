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

const props = defineProps({
    filters: { type: Object, default: () => ({ search: "", sort: "fiscal_year", direction: "desc", fiscal_year: new Date().getFullYear() }) },
    allocations: { type: Object, required: true },
    summary: { type: Object, default: () => ({ fiscal_year: new Date().getFullYear(), total_budget: 0, total_utilized: 0, total_pending: 0, total_available: 0 }) },
    categories: { type: Array, default: () => [] },
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
    category: props.categories[0] ?? "administrative",
    program_name: "",
    allocated_amount: "",
    revised_amount: "",
    remarks: "",
    status: "active",
};

const createForm = useForm({ ...baseForm });
const editForm = useForm({ ...baseForm });
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedRow = ref(null);

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const pretty = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());

const reloadByYear = () => {
    router.get("/admin/budget-planning", { ...props.filters, fiscal_year: fiscalYear.value }, { preserveState: true, preserveScroll: true, replace: true });
};

const submitCreate = () => {
    if (!canManage.value) return;
    createForm.fiscal_year = fiscalYear.value;
    createForm.post("/admin/budget-planning", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("program_name", "allocated_amount", "revised_amount", "remarks"),
    });
};

const openEdit = (row) => {
    if (!canManage.value) return;
    selectedRow.value = row;
    editForm.fiscal_year = row.fiscal_year;
    editForm.category = row.category;
    editForm.program_name = row.program_name ?? "";
    editForm.allocated_amount = row.allocated_amount;
    editForm.revised_amount = row.revised_amount;
    editForm.remarks = row.remarks ?? "";
    editForm.status = row.status ?? "active";
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
                <select v-model="createForm.category" class="ui-input">
                    <option v-for="category in props.categories" :key="category" :value="category">{{ pretty(category) }}</option>
                </select>
                <input v-model="createForm.program_name" type="text" class="ui-input" placeholder="Program / Activity (optional)" />
                <input v-model="createForm.allocated_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Allocated Amount" />
                <input v-model="createForm.revised_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Revised Amount (optional)" />
                <input v-model="createForm.remarks" type="text" class="ui-input md:col-span-3" placeholder="Remarks (optional)" />
                <select v-model="createForm.status" class="ui-input">
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select>
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
                        <th><button type="button" @click="sortBy('category')">Category {{ sortIndicator("category") }}</button></th>
                        <th>Program</th>
                        <th><button type="button" @click="sortBy('allocated_amount')">Allocated {{ sortIndicator("allocated_amount") }}</button></th>
                        <th><button type="button" @click="sortBy('revised_amount')">Revised {{ sortIndicator("revised_amount") }}</button></th>
                        <th>Utilized</th>
                        <th>Pending</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th v-if="canManage">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in props.allocations.data" :key="row.id">
                        <td>{{ row.fiscal_year }}</td>
                        <td>{{ pretty(row.category) }}</td>
                        <td>{{ row.program_name || "-" }}</td>
                        <td>{{ formatMoney(row.allocated_amount) }}</td>
                        <td>{{ formatMoney(row.effective_budget) }}</td>
                        <td>{{ formatMoney(row.utilized_amount) }}</td>
                        <td>{{ formatMoney(row.pending_amount) }}</td>
                        <td>{{ formatMoney(row.available_amount) }}</td>
                        <td>{{ pretty(row.status) }}</td>
                        <td v-if="canManage">
                            <div class="flex gap-2">
                                <button type="button" class="ui-btn ui-btn--ghost px-2 py-1 text-xs" @click="openEdit(row)">Edit</button>
                                <button type="button" class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="openDelete(row)">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.allocations.data.length === 0">
                        <td :colspan="canManage ? 10 : 9" class="px-4 py-6 text-center text-slate-500">No budget allocations found for this year.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks :links="props.allocations.links" />

        <ModalDialog :show="showEditModal" title="Edit Budget Allocation" @close="showEditModal = false">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="editForm.category" class="ui-input">
                    <option v-for="category in props.categories" :key="category" :value="category">{{ pretty(category) }}</option>
                </select>
                <input v-model="editForm.program_name" type="text" class="ui-input" placeholder="Program / Activity" />
                <input v-model="editForm.allocated_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Allocated Amount" />
                <input v-model="editForm.revised_amount" type="number" min="0.01" step="0.01" class="ui-input" placeholder="Revised Amount" />
                <input v-model="editForm.remarks" type="text" class="ui-input md:col-span-2" placeholder="Remarks" />
                <select v-model="editForm.status" class="ui-input">
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="showEditModal = false">Cancel</button>
                <button type="button" class="ui-btn ui-btn--primary px-4 py-2" @click="saveEdit">Save Changes</button>
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
