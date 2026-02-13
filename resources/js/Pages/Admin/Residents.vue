<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

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

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/admin/residents",
            {
                search: value,
                sort: props.filters?.sort ?? "id",
                direction: props.filters?.direction ?? "desc",
            },
            { preserveState: true, replace: true }
        );
    },
});

const sortBy = (column) => {
    const isCurrent = (props.filters?.sort ?? "id") === column;
    const nextDirection = isCurrent && (props.filters?.direction ?? "desc") === "asc" ? "desc" : "asc";

    router.get(
        "/admin/residents",
        {
            search: props.filters?.search ?? "",
            sort: column,
            direction: nextDirection,
        },
        { preserveState: true, replace: true }
    );
};

const sortIndicator = (column) => {
    if ((props.filters?.sort ?? "id") !== column) return "";
    return (props.filters?.direction ?? "desc") === "asc" ? "↑" : "↓";
};
</script>

<template>
    <AdminLayout title="Residents" :user-name="userName">
        <template #header>
            <div class="mb-4 flex items-end justify-between gap-3 border-b pb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Residents Module</h2>
                    <p class="text-sm text-slate-500">Manage resident records and profiles.</p>
                </div>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search resident..."
                    class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
            </div>
        </template>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('last_name')">Name {{ sortIndicator("last_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('birthdate')">Birthdate {{ sortIndicator("birthdate") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('gender')">Gender {{ sortIndicator("gender") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('contact_number')">Contact {{ sortIndicator("contact_number") }}</button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="resident in props.residents.data" :key="resident.id">
                        <td class="px-4 py-3 text-slate-700">
                            {{ resident.last_name }}, {{ resident.first_name }} {{ resident.middle_name ?? "" }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.birthdate }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.gender }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.contact_number ?? "-" }}</td>
                    </tr>
                    <tr v-if="props.residents.data.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No residents found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.residents.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1 text-sm"
                :class="[
                    link.active ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-700',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
