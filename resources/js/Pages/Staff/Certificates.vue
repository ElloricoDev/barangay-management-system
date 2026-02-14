<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import CertificateTable from "../../Components/modules/CertificateTable.vue";
import CertificateFormFields from "../../Components/modules/CertificateFormFields.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Staff");
const permissions = computed(() => page.props.auth?.permissions ?? []);

const props = defineProps({
    certificates: {
        type: Object,
        required: true,
    },
    residents: {
        type: Array,
        default: () => [],
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
    () => permissions.value.includes("certificates.approve") || props.delegation?.staff_can_approve
);

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/staff/certificates",
    filters: computed(() => props.filters),
});

const createForm = useForm({
    resident_id: "",
    type: "clearance",
    purpose: "",
});

const submitCreate = () => {
    createForm.post("/staff/certificates", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("purpose"),
    });
};

const showEditModal = ref(false);
const editTarget = ref(null);
const editForm = useForm({
    resident_id: "",
    type: "clearance",
    purpose: "",
});

const openEdit = (certificate) => {
    editTarget.value = certificate;
    editForm.resident_id = certificate.resident_id;
    editForm.type = certificate.type;
    editForm.purpose = certificate.purpose;
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
    editForm.put(`/staff/certificates/${editTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const submitForApproval = (certificate) => {
    router.patch(`/staff/certificates/${certificate.id}/submit`, {}, { preserveScroll: true });
};

const releaseCertificate = (certificate) => {
    router.patch(`/staff/certificates/${certificate.id}/release`, {}, { preserveScroll: true });
};

const approveCertificate = (certificate) => {
    router.patch(`/staff/certificates/${certificate.id}/approve`, {}, { preserveScroll: true });
};

const rejectCertificate = (certificate) => {
    router.patch(`/staff/certificates/${certificate.id}/reject`, {}, { preserveScroll: true });
};

const canEdit = (status) => !["approved", "rejected", "released"].includes(status);
</script>

<template>
    <StaffLayout title="Certificates" :user-name="userName">
        <template #header>
            <PageHeader title="Certificates Module" subtitle="Create, update, submit, and release certificate requests." icon="certificates">
                <template #actions>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search certificate..."
                    class="ui-input max-w-xs"
                />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Certificate Request</h3>
            <CertificateFormFields :form="createForm" :residents="props.residents" :include-placeholder="true" />
            <button type="button" class="ui-btn ui-btn--primary mt-3 px-4 py-2 font-medium" @click="submitCreate">
                Create Request
            </button>
        </div>

        <CertificateTable
            :certificates="props.certificates.data"
            :sort-indicator="sortIndicator"
            :show-actions="true"
            @sort="sortBy"
        >
            <template #actions="{ certificate }">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100 disabled:opacity-50"
                        :disabled="!canEdit(certificate.status)"
                        @click="openEdit(certificate)"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'submitted'"
                        @click="submitForApproval(certificate)"
                    >
                        Submit
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'approved'"
                        @click="releaseCertificate(certificate)"
                    >
                        Release
                    </button>
                    <button
                        v-if="canApprove"
                        type="button"
                        class="rounded-md border border-indigo-300 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'ready_for_approval'"
                        @click="approveCertificate(certificate)"
                    >
                        Approve
                    </button>
                    <button
                        v-if="canApprove"
                        type="button"
                        class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'ready_for_approval'"
                        @click="rejectCertificate(certificate)"
                    >
                        Reject
                    </button>
                </div>
            </template>
        </CertificateTable>

        <PaginationLinks :links="props.certificates.links" />

        <div v-if="showEditModal" class="ui-modal-backdrop">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Certificate Request</h3>
                <CertificateFormFields :form="editForm" :residents="props.residents" />
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
