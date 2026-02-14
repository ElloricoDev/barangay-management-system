<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
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

const SIDEBAR_SCROLL_KEY = "app-shell:sidebar-scroll-top";
const SCROLL_MEMORY_PREFIX = "app-shell:panel-scroll";
const PANEL_SCROLL_SELECTOR = "[data-persist-scroll], .ui-table-wrap, .ui-modal";
let sidebarScrollTop = 0;
let removeFinishListener = null;
let panelScrollCleanup = [];

const page = usePage();
const isSidebarOpen = ref(false);
const showLogoutModal = ref(false);
const sidebarRef = ref(null);
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

const readSidebarScrollTop = () => {
    if (typeof window === "undefined") return 0;
    const raw = window.sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
    const value = Number(raw);
    return Number.isFinite(value) && value >= 0 ? value : 0;
};

const writeSidebarScrollTop = (value) => {
    if (typeof window === "undefined") return;
    window.sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(value));
};

const clearSidebarScrollTop = () => {
    if (typeof window === "undefined") return;
    window.sessionStorage.removeItem(SIDEBAR_SCROLL_KEY);
};

const panelScrollKey = (element, index) => {
    const customKey = element.dataset.scrollKey?.trim();
    if (customKey) return `${SCROLL_MEMORY_PREFIX}:${normalizedUrl.value}:${customKey}`;
    const signature = element.className?.toString().split(/\s+/).filter(Boolean).slice(0, 3).join(".") ?? "panel";
    return `${SCROLL_MEMORY_PREFIX}:${normalizedUrl.value}:${signature}:${index}`;
};

const readPanelScrollState = (key) => {
    if (typeof window === "undefined") return { top: 0, left: 0 };
    const raw = window.sessionStorage.getItem(key);
    if (!raw) return { top: 0, left: 0 };
    try {
        const parsed = JSON.parse(raw);
        const top = Number(parsed?.top);
        const left = Number(parsed?.left);
        return {
            top: Number.isFinite(top) && top >= 0 ? top : 0,
            left: Number.isFinite(left) && left >= 0 ? left : 0,
        };
    } catch {
        return { top: 0, left: 0 };
    }
};

const writePanelScrollState = (key, element) => {
    if (typeof window === "undefined") return;
    window.sessionStorage.setItem(
        key,
        JSON.stringify({
            top: element.scrollTop ?? 0,
            left: element.scrollLeft ?? 0,
        }),
    );
};

const clearPanelBindings = () => {
    panelScrollCleanup.forEach((cleanup) => cleanup());
    panelScrollCleanup = [];
};

const bindPersistentPanelScroll = async () => {
    if (typeof window === "undefined") return;
    await nextTick();
    clearPanelBindings();
    const panels = Array.from(document.querySelectorAll(PANEL_SCROLL_SELECTOR));

    panels.forEach((panel, index) => {
        const key = panelScrollKey(panel, index);
        const state = readPanelScrollState(key);
        panel.scrollTop = state.top;
        panel.scrollLeft = state.left;

        const onScroll = () => writePanelScrollState(key, panel);
        panel.addEventListener("scroll", onScroll, { passive: true });
        panelScrollCleanup.push(() => panel.removeEventListener("scroll", onScroll));
    });
};

const clearPanelScrollTop = () => {
    if (typeof window === "undefined") return;
    const keys = [];
    for (let i = 0; i < window.sessionStorage.length; i += 1) {
        const key = window.sessionStorage.key(i);
        if (key?.startsWith(SCROLL_MEMORY_PREFIX)) {
            keys.push(key);
        }
    }
    keys.forEach((key) => window.sessionStorage.removeItem(key));
};

const clearPersistedScroll = () => {
    clearSidebarScrollTop();
    clearPanelScrollTop();
};

const restoreSidebarScroll = async () => {
    await nextTick();
    if (!sidebarRef.value) return;
    sidebarRef.value.scrollTop = sidebarScrollTop;
};

const handleSidebarScroll = (event) => {
    sidebarScrollTop = event.target?.scrollTop ?? 0;
    writeSidebarScrollTop(sidebarScrollTop);
};

onMounted(async () => {
    sidebarScrollTop = readSidebarScrollTop();
    await restoreSidebarScroll();
    await bindPersistentPanelScroll();
    removeFinishListener = router.on("finish", async () => {
        await restoreSidebarScroll();
        await bindPersistentPanelScroll();
    });
    window.addEventListener("beforeunload", clearPersistedScroll);
});

onBeforeUnmount(() => {
    if (typeof removeFinishListener === "function") {
        removeFinishListener();
    }
    clearPanelBindings();
    window.removeEventListener("beforeunload", clearPersistedScroll);
});
</script>

<template>
    <div class="app-shell">
        <div class="app-shell__grid">
            <aside
                ref="sidebarRef"
                class="app-shell__sidebar"
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
                @scroll="handleSidebarScroll"
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
            <div class="ui-modal ui-modal--compact" data-persist-scroll data-scroll-key="logout-modal">
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
