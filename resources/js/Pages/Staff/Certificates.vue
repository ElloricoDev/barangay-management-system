<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";

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

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/staff/certificates",
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
        "/staff/certificates",
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

const residentName = (residentId) => {
    const resident = props.residents.find((item) => item.id === residentId);
    if (!resident) return "-";
    return `${resident.last_name}, ${resident.first_name}`;
};

const canEdit = (status) => !["approved", "rejected", "released"].includes(status);
</script>

<template>
    <StaffLayout title="Certificates" :user-name="userName">
        <template #header>
            <div class="mb-4 flex items-end justify-between gap-3 border-b pb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Certificates Module</h2>
                    <p class="text-sm text-slate-500">Create, update, submit, and release certificate requests.</p>
                </div>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search certificate..."
                    class="w-full max-w-xs rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none"
                />
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-5 rounded-lg border border-emerald-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create Certificate Request</h3>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <select v-model="createForm.resident_id" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                        <option disabled value="">Select resident</option>
                        <option v-for="resident in props.residents" :key="resident.id" :value="resident.id">
                            {{ resident.last_name }}, {{ resident.first_name }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ createForm.errors.resident_id }}</p>
                </div>
                <div>
                    <select v-model="createForm.type" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                        <option value="clearance">clearance</option>
                        <option value="indigency">indigency</option>
                        <option value="residency">residency</option>
                    </select>
                    <p v-if="createForm.errors.type" class="mt-1 text-xs text-rose-600">{{ createForm.errors.type }}</p>
                </div>
                <div>
                    <input v-model="createForm.purpose" type="text" placeholder="Purpose" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.purpose" class="mt-1 text-xs text-rose-600">{{ createForm.errors.purpose }}</p>
                </div>
            </div>
            <button type="button" class="mt-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" @click="submitCreate">
                Create Request
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-emerald-200">
            <table class="min-w-full divide-y divide-emerald-200 text-sm">
                <thead class="bg-emerald-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('resident_name')">Resident {{ sortIndicator("resident_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('type')">Type {{ sortIndicator("type") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('purpose')">Purpose {{ sortIndicator("purpose") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('status')">Status {{ sortIndicator("status") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('issue_date')">Issue Date {{ sortIndicator("issue_date") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-emerald-100 bg-white">
                    <tr v-for="certificate in props.certificates.data" :key="certificate.id">
                        <td class="px-4 py-3 text-slate-700">
                            {{ certificate.resident?.last_name }}, {{ certificate.resident?.first_name }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ certificate.type }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ certificate.purpose }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ certificate.status }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ certificate.issue_date ?? "-" }}</td>
                        <td class="px-4 py-3">
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
                        </td>
                    </tr>
                    <tr v-if="props.certificates.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No certificates found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.certificates.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1 text-sm"
                :class="[
                    link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-emerald-300 bg-white text-emerald-700',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Certificate Request</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <select v-model="editForm.resident_id" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                            <option v-for="resident in props.residents" :key="resident.id" :value="resident.id">
                                {{ resident.last_name }}, {{ resident.first_name }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ editForm.errors.resident_id }}</p>
                    </div>
                    <div>
                        <select v-model="editForm.type" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                            <option value="clearance">clearance</option>
                            <option value="indigency">indigency</option>
                            <option value="residency">residency</option>
                        </select>
                        <p v-if="editForm.errors.type" class="mt-1 text-xs text-rose-600">{{ editForm.errors.type }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.purpose" type="text" placeholder="Purpose" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.purpose" class="mt-1 text-xs text-rose-600">{{ editForm.errors.purpose }}</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeEdit">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" @click="submitEdit">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </StaffLayout>
</template>

