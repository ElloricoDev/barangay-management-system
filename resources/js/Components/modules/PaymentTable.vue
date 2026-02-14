<script setup>
const prettify = (value) =>
    String(value ?? "-")
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());

defineProps({
    payments: {
        type: Array,
        default: () => [],
    },
    sortIndicator: {
        type: Function,
        default: () => "",
    },
    sortable: {
        type: Boolean,
        default: true,
    },
    showActions: {
        type: Boolean,
        default: false,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
    formatDate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(["sort"]);
</script>

<template>
    <div class="ui-table-wrap" data-persist-scroll data-scroll-key="payment-table">
        <table class="ui-table">
            <thead>
                <tr>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'transaction_type')">Type {{ sortIndicator("transaction_type") }}</button>
                        <span v-else>Type</span>
                    </th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'workflow_status')">Status {{ sortIndicator("workflow_status") }}</button>
                        <span v-else>Status</span>
                    </th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'or_number')">OR No. {{ sortIndicator("or_number") }}</button>
                        <span v-else>OR No.</span>
                    </th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'resident_name')">Resident {{ sortIndicator("resident_name") }}</button>
                        <span v-else>Resident</span>
                    </th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'service_type')">Service {{ sortIndicator("service_type") }}</button>
                        <span v-else>Service</span>
                    </th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'amount')">Amount {{ sortIndicator("amount") }}</button>
                        <span v-else>Amount</span>
                    </th>
                    <th>
                        <button v-if="sortable" type="button" @click="emit('sort', 'paid_at')">Paid At {{ sortIndicator("paid_at") }}</button>
                        <span v-else>Paid At</span>
                    </th>
                    <th>Collector</th>
                    <th v-if="showActions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payment in payments" :key="payment.id">
                    <td>{{ prettify(payment.transaction_type ?? "revenue") }}</td>
                    <td>{{ prettify(payment.workflow_status ?? "paid") }}</td>
                    <td>{{ payment.or_number }}</td>
                    <td>{{ payment.resident ? `${payment.resident.last_name}, ${payment.resident.first_name}` : "-" }}</td>
                    <td>{{ prettify(payment.revenue_source ?? payment.expense_type ?? payment.service_type) }}</td>
                    <td>{{ payment.description }}</td>
                    <td>{{ payment.request_reference ?? payment.voucher_number ?? "-" }}</td>
                    <td class="font-medium text-slate-800">{{ formatMoney(payment.amount) }}</td>
                    <td>{{ formatDate(payment.paid_at) }}</td>
                    <td>{{ payment.collector?.name ?? "-" }}</td>
                    <td v-if="showActions">
                        <slot name="actions" :payment="payment" />
                    </td>
                </tr>
                <tr v-if="payments.length === 0">
                    <td :colspan="showActions ? 11 : 10" class="px-4 py-6 text-center text-slate-500">No payment records found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
