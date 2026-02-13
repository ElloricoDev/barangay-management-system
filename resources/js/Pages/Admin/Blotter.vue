<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

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

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/admin/blotter",
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
        "/admin/blotter",
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
    return (props.filters?.direction ?? "desc") === "asc" ? "^" : "v";
};

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
</script>

<template>
    <AdminLayout title="Blotter Cases" :user-name="userName">
        <template #header>
            <div class="mb-4 flex items-end justify-between gap-3 border-b pb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Blotter Module</h2>
                    <p class="text-sm text-slate-500">Record incidents and monitor case status.</p>
                </div>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search blotter..."
                    class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                />
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('complainant_name')">Complainant {{ sortIndicator("complainant_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('respondent_name')">Respondent {{ sortIndicator("respondent_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('incident_date')">Incident Date {{ sortIndicator("incident_date") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('status')">Status {{ sortIndicator("status") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Description</th>
                        <th v-if="canApprove" class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="blotter in props.blotters.data" :key="blotter.id">
                        <td class="px-4 py-3 text-slate-700">{{ blotter.complainant_name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ blotter.respondent_name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ blotter.incident_date }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ blotter.status }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ blotter.description }}</td>
                        <td v-if="canApprove" class="px-4 py-3">
                            <div class="flex gap-2">
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
                        </td>
                    </tr>
                    <tr v-if="props.blotters.data.length === 0">
                        <td :colspan="canApprove ? 6 : 5" class="px-4 py-6 text-center text-slate-500">No blotter cases found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.blotters.links"
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

        <div
            v-if="showActionModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Confirm Action</h3>
                <p class="mt-2 text-sm text-slate-600">
                    <span v-if="pendingAction === 'approve'">
                        Approve this blotter case and mark it as settled?
                    </span>
                    <span v-else>
                        Reject this review and keep the blotter case as ongoing?
                    </span>
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100"
                        @click="closeActionModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                        @click="confirmAction"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

