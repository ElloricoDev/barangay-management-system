<script setup>
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import BlotterTable from "../../Components/modules/BlotterTable.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canApprove = computed(() => permissions.value.includes("blotter.approve"));

const props = defineProps({
    blotters: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: "", sort: "id", direction: "desc" }),
    },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/blotter",
    filters: computed(() => props.filters),
});

const showActionModal = ref(false);
const selectedBlotter = ref(null);
const pendingAction = ref("");

const openApproveModal = (blotter) => {
    selectedBlotter.value = blotter;
    pendingAction.value = "approve";
    showActionModal.value = true;
};

const openRejectModal = (blotter) => {
    selectedBlotter.value = blotter;
    pendingAction.value = "reject";
    showActionModal.value = true;
};

const closeActionModal = () => {
    showActionModal.value = false;
    selectedBlotter.value = null;
    pendingAction.value = "";
};

const confirmAction = () => {
    if (!selectedBlotter.value || !pendingAction.value) return;

    router.patch(
        `/admin/blotter/${selectedBlotter.value.id}/${pendingAction.value}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => closeActionModal(),
        }
    );
};

const actionMessage = computed(() => {
    if (pendingAction.value === "approve") return "Approve this blotter case and mark it as settled?";
    return "Reject this review and keep the blotter case as ongoing?";
});
</script>

<template>
    <AdminLayout title="Blotter Cases" :user-name="userName">
        <template #header>
            <PageHeader title="Blotter Module" subtitle="Record incidents and monitor case status." icon="blotter">
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

        <BlotterTable
            :blotters="props.blotters.data"
            :sort-indicator="sortIndicator"
            :can-approve="canApprove"
            @sort="sortBy"
        >
            <template #actions="{ blotter }">
                <div v-if="canApprove" class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
                        :disabled="blotter.status === 'settled'"
                        @click="openApproveModal(blotter)"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                        :disabled="blotter.status === 'ongoing'"
                        @click="openRejectModal(blotter)"
                    >
                        Reject
                    </button>
                </div>
            </template>
        </BlotterTable>

        <PaginationLinks :links="props.blotters.links" />

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
