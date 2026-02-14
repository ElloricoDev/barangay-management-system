<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-3 md:grid-cols-2">
        <div>
            <input v-model="form.complainant_name" type="text" placeholder="Complainant name" class="ui-input" />
            <p v-if="form.errors.complainant_name" class="mt-1 text-xs text-rose-600">{{ form.errors.complainant_name }}</p>
        </div>
        <div>
            <input v-model="form.respondent_name" type="text" placeholder="Respondent name" class="ui-input" />
            <p v-if="form.errors.respondent_name" class="mt-1 text-xs text-rose-600">{{ form.errors.respondent_name }}</p>
        </div>
        <div>
            <input v-model="form.incident_date" type="date" class="ui-input" />
            <p v-if="form.errors.incident_date" class="mt-1 text-xs text-rose-600">{{ form.errors.incident_date }}</p>
        </div>
        <div>
            <select v-model="form.status" class="ui-input">
                <option value="ongoing">ongoing</option>
                <option value="settled">settled</option>
            </select>
            <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
        </div>
        <div class="md:col-span-2">
            <textarea v-model="form.description" rows="3" placeholder="Incident description" class="ui-input"></textarea>
            <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
        </div>
    </div>
</template>
