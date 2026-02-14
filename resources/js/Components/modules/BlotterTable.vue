<script setup>
import { CheckCircleIcon, ClockIcon, XCircleIcon } from "@heroicons/vue/24/solid";

defineProps({
    blotters: {
        type: Array,
        default: () => [],
    },
    canApprove: {
        type: Boolean,
        default: false,
    },
    hasActions: {
        type: Boolean,
        default: false,
    },
    sortIndicator: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(["sort"]);

const statusClass = (status) => {
    if (status === "settled") return "border-emerald-200 bg-emerald-50 text-emerald-700";
    if (status === "rejected") return "border-rose-200 bg-rose-50 text-rose-700";
    return "border-amber-200 bg-amber-50 text-amber-700";
};

const statusIcon = (status) => {
    if (status === "settled") return CheckCircleIcon;
    if (status === "rejected") return XCircleIcon;
    return ClockIcon;
};
</script>

<template>
    <div class="ui-table-wrap" data-persist-scroll data-scroll-key="blotter-table">
        <table class="ui-table">
            <thead>
                <tr>
                    <th>
                        <button type="button" @click="emit('sort', 'complainant_name')">
                            Complainant {{ sortIndicator("complainant_name") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'respondent_name')">
                            Respondent {{ sortIndicator("respondent_name") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'incident_date')">
                            Incident Date {{ sortIndicator("incident_date") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'status')">
                            Status {{ sortIndicator("status") }}
                        </button>
                    </th>
                    <th>Description</th>
                    <th v-if="hasActions || canApprove">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="blotter in blotters" :key="blotter.id">
                    <td>{{ blotter.complainant_name }}</td>
                    <td>{{ blotter.respondent_name }}</td>
                    <td>{{ blotter.incident_date }}</td>
                    <td>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(blotter.status)">
                            <component :is="statusIcon(blotter.status)" class="h-3.5 w-3.5" />
                            {{ blotter.status }}
                        </span>
                    </td>
                    <td>{{ blotter.description }}</td>
                    <td v-if="hasActions || canApprove">
                        <slot name="actions" :blotter="blotter" />
                    </td>
                </tr>
                <tr v-if="blotters.length === 0">
                    <td :colspan="hasActions || canApprove ? 6 : 5" class="px-4 py-6 text-center text-slate-500">
                        No blotter cases found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
