<script setup>
import { computed } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    barangay_name: props.settings.barangay_name ?? "Barangay Management System",
    barangay_city: props.settings.barangay_city ?? "",
    barangay_province: props.settings.barangay_province ?? "",
    contact_number: props.settings.contact_number ?? "",
    contact_email: props.settings.contact_email ?? "",
    receipt_prefix: props.settings.receipt_prefix ?? "OR",
    timezone: props.settings.timezone ?? "Asia/Manila",
    maintenance_mode: !!props.settings.maintenance_mode,
    footer_note: props.settings.footer_note ?? "",
});

const save = () => {
    form.put("/admin/system-settings", {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout title="System Settings" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">System Settings</h2>
                <p class="text-sm text-slate-500">Manage global configuration and operational preferences.</p>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ page.props.flash.success }}</div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ page.props.flash.error }}</div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Barangay Name</label>
                <input v-model="form.barangay_name" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.barangay_name" class="mt-1 text-xs text-rose-600">{{ form.errors.barangay_name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">City / Municipality</label>
                <input v-model="form.barangay_city" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.barangay_city" class="mt-1 text-xs text-rose-600">{{ form.errors.barangay_city }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Province</label>
                <input v-model="form.barangay_province" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.barangay_province" class="mt-1 text-xs text-rose-600">{{ form.errors.barangay_province }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Contact Number</label>
                <input v-model="form.contact_number" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.contact_number" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_number }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Contact Email</label>
                <input v-model="form.contact_email" type="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.contact_email" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Receipt Prefix</label>
                <input v-model="form.receipt_prefix" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.receipt_prefix" class="mt-1 text-xs text-rose-600">{{ form.errors.receipt_prefix }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label>
                <input v-model="form.timezone" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                <p v-if="form.errors.timezone" class="mt-1 text-xs text-rose-600">{{ form.errors.timezone }}</p>
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input id="maintenance_mode" v-model="form.maintenance_mode" type="checkbox" class="h-4 w-4" />
                <label for="maintenance_mode" class="text-sm font-medium text-slate-700">Enable Maintenance Mode</label>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Footer Note</label>
                <textarea v-model="form.footer_note" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                <p v-if="form.errors.footer_note" class="mt-1 text-xs text-rose-600">{{ form.errors.footer_note }}</p>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="button" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="save">
                Save Settings
            </button>
        </div>
    </AdminLayout>
</template>
