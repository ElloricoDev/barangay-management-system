<script setup>
import { CheckCircleIcon, ClockIcon, DocumentArrowUpIcon, XCircleIcon } from "@heroicons/vue/24/solid";

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

const statusClass = (status) => {
    if (status === "approved" || status === "released") return "border-emerald-200 bg-emerald-50 text-emerald-700";
    if (status === "rejected") return "border-rose-200 bg-rose-50 text-rose-700";
    if (status === "ready_for_approval") return "border-blue-200 bg-blue-50 text-blue-700";
    return "border-amber-200 bg-amber-50 text-amber-700";
};

const statusIcon = (status) => {
    if (status === "approved" || status === "released") return CheckCircleIcon;
    if (status === "rejected") return XCircleIcon;
    if (status === "ready_for_approval") return DocumentArrowUpIcon;
    return ClockIcon;
};

const statusLabel = (status) => String(status ?? "-").replace(/_/g, " ");
</script>

<template>
    <div class="ui-table-wrap" data-persist-scroll data-scroll-key="certificate-table">
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
                    <td>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium" :class="statusClass(certificate.status)">
                            <component :is="statusIcon(certificate.status)" class="h-3.5 w-3.5" />
                            {{ statusLabel(certificate.status) }}
                        </span>
                    </td>
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
