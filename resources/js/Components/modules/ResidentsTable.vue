<script setup>
defineProps({
    residents: {
        type: Array,
        default: () => [],
    },
    canEdit: {
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
    <div class="ui-table-wrap" data-persist-scroll data-scroll-key="residents-table">
        <table class="ui-table">
            <thead>
                <tr>
                    <th>
                        <button type="button" @click="emit('sort', 'last_name')">
                            Name {{ sortIndicator("last_name") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'birthdate')">
                            Birthdate {{ sortIndicator("birthdate") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'gender')">
                            Gender {{ sortIndicator("gender") }}
                        </button>
                    </th>
                    <th>
                        <button type="button" @click="emit('sort', 'contact_number')">
                            Contact {{ sortIndicator("contact_number") }}
                        </button>
                    </th>
                    <th v-if="canEdit">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="resident in residents" :key="resident.id">
                    <td>{{ resident.last_name }}, {{ resident.first_name }} {{ resident.middle_name ?? "" }}</td>
                    <td>{{ resident.birthdate }}</td>
                    <td>{{ resident.gender }}</td>
                    <td>{{ resident.contact_number ?? "-" }}</td>
                    <td v-if="canEdit">
                        <slot name="actions" :resident="resident" />
                    </td>
                </tr>
                <tr v-if="residents.length === 0">
                    <td :colspan="canEdit ? 5 : 4" class="px-4 py-6 text-center text-slate-500">No residents found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
