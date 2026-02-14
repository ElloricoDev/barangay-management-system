<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    title: {
        type: String,
        required: true,
    },
    href: {
        type: String,
        required: true,
    },
    linkLabel: {
        type: String,
        default: "View all",
    },
    emptyText: {
        type: String,
        default: "No records yet.",
    },
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="rounded-lg border border-slate-200 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">{{ title }}</h3>
            <Link :href="href" class="text-sm text-slate-600 hover:underline">{{ linkLabel }}</Link>
        </div>
        <ul class="space-y-2 text-sm text-slate-700">
            <li v-for="item in items" :key="item.id">
                <slot name="item" :item="item" />
            </li>
            <li v-if="items.length === 0" class="text-slate-500">{{ emptyText }}</li>
        </ul>
    </div>
</template>
