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
const canManageYouth = computed(() => permissions.value.includes("youth.manage"));

const props = defineProps({
    section: { type: String, default: "youth_management" },
    stats: { type: Object, default: () => ({ youth_residents: 0, active_programs: 0, completed_programs: 0 }) },
    recentPrograms: { type: Array, default: () => [] },
    youthResidents: { type: Object, default: () => ({ data: [], links: [] }) },
    youthPrograms: { type: Object, default: () => ({ data: [], links: [] }) },
    reportSummary: { type: Object, default: () => ({}) },
    reportByCommittee: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: "", status: "", sort: "created_at", direction: "desc" }) },
});

const titles = {
    youth_management: "Youth Management (SK)",
    youth_residents: "Youth Residents",
    youth_programs: "Youth Programs",
    youth_reports: "Youth Reports",
};

const subtitle = {
    youth_management: "Overview of youth records and activities.",
    youth_residents: "Residents aged 30 and below.",
    youth_programs: "Manage SK programs and events.",
    youth_reports: "Youth participation and program summaries.",
};
const headerIcon = computed(() => {
    if (props.section === "youth_reports") return "reports";
    if (props.section === "youth_programs") return "projects";
    return "youth";
});

const residentsQuery = useListQuery({
    path: "/admin/youth-residents",
    filters: computed(() => props.filters),
    defaultSort: "last_name",
    defaultDirection: "asc",
});
const programsQuery = useListQuery({
    path: "/admin/youth-programs",
    filters: computed(() => props.filters),
    defaultSort: "created_at",
    buildParams: ({ search: nextSearch, sort, direction, filters }) => ({
        search: nextSearch,
        status: filters?.status ?? "",
        sort,
        direction,
    }),
});

const sortBy = (column) => {
    if (props.section === "youth_residents") {
        residentsQuery.sortBy(column);
        return;
    }
    programsQuery.sortBy(column);
};

const sortIndicator = (column) => {
    if (props.section === "youth_residents") return residentsQuery.sortIndicator(column);
    return programsQuery.sortIndicator(column);
};

const searchYouthResidents = (value) => {
    residentsQuery.search.value = value;
};

const searchYouthPrograms = (value) => {
    programsQuery.search.value = value;
};

const changeProgramStatusFilter = (value) => {
    router.get("/admin/youth-programs", {
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

const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedProgram = ref(null);

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

const submitCreate = () => {
    if (!canManageYouth.value) return;
    createForm.post("/admin/youth-programs", {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const openEditModal = (program) => {
    if (!canManageYouth.value) return;
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
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedProgram.value = null;
};

const submitEdit = () => {
    if (!canManageYouth.value) return;
    if (!selectedProgram.value) return;
    editForm.put(`/admin/youth-programs/${selectedProgram.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const openDeleteModal = (program) => {
    if (!canManageYouth.value) return;
    selectedProgram.value = program;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedProgram.value = null;
};

const confirmDelete = () => {
    if (!canManageYouth.value) return;
    if (!selectedProgram.value) return;
    router.delete(`/admin/youth-programs/${selectedProgram.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const deleteMessage = computed(() =>
    selectedProgram.value ? `Delete ${selectedProgram.value.title}?` : "Delete selected program?"
);
</script>

<template>
    <AdminLayout :title="titles[props.section]" :user-name="userName">
        <template #header>
            <PageHeader :title="titles[props.section]" :subtitle="subtitle[props.section]" :icon="headerIcon" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <template v-if="props.section === 'youth_management'">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Youth Residents</p><p class="mt-2 text-2xl font-bold">{{ props.stats.youth_residents }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Active Programs</p><p class="mt-2 text-2xl font-bold">{{ props.stats.active_programs }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Completed Programs</p><p class="mt-2 text-2xl font-bold">{{ props.stats.completed_programs }}</p></div>
            </div>
            <div class="mt-4 rounded-lg border border-slate-200 p-4">
                <div class="mb-2 flex items-center justify-between"><h3 class="font-semibold">Recent Youth Programs</h3><Link href="/admin/youth-programs" class="text-sm text-slate-600 hover:underline">View all</Link></div>
                <ul class="space-y-2 text-sm text-slate-700">
                    <li v-for="program in props.recentPrograms" :key="program.id">{{ program.title }} - {{ program.status }}</li>
                    <li v-if="props.recentPrograms.length === 0" class="text-slate-500">No programs yet.</li>
                </ul>
            </div>
        </template>

        <template v-else-if="props.section === 'youth_residents'">
            <div class="mb-3"><input :value="props.filters.search" type="text" placeholder="Search youth resident..." class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm" @input="searchYouthResidents($event.target.value)" /></div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('last_name')">Name {{ sortIndicator('last_name') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('birthdate')">Birthdate {{ sortIndicator('birthdate') }}</button></th><th class="px-4 py-3 text-left">Gender</th><th class="px-4 py-3 text-left">Contact</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="resident in props.youthResidents.data" :key="resident.id"><td class="px-4 py-3">{{ resident.last_name }}, {{ resident.first_name }}</td><td class="px-4 py-3">{{ resident.birthdate }}</td><td class="px-4 py-3">{{ resident.gender }}</td><td class="px-4 py-3">{{ resident.contact_number }}</td></tr>
                        <tr v-if="props.youthResidents.data.length === 0"><td colspan="4" class="px-4 py-6 text-center text-slate-500">No youth residents found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><Link v-for="link in props.youthResidents.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-1 text-sm" :class="[link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-50' : '']" v-html="link.label" /></div>
        </template>

        <template v-else-if="props.section === 'youth_programs'">
            <div v-if="canManageYouth" class="mb-4 rounded-lg border border-slate-200 p-4">
                <h3 class="mb-2 font-semibold">Create Youth Program</h3>
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
                <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white" @click="submitCreate">Save Program</button>
            </div>

            <div class="mb-3 flex flex-wrap gap-2">
                <input :value="props.filters.search" type="text" placeholder="Search program..." class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm" @input="searchYouthPrograms($event.target.value)" />
                <select :value="props.filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="changeProgramStatusFilter($event.target.value)">
                    <option value="">All status</option><option value="planned">planned</option><option value="ongoing">ongoing</option><option value="completed">completed</option><option value="cancelled">cancelled</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('title')">Title {{ sortIndicator('title') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('status')">Status {{ sortIndicator('status') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('start_date')">Start {{ sortIndicator('start_date') }}</button></th><th class="px-4 py-3 text-left"><button type="button" @click="sortBy('participants')">Participants {{ sortIndicator('participants') }}</button></th><th v-if="canManageYouth" class="px-4 py-3 text-left">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="program in props.youthPrograms.data" :key="program.id">
                            <td class="px-4 py-3">{{ program.title }}</td><td class="px-4 py-3">{{ program.status }}</td><td class="px-4 py-3">{{ program.start_date ?? '-' }}</td><td class="px-4 py-3">{{ program.participants }}</td>
                            <td v-if="canManageYouth" class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs" @click="openEditModal(program)">Edit</button>
                                    <button type="button" class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700" @click="openDeleteModal(program)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.youthPrograms.data.length === 0"><td :colspan="canManageYouth ? 5 : 4" class="px-4 py-6 text-center text-slate-500">No youth programs found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><Link v-for="link in props.youthPrograms.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-1 text-sm" :class="[link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-50' : '']" v-html="link.label" /></div>
        </template>

        <template v-else-if="props.section === 'youth_reports'">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Total Programs</p><p class="mt-2 text-2xl font-bold">{{ props.reportSummary.total_programs ?? 0 }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Total Budget</p><p class="mt-2 text-2xl font-bold">{{ props.reportSummary.total_budget ?? 0 }}</p></div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><p class="text-xs uppercase text-slate-500">Participants</p><p class="mt-2 text-2xl font-bold">{{ props.reportSummary.total_participants ?? 0 }}</p></div>
            </div>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Committee</th><th class="px-4 py-3 text-left">Programs</th><th class="px-4 py-3 text-left">Participants</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in props.reportByCommittee" :key="row.committee"><td class="px-4 py-3">{{ row.committee }}</td><td class="px-4 py-3">{{ row.programs_count }}</td><td class="px-4 py-3">{{ row.participants_count }}</td></tr>
                        <tr v-if="props.reportByCommittee.length === 0"><td colspan="3" class="px-4 py-6 text-center text-slate-500">No report data.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>

        <ModalDialog :show="showEditModal" title="Edit Youth Program" max-width-class="max-w-3xl" @close="closeEditModal">
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
