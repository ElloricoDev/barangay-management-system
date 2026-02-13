<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import StaffLayout from "../../Layouts/StaffLayout.vue";

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

const search = computed({
    get: () => props.filters?.search ?? "",
    set: (value) => {
        router.get(
            "/staff/residents",
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
        "/staff/residents",
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
            <div class="mb-4 flex items-end justify-between gap-3 border-b pb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Residents Module</h2>
                    <p class="text-sm text-slate-500">View and update barangay resident records.</p>
                </div>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search resident..."
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
            <h3 class="mb-3 font-semibold text-slate-800">Create Resident</h3>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <input v-model="createForm.first_name" type="text" placeholder="First name" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.first_name" class="mt-1 text-xs text-rose-600">{{ createForm.errors.first_name }}</p>
                </div>
                <div>
                    <input v-model="createForm.last_name" type="text" placeholder="Last name" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.last_name" class="mt-1 text-xs text-rose-600">{{ createForm.errors.last_name }}</p>
                </div>
                <div>
                    <input v-model="createForm.middle_name" type="text" placeholder="Middle name (optional)" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.middle_name" class="mt-1 text-xs text-rose-600">{{ createForm.errors.middle_name }}</p>
                </div>
                <div>
                    <input v-model="createForm.birthdate" type="date" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.birthdate" class="mt-1 text-xs text-rose-600">{{ createForm.errors.birthdate }}</p>
                </div>
                <div>
                    <select v-model="createForm.gender" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <p v-if="createForm.errors.gender" class="mt-1 text-xs text-rose-600">{{ createForm.errors.gender }}</p>
                </div>
                <div>
                    <input v-model="createForm.contact_number" type="text" placeholder="Contact number" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                    <p v-if="createForm.errors.contact_number" class="mt-1 text-xs text-rose-600">{{ createForm.errors.contact_number }}</p>
                </div>
            </div>
            <button type="button" class="mt-3 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" @click="submitCreate">
                Create Resident
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-emerald-200">
            <table class="min-w-full divide-y divide-emerald-200 text-sm">
                <thead class="bg-emerald-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('last_name')">Name {{ sortIndicator("last_name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('birthdate')">Birthdate {{ sortIndicator("birthdate") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('gender')">Gender {{ sortIndicator("gender") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">
                            <button type="button" @click="sortBy('contact_number')">Contact {{ sortIndicator("contact_number") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-emerald-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-emerald-100 bg-white">
                    <tr v-for="resident in props.residents.data" :key="resident.id">
                        <td class="px-4 py-3 text-slate-700">
                            {{ resident.last_name }}, {{ resident.first_name }} {{ resident.middle_name ?? "" }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.birthdate }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.gender }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ resident.contact_number ?? "-" }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="openEdit(resident)">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr v-if="props.residents.data.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No residents found.</td>
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
                    link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-emerald-300 bg-white text-emerald-700',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit Resident</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <input v-model="editForm.first_name" type="text" placeholder="First name" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.first_name" class="mt-1 text-xs text-rose-600">{{ editForm.errors.first_name }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.last_name" type="text" placeholder="Last name" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.last_name" class="mt-1 text-xs text-rose-600">{{ editForm.errors.last_name }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.middle_name" type="text" placeholder="Middle name" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.middle_name" class="mt-1 text-xs text-rose-600">{{ editForm.errors.middle_name }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.birthdate" type="date" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.birthdate" class="mt-1 text-xs text-rose-600">{{ editForm.errors.birthdate }}</p>
                    </div>
                    <div>
                        <select v-model="editForm.gender" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <p v-if="editForm.errors.gender" class="mt-1 text-xs text-rose-600">{{ editForm.errors.gender }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.contact_number" type="text" placeholder="Contact number" class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none" />
                        <p v-if="editForm.errors.contact_number" class="mt-1 text-xs text-rose-600">{{ editForm.errors.contact_number }}</p>
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

