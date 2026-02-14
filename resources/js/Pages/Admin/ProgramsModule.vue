<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import ModalDialog from "../../Components/ui/ModalDialog.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canManagePrograms = computed(() => permissions.value.includes("programs.manage"));

const props = defineProps({
    section: { type: String, default: "programs_projects" },
    programs: { type: Object, default: () => ({ data: [], links: [] }) },
    monitoring: { type: Object, default: () => ({ data: [], links: [] }) },
    committeeReports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: "", status: "", sort: "created_at", direction: "desc" }) },
});

const titles = {
    programs_projects: "Programs & Projects",
    committee_reports: "Committee Reports",
    programs_monitoring: "Programs Monitoring",
};
const headerIcon = computed(() => {
    if (props.section === "committee_reports") return "reports";
    if (props.section === "programs_monitoring") return "analytics";
    return "projects";
});

const listPath = computed(() =>
    props.section === "programs_monitoring" ? "/admin/programs-monitoring" : "/admin/programs-projects"
);
const { search, sortBy, sortIndicator } = useListQuery({
    path: listPath,
    filters: computed(() => props.filters),
    defaultSort: "created_at",
    buildParams: ({ search: nextSearch, sort, direction, filters }) => ({
        search: nextSearch,
        status: filters?.status ?? "",
        sort,
        direction,
    }),
});

const searchPrograms = (value) => {
    search.value = value;
};

const changeStatusFilter = (value) => {
    const base = props.section === "programs_monitoring" ? "/admin/programs-monitoring" : "/admin/programs-projects";
    router.get(base, {
        search: props.filters?.search ?? "",
        status: value,
        sort: props.filters?.sort ?? "created_at",
        direction: props.filters?.direction ?? "desc",
    }, { preserveState: true, replace: true });
};

const createForm = useForm({
    title: "",
    description: "",
    committee: "",
    status: "planned",
    start_date: "",
    end_date: "",
    budget: "",
    participants: 0,
    remarks: "",
});

const editForm = useForm({
    title: "",
    description: "",
    committee: "",
    status: "planned",
    start_date: "",
    end_date: "",
    budget: "",
    participants: 0,
    remarks: "",
});

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedProgram = ref(null);

const submitCreate = () => {
    if (!canManagePrograms.value) return;
    createForm.post("/admin/programs-projects", { preserveScroll: true, onSuccess: () => createForm.reset() });
};

const openEditModal = (program) => {
    if (!canManagePrograms.value) return;
    selectedProgram.value = program;
    editForm.title = program.title ?? "";
    editForm.description = program.description ?? "";
    editForm.committee = program.committee ?? "";
    editForm.status = program.status ?? "planned";
    editForm.start_date = program.start_date ?? "";
    editForm.end_date = program.end_date ?? "";
    editForm.budget = program.budget ?? "";
    editForm.participants = program.participants ?? 0;
    editForm.remarks = program.remarks ?? "";
    showEditModal.value = true;
};

const closeEditModal = () => {
    selectedProgram.value = null;
    showEditModal.value = false;
};

const submitEdit = () => {
    if (!canManagePrograms.value) return;
    if (!selectedProgram.value) return;
    editForm.put(`/admin/programs-projects/${selectedProgram.value.id}`, { preserveScroll: true, onSuccess: () => closeEditModal() });
};

const openDeleteModal = (program) => {
    if (!canManagePrograms.value) return;
    selectedProgram.value = program;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    selectedProgram.value = null;
    showDeleteModal.value = false;
};

const confirmDelete = () => {
    if (!canManagePrograms.value) return;
    if (!selectedProgram.value) return;
    router.delete(`/admin/programs-projects/${selectedProgram.value.id}`, { preserveScroll: true, onSuccess: () => closeDeleteModal() });
};

const deleteMessage = computed(() =>
    selectedProgram.value ? `Delete ${selectedProgram.value.title}?` : "Delete selected program?"
);
</script>

<template>
    <AdminLayout :title="titles[props.section]" :user-name="userName">
        <template #header>
            <PageHeader :title="titles[props.section]" :icon="headerIcon" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <template v-if="props.section === 'programs_projects'">
            <div v-if="canManagePrograms" class="mb-4 rounded-lg border border-slate-200 p-4">
                <h3 class="mb-2 font-semibold">Create Program / Project</h3>
                <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="createForm.title" type="text" placeholder="Title" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="createForm.committee" type="text" placeholder="Committee" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <select v-model="createForm.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="planned">planned</option><option value="ongoing">ongoing</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select>
                    <input v-model="createForm.budget" type="number" step="0.01" min="0" placeholder="Budget" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="createForm.start_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="createForm.end_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="createForm.participants" type="number" min="0" placeholder="Participants" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="createForm.remarks" type="text" placeholder="Remarks" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <textarea v-model="createForm.description" placeholder="Description" class="md:col-span-4 rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white" @click="submitCreate">Save</button>
            </div>

            <div class="mb-3 flex flex-wrap gap-2">
                <input :value="props.filters.search" type="text" placeholder="Search programs..." class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm" @input="searchPrograms($event.target.value)" />
                <select :value="props.filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="changeStatusFilter($event.target.value)">
                    <option value="">All status</option><option value="planned">planned</option><option value="ongoing">ongoing</option><option value="completed">completed</option><option value="cancelled">cancelled</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('title')">Title {{ sortIndicator('title') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('committee')">Committee {{ sortIndicator('committee') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('status')">Status {{ sortIndicator('status') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('start_date')">Start {{ sortIndicator('start_date') }}</button></th><th v-if="canManagePrograms" class="px-4 py-3 text-left">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="program in props.programs.data" :key="program.id"><td class="px-4 py-3">{{ program.title }}</td><td class="px-4 py-3">{{ program.committee ?? '-' }}</td><td class="px-4 py-3">{{ program.status }}</td><td class="px-4 py-3">{{ program.start_date ?? '-' }}</td><td v-if="canManagePrograms" class="px-4 py-3"><div class="flex gap-2"><button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs" @click="openEditModal(program)">Edit</button><button type="button" class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700" @click="openDeleteModal(program)">Delete</button></div></td></tr>
                        <tr v-if="props.programs.data.length === 0"><td :colspan="canManagePrograms ? 5 : 4" class="px-4 py-6 text-center text-slate-500">No programs found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><Link v-for="link in props.programs.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-1 text-sm" :class="[link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-50' : '']" v-html="link.label" /></div>
        </template>

        <template v-else-if="props.section === 'committee_reports'">
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Committee</th><th class="px-4 py-3 text-left">Total Programs</th><th class="px-4 py-3 text-left">Ongoing</th><th class="px-4 py-3 text-left">Completed</th><th class="px-4 py-3 text-left">Participants</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.committeeReports" :key="row.committee"><td class="px-4 py-3">{{ row.committee }}</td><td class="px-4 py-3">{{ row.total_programs }}</td><td class="px-4 py-3">{{ row.ongoing_programs }}</td><td class="px-4 py-3">{{ row.completed_programs }}</td><td class="px-4 py-3">{{ row.participants }}</td></tr>
                        <tr v-if="props.committeeReports.length === 0"><td colspan="5" class="px-4 py-6 text-center text-slate-500">No committee data.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>

        <template v-else-if="props.section === 'programs_monitoring'">
            <div class="mb-3 flex flex-wrap gap-2">
                <input :value="props.filters.search" type="text" placeholder="Search monitoring..." class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm" @input="searchPrograms($event.target.value)" />
                <select :value="props.filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="changeStatusFilter($event.target.value)">
                    <option value="">All status</option><option value="planned">planned</option><option value="ongoing">ongoing</option><option value="completed">completed</option><option value="cancelled">cancelled</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('title')">Title {{ sortIndicator('title') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('category')">Category</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('status')">Status {{ sortIndicator('status') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('committee')">Committee {{ sortIndicator('committee') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('start_date')">Start {{ sortIndicator('start_date') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('end_date')">End {{ sortIndicator('end_date') }}</button></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in props.monitoring.data" :key="item.id"><td class="px-4 py-3">{{ item.title }}</td><td class="px-4 py-3">{{ item.category }}</td><td class="px-4 py-3">{{ item.status }}</td><td class="px-4 py-3">{{ item.committee ?? '-' }}</td><td class="px-4 py-3">{{ item.start_date ?? '-' }}</td><td class="px-4 py-3">{{ item.end_date ?? '-' }}</td></tr>
                        <tr v-if="props.monitoring.data.length === 0"><td colspan="6" class="px-4 py-6 text-center text-slate-500">No monitoring data.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><Link v-for="link in props.monitoring.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-1 text-sm" :class="[link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-50' : '']" v-html="link.label" /></div>
        </template>

        <ModalDialog :show="showEditModal" title="Edit Program / Project" max-width-class="max-w-3xl" @close="closeEditModal">
                <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="editForm.title" type="text" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="editForm.committee" type="text" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <select v-model="editForm.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="planned">planned</option><option value="ongoing">ongoing</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select>
                    <input v-model="editForm.budget" type="number" step="0.01" min="0" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="editForm.start_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="editForm.end_date" type="date" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="editForm.participants" type="number" min="0" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="editForm.remarks" type="text" class="rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    <textarea v-model="editForm.description" class="md:col-span-4 rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2"><button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeEditModal">Cancel</button><button type="button" class="ui-btn ui-btn--primary px-4 py-2 font-medium" @click="submitEdit">Save Changes</button></div>
        </ModalDialog>

        <ConfirmActionModal
            :show="showDeleteModal"
            title="Delete Program"
            :message="deleteMessage"
            confirm-label="Delete"
            confirm-variant="danger"
            @cancel="closeDeleteModal"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
