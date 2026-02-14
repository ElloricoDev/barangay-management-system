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

const revenueSources = [
    { value: "ira", label: "National Tax Allotment (IRA/NTA)" },
    { value: "local_taxes", label: "Local Taxes and Permits" },
    { value: "fines_fees", label: "Fines and Service Fees" },
    { value: "donations_grants", label: "Donations and Grants" },
    { value: "other_income", label: "Other Income" },
];

const expenseTypes = [
    { value: "administrative", label: "Administrative Expenses" },
    { value: "operations", label: "Operations and Utilities" },
    { value: "social_services", label: "Social Services" },
    { value: "infrastructure", label: "Infrastructure Projects" },
    { value: "contingency", label: "Contingency and Emergency" },
    { value: "other_expense", label: "Other Expense" },
];
</script>

<template>
    <div class="grid gap-3 md:grid-cols-4">
        <div>
            <select v-model="form.transaction_type" class="ui-input">
                <option value="revenue">Revenue</option>
                <option value="expense">Expenditure</option>
            </select>
            <p v-if="form.errors.transaction_type" class="mt-1 text-xs text-rose-600">{{ form.errors.transaction_type }}</p>
        </div>
        <div>
            <select v-model="form.workflow_status" class="ui-input">
                <option value="requested">Requested</option>
                <option value="approved">Approved</option>
                <option value="paid">Paid</option>
                <option value="rejected">Rejected</option>
            </select>
            <p v-if="form.errors.workflow_status" class="mt-1 text-xs text-rose-600">{{ form.errors.workflow_status }}</p>
        </div>
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
            <input v-model="form.or_number" type="text" placeholder="Reference / OR Number" class="ui-input" />
            <p v-if="form.errors.or_number" class="mt-1 text-xs text-rose-600">{{ form.errors.or_number }}</p>
        </div>
        <div v-if="form.transaction_type === 'revenue'">
            <select v-model="form.revenue_source" class="ui-input">
                <option value="">Revenue Source</option>
                <option v-for="source in revenueSources" :key="source.value" :value="source.value">{{ source.label }}</option>
            </select>
            <p v-if="form.errors.revenue_source" class="mt-1 text-xs text-rose-600">{{ form.errors.revenue_source }}</p>
        </div>
        <div v-else>
            <select v-model="form.expense_type" class="ui-input">
                <option value="">Expenditure Type</option>
                <option v-for="type in expenseTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
            <p v-if="form.errors.expense_type" class="mt-1 text-xs text-rose-600">{{ form.errors.expense_type }}</p>
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
            <input v-model="form.request_reference" type="text" placeholder="Request / Resolution Ref. (optional)" class="ui-input" />
            <p v-if="form.errors.request_reference" class="mt-1 text-xs text-rose-600">{{ form.errors.request_reference }}</p>
        </div>
        <div>
            <input v-model="form.voucher_number" type="text" placeholder="Voucher No. (optional)" class="ui-input" />
            <p v-if="form.errors.voucher_number" class="mt-1 text-xs text-rose-600">{{ form.errors.voucher_number }}</p>
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
        <div>
            <input v-model="form.approved_at" type="datetime-local" class="ui-input" />
            <p v-if="form.errors.approved_at" class="mt-1 text-xs text-rose-600">{{ form.errors.approved_at }}</p>
        </div>
        <div class="md:col-span-2">
            <input v-model="form.notes" type="text" placeholder="Notes (optional)" class="ui-input" />
            <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
        </div>
    </div>
</template>
