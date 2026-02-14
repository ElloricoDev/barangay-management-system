<script setup>
import { CheckIcon, ExclamationTriangleIcon, XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "Confirm Action",
    },
    message: {
        type: String,
        default: "Are you sure?",
    },
    confirmLabel: {
        type: String,
        default: "Confirm",
    },
    cancelLabel: {
        type: String,
        default: "Cancel",
    },
    confirmVariant: {
        type: String,
        default: "primary",
    },
});

const emit = defineEmits(["cancel", "confirm"]);

const confirmButtonClass = () => {
    if (props.confirmVariant === "danger") return "ui-btn ui-btn--danger px-4 py-2 font-medium";
    return "ui-btn ui-btn--primary px-4 py-2 font-medium";
};

const iconClass = "h-4 w-4";
</script>

<template>
    <div v-if="show" class="ui-modal-backdrop">
        <div class="ui-modal ui-modal--compact" data-persist-scroll data-scroll-key="confirm-action-modal">
            <h3 class="text-lg font-semibold text-slate-800">{{ title }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="ui-btn ui-btn--ghost px-4 py-2" @click="emit('cancel')">
                    <XMarkIcon :class="iconClass" />
                    {{ cancelLabel }}
                </button>
                <button type="button" :class="confirmButtonClass()" @click="emit('confirm')">
                    <ExclamationTriangleIcon v-if="confirmVariant === 'danger'" :class="iconClass" />
                    <CheckIcon v-else :class="iconClass" />
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
