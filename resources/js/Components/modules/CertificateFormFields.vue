<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    residents: {
        type: Array,
        default: () => [],
    },
    includePlaceholder: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="grid gap-3 md:grid-cols-3">
        <div>
            <select v-model="form.resident_id" class="ui-input">
                <option v-if="includePlaceholder" disabled value="">Select resident</option>
                <option v-for="resident in residents" :key="resident.id" :value="resident.id">
                    {{ resident.last_name }}, {{ resident.first_name }}
                </option>
            </select>
            <p v-if="form.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ form.errors.resident_id }}</p>
        </div>
        <div>
            <select v-model="form.type" class="ui-input">
                <option value="clearance">clearance</option>
                <option value="indigency">indigency</option>
                <option value="residency">residency</option>
            </select>
            <p v-if="form.errors.type" class="mt-1 text-xs text-rose-600">{{ form.errors.type }}</p>
        </div>
        <div>
            <input v-model="form.purpose" type="text" placeholder="Purpose" class="ui-input" />
            <p v-if="form.errors.purpose" class="mt-1 text-xs text-rose-600">{{ form.errors.purpose }}</p>
        </div>
    </div>
</template>
