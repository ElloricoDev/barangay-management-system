<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
});
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ label }}</p>
        <p class="mt-2 text-2xl font-bold text-slate-800">{{ value }}</p>
    </div>
</template>
