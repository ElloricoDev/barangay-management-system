<script setup>
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
</script>

<template>
    <div class="ui-table-wrap">
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
                    <td>{{ blotter.status }}</td>
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
