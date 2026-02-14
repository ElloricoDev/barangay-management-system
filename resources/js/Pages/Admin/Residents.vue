<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { useListQuery } from "../../Composables/useListQuery";
import ResidentsTable from "../../Components/modules/ResidentsTable.vue";
import PaginationLinks from "../../Components/ui/PaginationLinks.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
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
    path: "/admin/residents",
    filters: computed(() => props.filters),
});
</script>

<template>
    <AdminLayout title="Residents" :user-name="userName">
        <template #header>
            <PageHeader title="Residents Module" subtitle="Manage resident records and profiles." icon="residents">
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

        <ResidentsTable :residents="props.residents.data" :sort-indicator="sortIndicator" @sort="sortBy" />
        <PaginationLinks :links="props.residents.links" />
    </AdminLayout>
</template>
