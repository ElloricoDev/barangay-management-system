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
});
</script>

<template>
    <div class="grid gap-3 md:grid-cols-4">
        <div>
            <select v-model="form.resident_id" class="ui-input">
                <option value="">Resident (optional)</option>
                <option v-for="resident in residents" :key="resident.id" :value="resident.id">
                    {{ resident.last_name }}, {{ resident.first_name }}
                </option>
            </select>
            <p v-if="form.errors.resident_id" class="mt-1 text-xs text-rose-600">{{ form.errors.resident_id }}</p>
        </div>
        <div>
            <input v-model="form.or_number" type="text" placeholder="OR Number" class="ui-input" />
            <p v-if="form.errors.or_number" class="mt-1 text-xs text-rose-600">{{ form.errors.or_number }}</p>
        </div>
        <div>
            <select v-model="form.service_type" class="ui-input">
                <option value="certificate">Certificate</option>
                <option value="clearance_fee">Clearance Fee</option>
                <option value="blotter_fee">Blotter Fee</option>
                <option value="other">Other Service</option>
            </select>
            <p v-if="form.errors.service_type" class="mt-1 text-xs text-rose-600">{{ form.errors.service_type }}</p>
        </div>
        <div>
            <input v-model="form.description" type="text" placeholder="Description" class="ui-input" />
            <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
        </div>
        <div>
            <input v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="Amount" class="ui-input" />
            <p v-if="form.errors.amount" class="mt-1 text-xs text-rose-600">{{ form.errors.amount }}</p>
        </div>
        <div>
            <input v-model="form.paid_at" type="datetime-local" class="ui-input" />
            <p v-if="form.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ form.errors.paid_at }}</p>
        </div>
        <div class="md:col-span-2">
            <input v-model="form.notes" type="text" placeholder="Notes (optional)" class="ui-input" />
            <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
        </div>
    </div>
</template>
