<script setup>
import { computed } from "vue";
import {
    ArchiveBoxIcon,
    ChartBarIcon,
    ChartPieIcon,
    CircleStackIcon,
    ClipboardDocumentCheckIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    DocumentArrowUpIcon,
    DocumentChartBarIcon,
    DocumentTextIcon,
    HomeIcon,
    IdentificationIcon,
    QueueListIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserCircleIcon,
    UserGroupIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: "",
    },
    icon: {
        type: String,
        default: "default",
    },
});

const iconMap = {
    dashboard: HomeIcon,
    residents: UserGroupIcon,
    certificates: DocumentTextIcon,
    blotter: ScaleIcon,
    financial: CircleStackIcon,
    payment: CreditCardIcon,
    receipts: ReceiptPercentIcon,
    collection: DocumentChartBarIcon,
    transactions: QueueListIcon,
    summary: ChartBarIcon,
    youth: UsersIcon,
    projects: Squares2X2Icon,
    reports: ClipboardDocumentCheckIcon,
    analytics: ChartPieIcon,
    archive: ArchiveBoxIcon,
    upload: DocumentArrowUpIcon,
    users: UserCircleIcon,
    roles: ShieldCheckIcon,
    matrix: QueueListIcon,
    audit: ShieldCheckIcon,
    logs: QueueListIcon,
    backup: ArchiveBoxIcon,
    settings: Cog6ToothIcon,
    quality: WrenchScrewdriverIcon,
    account: UserCircleIcon,
    default: IdentificationIcon,
};

const iconComponent = computed(() => iconMap[props.icon] ?? iconMap.default);
</script>

<template>
    <div class="ui-section-header items-center">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700">
                <component :is="iconComponent" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="ui-title">{{ title }}</h2>
                <p v-if="subtitle" class="ui-subtitle">{{ subtitle }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
