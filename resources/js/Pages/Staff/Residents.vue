<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import ResidentsTable from "../../Components/modules/ResidentsTable.vue";
import ResidentsFormFields from "../../Components/modules/ResidentsFormFields.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");

const props = defineProps({
    residents: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: "", sort: "id", direction: "desc" }),
    },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/staff/residents",
    filters: computed(() => props.filters),
});

const createForm = useForm({
    first_name: "",
    last_name: "",
    middle_name: "",
    birthdate: "",
    gender: "Male",
    contact_number: "",
});

const submitCreate = () => {
    createForm.post("/staff/residents", {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.gender = "Male";
        },
    });
};

const showEditModal = ref(false);
const editTarget = ref(null);
const editForm = useForm({
    first_name: "",
    last_name: "",
    middle_name: "",
    birthdate: "",
    gender: "Male",
    contact_number: "",
});

const openEdit = (resident) => {
    editTarget.value = resident;
    editForm.first_name = resident.first_name ?? "";
    editForm.last_name = resident.last_name ?? "";
    editForm.middle_name = resident.middle_name ?? "";
    editForm.birthdate = resident.birthdate ?? "";
    editForm.gender = resident.gender ?? "Male";
    editForm.contact_number = resident.contact_number ?? "";
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
    editForm.put(`/staff/residents/${editTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};
</script>

<template>
    <StaffLayout title="Residents" :user-name="userName">
        <template #header>
            <PageHeader title="Residents Module" subtitle="View and update barangay resident records." icon="residents">
                <template #actions>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search resident..."
                    class="ui-input max-w-xs"
                />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Resident</h3>
            <ResidentsFormFields :form="createForm" />
            <button type="button" class="ui-btn ui-btn--primary mt-3 px-4 py-2 font-medium" @click="submitCreate">
                Create Resident
            </button>
        </div>

        <ResidentsTable :residents="props.residents.data" :sort-indicator="sortIndicator" :can-edit="true" @sort="sortBy">
            <template #actions="{ resident }">
                <button type="button" class="ui-btn ui-btn--ghost px-2 py-1 text-xs" @click="openEdit(resident)">
                    Edit
                </button>
            </template>
        </ResidentsTable>

        <PaginationLinks :links="props.residents.links" />

        <div v-if="showEditModal" class="ui-modal-backdrop">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Resident</h3>
                <ResidentsFormFields :form="editForm" />
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
