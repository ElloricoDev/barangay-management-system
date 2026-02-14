<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
    ArchiveBoxIcon,
    ArrowPathRoundedSquareIcon,
    ArrowTrendingUpIcon,
    Bars3Icon,
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
    LockClosedIcon,
    QueueListIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserCircleIcon,
    UserGroupIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
    ArrowRightOnRectangleIcon,
} from "@heroicons/vue/24/outline";

const props = defineProps({
    title: {
        type: String,
        default: "Dashboard",
    },
    userName: {
        type: String,
        default: "User",
    },
    workspaceLabel: {
        type: String,
        default: "Workspace",
    },
    panelLabel: {
        type: String,
        default: "Panel",
    },
    homeHref: {
        type: String,
        default: "/dashboard",
    },
    navItems: {
        type: Array,
        default: () => [],
    },
    showAccountLink: {
        type: Boolean,
        default: false,
    },
    accountHref: {
        type: String,
        default: "/account",
    },
});

const page = usePage();
const isSidebarOpen = ref(false);
const showLogoutModal = ref(false);
const barangayName = computed(() => page.props.systemSettings?.barangay_name ?? "Barangay Management System");

const normalizedUrl = computed(() => {
    const url = page.url ?? "";
    const [path] = url.split("?");
    return path;
});

const visibleNavItems = computed(() => props.navItems.filter((item) => item?.show !== false));
const iconMap = {
    residents: UserGroupIcon,
    certificates: DocumentTextIcon,
    blotter: ScaleIcon,
    financial: CircleStackIcon,
    payment: CreditCardIcon,
    receipts: ReceiptPercentIcon,
    collection: DocumentChartBarIcon,
    transactions: ArrowPathRoundedSquareIcon,
    summary: ChartBarIcon,
    youth: UsersIcon,
    projects: Squares2X2Icon,
    reports: ClipboardDocumentCheckIcon,
    analytics: ChartPieIcon,
    archive: ArchiveBoxIcon,
    upload: DocumentArrowUpIcon,
    users: UserCircleIcon,
    roles: LockClosedIcon,
    matrix: QueueListIcon,
    audit: ShieldCheckIcon,
    logs: QueueListIcon,
    backup: ArrowTrendingUpIcon,
    settings: Cog6ToothIcon,
    quality: WrenchScrewdriverIcon,
};

const resolveIcon = (name) => iconMap[name] ?? IdentificationIcon;

const isActive = (href) => {
    if (!href) return false;
    const path = normalizedUrl.value;
    return path === href || path.startsWith(`${href}/`);
};

const confirmLogout = () => {
    router.post("/logout");
};
</script>

<template>
    <div class="app-shell">
        <div class="app-shell__grid">
            <aside
                class="app-shell__sidebar"
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            >
                <div class="app-shell__brand">
                    <p class="app-shell__barangay">{{ barangayName }}</p>
                    <h2 class="app-shell__panel">{{ panelLabel }}</h2>
                </div>

                <nav class="app-shell__nav">
                    <Link
                        :href="homeHref"
                        class="app-shell__link"
                        :class="{ 'app-shell__link--active': isActive(homeHref) }"
                    >
                        <HomeIcon class="app-shell__link-icon" />
                        <span class="app-shell__link-text">Dashboard</span>
                    </Link>
                    <Link
                        v-for="item in visibleNavItems"
                        :key="item.href"
                        :href="item.href"
                        class="app-shell__link"
                        :class="{ 'app-shell__link--active': isActive(item.href) }"
                    >
                        <component :is="resolveIcon(item.icon)" class="app-shell__link-icon" />
                        <span class="app-shell__link-text">{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>

            <div v-if="isSidebarOpen" class="app-shell__overlay" @click="isSidebarOpen = false" />

            <div class="app-shell__main">
                <header class="app-shell__header">
                    <div class="app-shell__header-row">
                        <div class="app-shell__header-start">
                            <button type="button" class="ui-btn ui-btn--ghost md:hidden" @click="isSidebarOpen = !isSidebarOpen">
                                <Bars3Icon class="h-4 w-4" />
                                <span>Menu</span>
                            </button>
                            <div>
                                <h1 class="text-lg font-semibold">{{ title }}</h1>
                                <p class="text-xs text-slate-500">{{ workspaceLabel }}</p>
                            </div>
                        </div>

                        <div class="app-shell__header-end">
                            <span class="hidden text-sm text-slate-600 sm:inline">Signed in as {{ userName }}</span>
                            <Link v-if="showAccountLink" :href="accountHref" class="ui-btn ui-btn--ghost">
                                <UserCircleIcon class="h-4 w-4" />
                                <span>My Account</span>
                            </Link>
                            <button type="button" class="ui-btn ui-btn--danger-outline" @click="showLogoutModal = true">
                                <ArrowRightOnRectangleIcon class="h-4 w-4" />
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                </header>

                <main class="app-shell__content">
                    <div class="ui-card">
                        <slot name="header" />
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <div v-if="showLogoutModal" class="ui-modal-backdrop">
            <div class="ui-modal">
                <h3 class="text-lg font-semibold text-slate-800">Confirm Logout</h3>
                <p class="mt-2 text-sm text-slate-600">Are you sure you want to logout?</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="ui-btn ui-btn--ghost" @click="showLogoutModal = false">Cancel</button>
                    <button type="button" class="ui-btn ui-btn--danger" @click="confirmLogout">Logout</button>
                </div>
            </div>
        </div>
    </div>
</template>
