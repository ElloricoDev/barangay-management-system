<script setup>
defineProps({
    certificates: {
        type: Array,
        default: () => [],
    },
    showActions: {
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
                        <button type="button" @click="emit('sort', 'resident_name')">
                            Resident {{ sortIndicator("resident_name") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'type')">
                            Type {{ sortIndicator("type") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'purpose')">
                            Purpose {{ sortIndicator("purpose") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'status')">
                            Status {{ sortIndicator("status") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'issue_date')">
                            Issue Date {{ sortIndicator("issue_date") }}
                        </button>
                    </th>
                    <th v-if="showActions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="certificate in certificates" :key="certificate.id">
                    <td>{{ certificate.resident?.last_name }}, {{ certificate.resident?.first_name }}</td>
                    <td>{{ certificate.type }}</td>
                    <td>{{ certificate.purpose }}</td>
                    <td>{{ certificate.status }}</td>
                    <td>{{ certificate.issue_date ?? "-" }}</td>
                    <td v-if="showActions">
                        <slot name="actions" :certificate="certificate" />
                    </td>
                </tr>
                <tr v-if="certificates.length === 0">
                    <td :colspan="showActions ? 6 : 5" class="px-4 py-6 text-center text-slate-500">
                        No certificates found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
