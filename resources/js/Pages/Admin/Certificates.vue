<script setup>
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import CertificateTable from "../../Components/modules/CertificateTable.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canApprove = computed(() => permissions.value.includes("certificates.approve"));

const props = defineProps({
    certificates: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: "", sort: "id", direction: "desc" }),
    },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/certificates",
    filters: computed(() => props.filters),
});

const showActionModal = ref(false);
const selectedCertificate = ref(null);
const pendingAction = ref("");

const openApproveModal = (certificate) => {
    selectedCertificate.value = certificate;
    pendingAction.value = "approve";
    showActionModal.value = true;
};

const openRejectModal = (certificate) => {
    selectedCertificate.value = certificate;
    pendingAction.value = "reject";
    showActionModal.value = true;
};

const closeActionModal = () => {
    showActionModal.value = false;
    selectedCertificate.value = null;
    pendingAction.value = "";
};

const confirmAction = () => {
    if (!selectedCertificate.value || !pendingAction.value) return;

    router.patch(
        `/admin/certificates/${selectedCertificate.value.id}/${pendingAction.value}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => closeActionModal(),
        }
    );
};

const actionMessage = computed(() => {
    if (pendingAction.value === "approve") return "Approve this certificate?";
    return "Reject this certificate?";
});
</script>

<template>
    <AdminLayout title="Certificates" :user-name="userName">
        <template #header>
            <PageHeader title="Certificates Module" subtitle="Issue, track, and approve certificates." icon="certificates">
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

        <CertificateTable
            :certificates="props.certificates.data"
            :sort-indicator="sortIndicator"
            :show-actions="canApprove"
            @sort="sortBy"
        >
            <template #actions="{ certificate }">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'ready_for_approval'"
                        @click="openApproveModal(certificate)"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                        :disabled="certificate.status !== 'ready_for_approval'"
                        @click="openRejectModal(certificate)"
                    >
                        Reject
                    </button>
                </div>
            </template>
        </CertificateTable>

        <PaginationLinks :links="props.certificates.links" />

        <ConfirmActionModal
            :show="showActionModal"
            title="Confirm Action"
            :message="actionMessage"
            confirm-label="Confirm"
            @cancel="closeActionModal"
            @confirm="confirmAction"
        />
    </AdminLayout>
</template>
