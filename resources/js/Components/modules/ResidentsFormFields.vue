<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-3 md:grid-cols-3">
        <div>
            <input v-model="form.first_name" type="text" placeholder="First name" class="ui-input" />
            <p v-if="form.errors.first_name" class="mt-1 text-xs text-rose-600">{{ form.errors.first_name }}</p>
        </div>
        <div>
            <input v-model="form.last_name" type="text" placeholder="Last name" class="ui-input" />
            <p v-if="form.errors.last_name" class="mt-1 text-xs text-rose-600">{{ form.errors.last_name }}</p>
        </div>
        <div>
            <input v-model="form.middle_name" type="text" placeholder="Middle name (optional)" class="ui-input" />
            <p v-if="form.errors.middle_name" class="mt-1 text-xs text-rose-600">{{ form.errors.middle_name }}</p>
        </div>
        <div>
            <input v-model="form.birthdate" type="date" class="ui-input" />
            <p v-if="form.errors.birthdate" class="mt-1 text-xs text-rose-600">{{ form.errors.birthdate }}</p>
        </div>
        <div>
            <select v-model="form.gender" class="ui-input">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
            <p v-if="form.errors.gender" class="mt-1 text-xs text-rose-600">{{ form.errors.gender }}</p>
        </div>
        <div>
            <input v-model="form.contact_number" type="text" placeholder="Contact number" class="ui-input" />
            <p v-if="form.errors.contact_number" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_number }}</p>
        </div>
    </div>
</template>
