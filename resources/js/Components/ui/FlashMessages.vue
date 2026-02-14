<script setup>
import { CheckCircleIcon, ExclamationTriangleIcon } from "@heroicons/vue/24/solid";

defineProps({
    flash: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <div v-if="flash?.success" class="ui-alert-success flex items-start gap-2">
        <CheckCircleIcon class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ flash.success }}</span>
    </div>
    <div v-if="flash?.error" class="ui-alert-error flex items-start gap-2">
        <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ flash.error }}</span>
    </div>
</template>
