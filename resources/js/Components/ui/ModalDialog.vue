<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "",
    },
    maxWidthClass: {
        type: String,
        default: "max-w-3xl",
    },
});

const emit = defineEmits(["close"]);
</script>

<template>
    <div v-if="show" class="ui-modal-backdrop">
        <div class="w-full rounded-lg bg-white p-5 shadow-xl" :class="maxWidthClass">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800">{{ title }}</h3>
                <button type="button" class="ui-btn ui-btn--ghost px-2 py-1 text-xs" @click="emit('close')">Close</button>
            </div>
            <slot />
        </div>
    </div>
</template>
