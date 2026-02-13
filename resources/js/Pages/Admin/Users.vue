<script setup>
import { computed, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");
const currentUserId = computed(() => page.props.auth?.user?.id);
const roleOptions = [
    { value: "super_admin", label: "Super Administrator (Punong Barangay)" },
    { value: "records_administrator", label: "Records Administrator (Barangay Secretary)" },
    { value: "finance_officer", label: "Finance Officer (Barangay Treasurer)" },
    { value: "committee_access_user", label: "Committee Access User (Barangay Kagawad)" },
    { value: "youth_administrator", label: "Youth Administrator (SK Chairperson)" },
    { value: "staff_user", label: "Staff User (Administrative Staff)" },
    { value: "data_manager", label: "Data Manager (Records Officer)" },
    { value: "encoder", label: "Encoder (Data Entry Personnel)" },
    { value: "technical_administrator", label: "Technical Administrator (IT/System Admin)" },
];

const roleLabelMap = roleOptions.reduce((acc, role) => {
    acc[role.value] = role.label;
    return acc;
}, {});

const roleLabel = (role) => roleLabelMap[role] ?? role;

const props = defineProps({
    users: {
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
            "/admin/users",
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
        "/admin/users",
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
    name: "",
    email: "",
    role: "staff_user",
    password: "",
});

const submitCreate = () => {
    createForm.post("/admin/users", {
        preserveScroll: true,
        onSuccess: () => createForm.reset("name", "email", "password"),
    });
};

const selectedUser = ref(null);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showResetModal = ref(false);

const editForm = useForm({
    name: "",
    email: "",
    role: "staff_user",
    password: "",
});

const openEditModal = (user) => {
    selectedUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.password = "";
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedUser.value = null;
    editForm.reset("name", "email", "role", "password");
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!selectedUser.value) return;

    editForm.put(`/admin/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

const openDeleteModal = (user) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedUser.value = null;
};

const confirmDelete = () => {
    if (!selectedUser.value) return;

    router.delete(`/admin/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

const openResetModal = (user) => {
    selectedUser.value = user;
    showResetModal.value = true;
};

const closeResetModal = () => {
    showResetModal.value = false;
    selectedUser.value = null;
};

const confirmResetPassword = () => {
    if (!selectedUser.value) return;

    router.patch(
        `/admin/users/${selectedUser.value.id}/reset-password`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => closeResetModal(),
        }
    );
};
</script>

<template>
    <AdminLayout title="Users" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">Users Module</h2>
                <p class="text-sm text-slate-500">Manage system user accounts and roles.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-5 rounded-lg border border-slate-200 p-4">
            <h3 class="mb-3 font-semibold text-slate-800">Create User</h3>
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <input v-model="createForm.name" type="text" placeholder="Name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-600">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <input v-model="createForm.email" type="email" placeholder="Email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.email" class="mt-1 text-xs text-rose-600">{{ createForm.errors.email }}</p>
                </div>
                <div>
                    <select v-model="createForm.role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.role" class="mt-1 text-xs text-rose-600">{{ createForm.errors.role }}</p>
                </div>
                <div>
                    <input v-model="createForm.password" type="password" placeholder="Password (min 8)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    <p v-if="createForm.errors.password" class="mt-1 text-xs text-rose-600">{{ createForm.errors.password }}</p>
                </div>
            </div>
            <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitCreate">
                Create User
            </button>
        </div>

        <div class="mb-4 flex items-end justify-between gap-3">
            <h3 class="font-semibold text-slate-800">User List</h3>
            <input
                v-model="search"
                type="text"
                placeholder="Search user..."
                class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('name')">Name {{ sortIndicator("name") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('email')">Email {{ sortIndicator("email") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('role')">Role {{ sortIndicator("role") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <button type="button" @click="sortBy('created_at')">Created {{ sortIndicator("created_at") }}</button>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="user in props.users.data" :key="user.id">
                        <td class="px-4 py-3 text-slate-700">{{ user.name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ user.email }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ roleLabel(user.role) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ user.created_at }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="openEditModal(user)">
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 disabled:opacity-50"
                                    :disabled="user.id === currentUserId"
                                    @click="openResetModal(user)"
                                >
                                    Reset Password
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                                    :disabled="user.id === currentUserId"
                                    @click="openDeleteModal(user)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.users.data.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No users found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in props.users.links"
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

        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-3 text-lg font-semibold text-slate-800">Edit User</h3>
                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <input v-model="editForm.name" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-rose-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.email" type="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.email" class="mt-1 text-xs text-rose-600">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <select v-model="editForm.role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                                {{ role.label }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.role" class="mt-1 text-xs text-rose-600">{{ editForm.errors.role }}</p>
                    </div>
                    <div>
                        <input v-model="editForm.password" type="password" placeholder="New password (optional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="editForm.errors.password" class="mt-1 text-xs text-rose-600">{{ editForm.errors.password }}</p>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeEditModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitEdit">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Confirm Delete</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Delete user <span class="font-medium">{{ selectedUser?.name }}</span>?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeDeleteModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="confirmDelete">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showResetModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Reset Password</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Reset password for <span class="font-medium">{{ selectedUser?.name }}</span> to <span class="font-medium">password123</span>?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeResetModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700" @click="confirmResetPassword">
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
