<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import PaymentTable from "../../Components/modules/PaymentTable.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canGenerateReceipt = computed(() => permissions.value.includes("finance.receipts"));

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: "", sort: "or_number", direction: "desc" }) },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/official-receipts",
    filters: computed(() => props.filters),
    defaultSort: "or_number",
});

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
};
</script>

<template>
    <AdminLayout title="Official Receipts" :user-name="userName">
        <template #header>
            <PageHeader title="Official Receipts" subtitle="Receipt registry with print-ready records." icon="receipts">
                <template #actions>
                    <input v-model="search" type="text" placeholder="Search OR / resident..." class="ui-input max-w-xs" />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <PaymentTable :payments="props.payments.data" :sort-indicator="sortIndicator" :format-money="formatMoney" :format-date="formatDate" :show-actions="canGenerateReceipt" @sort="sortBy">
            <template #actions="{ payment }">
                <div class="flex gap-2">
                    <a v-if="canGenerateReceipt" :href="`/admin/payments/${payment.id}/receipt`" target="_blank" class="rounded-md border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Print Receipt</a>
                </div>
            </template>
        </PaymentTable>

        <PaginationLinks :links="props.payments.links" />
    </AdminLayout>
</template>
