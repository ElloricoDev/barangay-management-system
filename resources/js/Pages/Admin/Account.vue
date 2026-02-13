<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    account: {
        type: Object,
        required: true,
    },
});

const profileForm = useForm({
    name: props.account.name ?? "",
    email: props.account.email ?? "",
});

const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const deleteForm = useForm({
    password: "",
});
const showDeleteModal = ref(false);

const submitProfile = () => {
    profileForm.put("/admin/account/profile", {
        preserveScroll: true,
    });
};

const submitPassword = () => {
    passwordForm.put("/admin/account/password", {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const submitDelete = () => {
    deleteForm.delete("/admin/account", {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout title="My Account" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">My Account</h2>
                <p class="text-sm text-slate-500">Update your profile, change password, or delete your account.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ page.props.flash.error }}
        </div>

        <div class="space-y-5">
            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Edit Profile</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <input v-model="profileForm.name" type="text" placeholder="Name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="profileForm.errors.name" class="mt-1 text-xs text-rose-600">{{ profileForm.errors.name }}</p>
                    </div>
                    <div>
                        <input v-model="profileForm.email" type="email" placeholder="Email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="profileForm.errors.email" class="mt-1 text-xs text-rose-600">{{ profileForm.errors.email }}</p>
                    </div>
                </div>
                <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitProfile">
                    Save Profile
                </button>
            </section>

            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 font-semibold text-slate-800">Change Password</h3>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <input v-model="passwordForm.current_password" type="password" placeholder="Current password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-600">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <input v-model="passwordForm.password" type="password" placeholder="New password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <input v-model="passwordForm.password_confirmation" type="password" placeholder="Confirm new password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    </div>
                </div>
                <button type="button" class="mt-3 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="submitPassword">
                    Change Password
                </button>
            </section>

            <section class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                <h3 class="mb-2 font-semibold text-rose-700">Delete Account</h3>
                <p class="text-sm text-rose-700">Enter your current password to permanently delete your account.</p>
                <div class="mt-3 flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="w-full md:max-w-sm">
                        <input v-model="deleteForm.password" type="password" placeholder="Current password" class="w-full rounded-md border border-rose-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none" />
                        <p v-if="deleteForm.errors.password" class="mt-1 text-xs text-rose-600">{{ deleteForm.errors.password }}</p>
                    </div>
                    <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="showDeleteModal = true">
                        Delete My Account
                    </button>
                </div>
            </section>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-800">Confirm Account Deletion</h3>
                <p class="mt-2 text-sm text-slate-600">
                    This will permanently delete your account. Continue?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                        @click="submitDelete"
                    >
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
