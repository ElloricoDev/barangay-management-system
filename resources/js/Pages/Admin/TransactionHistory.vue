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

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: "", sort: "paid_at", direction: "desc" }) },
});

const { search, sortBy, sortIndicator } = useListQuery({
    path: "/admin/transaction-history",
    filters: computed(() => props.filters),
    defaultSort: "paid_at",
});

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
const formatDate = (value) => {
    if (!value) return "-";
    return new Date(value).toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
};
</script>

<template>
    <AdminLayout title="Transaction History" :user-name="userName">
        <template #header>
            <PageHeader title="Transaction History" subtitle="Read-only transaction ledger view." icon="transactions">
                <template #actions>
                    <input v-model="search" type="text" placeholder="Search history..." class="ui-input max-w-xs" />
                </template>
            </PageHeader>
        </template>

        <FlashMessages :flash="page.props.flash" />

        <PaymentTable :payments="props.payments.data" :sort-indicator="sortIndicator" :format-money="formatMoney" :format-date="formatDate" @sort="sortBy" />
        <PaginationLinks :links="props.payments.links" />
    </AdminLayout>
</template>
