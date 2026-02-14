<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import BlotterTable from "../../Components/modules/BlotterTable.vue";
import BlotterFormFields from "../../Components/modules/BlotterFormFields.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");
const permissions = computed(() => page.props.auth?.permissions ?? []);

const props = defineProps({
    blotters: {
        type: Object,
        required: true,
    },
    delegation: {
        type: Object,
        default: () => ({ staff_can_approve: false }),
    },
    filters: {
        type: Object,
        default: () => ({ search: "", sort: "id", direction: "desc" }),
    },
});

const canApprove = computed(
    () => permissions.value.includes("blotter.approve") || props.delegation?.staff_can_approve
);

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/staff/blotter",
    filters: computed(() => props.filters),
});

const createForm = useForm({
    complainant_name: "",
    respondent_name: "",
    incident_date: "",
    description: "",
    status: "ongoing",
});

const submitCreate = () => {
    createForm.post("/staff/blotter", {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.status = "ongoing";
        },
    });
};

const showEditModal = ref(false);
const editTarget = ref(null);
const editForm = useForm({
    complainant_name: "",
    respondent_name: "",
    incident_date: "",
    description: "",
    status: "ongoing",
});

const openEdit = (blotter) => {
    editTarget.value = blotter;
    editForm.complainant_name = blotter.complainant_name ?? "";
    editForm.respondent_name = blotter.respondent_name ?? "";
    editForm.incident_date = blotter.incident_date ?? "";
    editForm.description = blotter.description ?? "";
    editForm.status = blotter.status ?? "ongoing";
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEdit = () => {
    showEditModal.value = false;
    editTarget.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editTarget.value) return;
    editForm.put(`/staff/blotter/${editTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const approveBlotter = (blotter) => {
    router.patch(`/staff/blotter/${blotter.id}/approve`, {}, { preserveScroll: true });
};

const rejectBlotter = (blotter) => {
    router.patch(`/staff/blotter/${blotter.id}/reject`, {}, { preserveScroll: true });
};
</script>

<template>
    <StaffLayout title="Blotter Cases" :user-name="userName">
        <template #header>
            <PageHeader title="Blotter Module" subtitle="Handle incident records and case progress." icon="blotter">
                <template #actions>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search blotter..."
                    class="ui-input max-w-xs"
                />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Blotter Case</h3>
            <BlotterFormFields :form="createForm" />
            <button type="button" class="ui-btn ui-btn--primary mt-3 px-4 py-2 font-medium" @click="submitCreate">
                Create Case
            </button>
        </div>

        <BlotterTable
            :blotters="props.blotters.data"
            :sort-indicator="sortIndicator"
            :can-approve="canApprove"
            :has-actions="true"
            @sort="sortBy"
        >
            <template #actions="{ blotter }">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn ui-btn--ghost px-2 py-1 text-xs" @click="openEdit(blotter)">
                        Edit
                    </button>
                    <button
                        v-if="canApprove"
                        type="button"
                        class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        :disabled="blotter.status === 'settled'"
                        @click="approveBlotter(blotter)"
                    >
                        Approve
                    </button>
                    <button
                        v-if="canApprove"
                        type="button"
                        class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                        :disabled="blotter.status === 'ongoing'"
                        @click="rejectBlotter(blotter)"
                    >
                        Reject
                    </button>
                </div>
            </template>
        </BlotterTable>

        <PaginationLinks :links="props.blotters.links" />

        <div v-if="showEditModal" class="ui-modal-backdrop">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Blotter Case</h3>
                <BlotterFormFields :form="editForm" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="closeEdit">
                        Cancel
                    </button>
                    <button type="button" class="ui-btn ui-btn--primary px-4 py-2 font-medium" @click="submitEdit">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </StaffLayout>
</template>
