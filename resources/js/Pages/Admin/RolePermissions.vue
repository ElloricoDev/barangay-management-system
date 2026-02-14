<script setup>
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import FlashMessages from "../../Components/ui/FlashMessages.vue";
import ConfirmActionModal from "../../Components/ui/ConfirmActionModal.vue";
import PageHeader from "../../Components/ui/PageHeader.vue";
import { permissionLabel } from "../../Utils/permissionLabels";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    allPermissions: {
        type: Array,
        default: () => [],
    },
    rolePermissions: {
        type: Object,
        default: () => ({}),
    },
    defaultPermissions: {
        type: Object,
        default: () => ({}),
    },
    selectedRole: {
        type: String,
        default: "",
    },
});

const selectedRole = ref(props.selectedRole || props.roles[0] || "");
const search = ref("");
const selectedPermissions = ref([]);
const showResetAllModal = ref(false);

watch(
    () => [selectedRole.value, props.rolePermissions],
    () => {
        selectedPermissions.value = [...(props.rolePermissions[selectedRole.value] ?? [])];
    },
    { immediate: true }
);

const normalizedRoleLabel = (role) =>
    role
        .split("_")
        .map((chunk) => chunk.charAt(0).toUpperCase() + chunk.slice(1))
        .join(" ");

const filteredPermissions = computed(() => {
    if (!search.value.trim()) return props.allPermissions;
    const query = search.value.toLowerCase();
    return props.allPermissions.filter((permission) =>
        permission.toLowerCase().includes(query) ||
        permissionLabel(permission).toLowerCase().includes(query)
    );
});

const changeRole = (role) => {
    selectedRole.value = role;
    router.get(
        "/admin/role-permissions",
        { role },
        { preserveState: true, replace: true, preserveScroll: true }
    );
};

const togglePermission = (permission) => {
    const index = selectedPermissions.value.indexOf(permission);
    if (index === -1) {
        selectedPermissions.value.push(permission);
    } else {
        selectedPermissions.value.splice(index, 1);
    }
};

const selectAllFiltered = () => {
    const merged = new Set([...selectedPermissions.value, ...filteredPermissions.value]);
    selectedPermissions.value = [...merged];
};

const clearAllFiltered = () => {
    const blocked = new Set(filteredPermissions.value);
    selectedPermissions.value = selectedPermissions.value.filter((item) => !blocked.has(item));
};

const savePermissions = () => {
    if (!selectedRole.value) return;
    router.put(
        `/admin/role-permissions/${selectedRole.value}`,
        { permissions: selectedPermissions.value },
        { preserveScroll: true }
    );
};

const resetToDefault = () => {
    if (!selectedRole.value) return;
    router.patch(
        `/admin/role-permissions/${selectedRole.value}/reset`,
        {},
        { preserveScroll: true }
    );
};

const resetAllRoles = () => {
    router.patch("/admin/role-permissions/reset-all", {}, { preserveScroll: true });
};

const openResetAllModal = () => {
    showResetAllModal.value = true;
};

const closeResetAllModal = () => {
    showResetAllModal.value = false;
};

const confirmResetAllRoles = () => {
    resetAllRoles();
    closeResetAllModal();
};
</script>

<template>
    <AdminLayout title="Role Permissions" :user-name="userName">
        <template #header>
            <PageHeader title="Role Permissions" subtitle="Manage role-to-permission assignments from the UI." icon="roles" />
        </template>

        <FlashMessages :flash="page.props.flash" />

        <div class="grid gap-4 lg:grid-cols-[260px_1fr]">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Roles</h3>
                <div class="space-y-1">
                    <button
                        v-for="role in props.roles"
                        :key="role"
                        type="button"
                        class="block w-full rounded-md px-3 py-2 text-left text-sm"
                        :class="selectedRole === role ? 'bg-slate-800 text-white' : 'hover:bg-slate-200 text-slate-700'"
                        @click="changeRole(role)"
                    >
                        {{ normalizedRoleLabel(role) }}
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-800">{{ normalizedRoleLabel(selectedRole) }}</h3>
                        <p class="text-xs text-slate-500">Check permissions then save.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50" @click="openResetAllModal">
                            Reset All Roles
                        </button>
                        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="selectAllFiltered">
                            Select Filtered
                        </button>
                        <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100" @click="clearAllFiltered">
                            Clear Filtered
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search permission..."
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                    />
                </div>

                <div class="max-h-[460px] overflow-y-auto rounded-md border border-slate-200 p-3">
                    <div class="grid gap-2 md:grid-cols-2">
                        <label
                            v-for="permission in filteredPermissions"
                            :key="permission"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 hover:bg-slate-50"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedPermissions.includes(permission)"
                                @change="togglePermission(permission)"
                            />
                            <span class="text-sm text-slate-700">{{ permissionLabel(permission) }}</span>
                        </label>
                    </div>
                    <p v-if="filteredPermissions.length === 0" class="text-sm text-slate-500">No permissions match the search.</p>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-md border border-amber-300 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50" @click="resetToDefault">
                        Reset To Default
                    </button>
                    <button type="button" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="savePermissions">
                        Save Permissions
                    </button>
                </div>
            </div>
        </div>

        <ConfirmActionModal
            :show="showResetAllModal"
            title="Reset All Role Permissions"
            message="This will reset every role to the default permission matrix. Continue?"
            confirm-label="Reset All"
            confirm-variant="danger"
            @cancel="closeResetAllModal"
            @confirm="confirmResetAllRoles"
        />
    </AdminLayout>
</template>
