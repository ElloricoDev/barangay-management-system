<script setup>
defineProps({
    flash: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <div v-if="flash?.success" class="ui-alert-success">
        {{ flash.success }}
    </div>
    <div v-if="flash?.error" class="ui-alert-error">
        {{ flash.error }}
    </div>
</template>
