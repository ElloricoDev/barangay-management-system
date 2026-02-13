<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? "Admin");

const props = defineProps({
    title: {
        type: String,
        default: "Module",
    },
    description: {
        type: String,
        default: "This module is available for your role.",
    },
});
</script>

<template>
    <AdminLayout :title="props.title" :user-name="userName">
        <template #header>
            <div class="mb-4 border-b pb-4">
                <h2 class="text-xl font-semibold text-slate-800">{{ props.title }}</h2>
                <p class="text-sm text-slate-500">{{ props.description }}</p>
            </div>
        </template>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            Module scaffold is enabled. You can now add the full business UI and backend actions here.
        </div>
    </AdminLayout>
</template>
