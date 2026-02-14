<script setup>
import { computed, reactive, ref } from "vue";
import axios from "axios";
import { Head, usePage } from "@inertiajs/vue3";
import {
    EnvelopeIcon,
    LockClosedIcon,
    ShieldCheckIcon,
    ServerStackIcon,
    UsersIcon,
} from "@heroicons/vue/24/outline";

const loading = ref(false);
const errors = ref({});
const showPassword = ref(false);
const page = usePage();

const form = reactive({
    email: "",
    password: "",
    remember: false,
});

const currentYear = computed(() => new Date().getFullYear());
const systemSettings = computed(() => page.props.systemSettings ?? {});
const barangayName = computed(() => systemSettings.value.barangay_name ?? "Barangay Management System");
const footerNote = computed(() => systemSettings.value.footer_note ?? "");

const themeKey = computed(() => {
    const value = systemSettings.value.login_theme;
    const allowed = ["emerald", "teal", "blue", "rose", "amber"];
    return allowed.includes(value) ? value : "emerald";
});

const theme = computed(() => {
    const palettes = {
        emerald: {
            bgGlowClass:
                "pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(16,185,129,0.35),transparent_30%),radial-gradient(circle_at_85%_80%,rgba(20,184,166,0.25),transparent_30%)]",
            accentText: "text-emerald-300",
            accentStrong: "text-emerald-700 hover:text-emerald-600",
            panelBorder: "border-emerald-300/20",
            iconAccent: "text-emerald-300",
            inputFocus: "focus:border-emerald-500",
            checkboxAccent: "text-emerald-600 focus:ring-emerald-500",
            submitButton: "bg-emerald-700 hover:bg-emerald-600",
        },
        teal: {
            bgGlowClass:
                "pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(20,184,166,0.35),transparent_30%),radial-gradient(circle_at_85%_80%,rgba(6,182,212,0.25),transparent_30%)]",
            accentText: "text-teal-300",
            accentStrong: "text-teal-700 hover:text-teal-600",
            panelBorder: "border-teal-300/20",
            iconAccent: "text-teal-300",
            inputFocus: "focus:border-teal-500",
            checkboxAccent: "text-teal-600 focus:ring-teal-500",
            submitButton: "bg-teal-700 hover:bg-teal-600",
        },
        blue: {
            bgGlowClass:
                "pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(59,130,246,0.35),transparent_30%),radial-gradient(circle_at_85%_80%,rgba(14,165,233,0.25),transparent_30%)]",
            accentText: "text-blue-300",
            accentStrong: "text-blue-700 hover:text-blue-600",
            panelBorder: "border-blue-300/20",
            iconAccent: "text-blue-300",
            inputFocus: "focus:border-blue-500",
            checkboxAccent: "text-blue-600 focus:ring-blue-500",
            submitButton: "bg-blue-700 hover:bg-blue-600",
        },
        rose: {
            bgGlowClass:
                "pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(244,63,94,0.35),transparent_30%),radial-gradient(circle_at_85%_80%,rgba(236,72,153,0.25),transparent_30%)]",
            accentText: "text-rose-300",
            accentStrong: "text-rose-700 hover:text-rose-600",
            panelBorder: "border-rose-300/20",
            iconAccent: "text-rose-300",
            inputFocus: "focus:border-rose-500",
            checkboxAccent: "text-rose-600 focus:ring-rose-500",
            submitButton: "bg-rose-700 hover:bg-rose-600",
        },
        amber: {
            bgGlowClass:
                "pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(245,158,11,0.35),transparent_30%),radial-gradient(circle_at_85%_80%,rgba(234,179,8,0.25),transparent_30%)]",
            accentText: "text-amber-300",
            accentStrong: "text-amber-700 hover:text-amber-600",
            panelBorder: "border-amber-300/20",
            iconAccent: "text-amber-300",
            inputFocus: "focus:border-amber-500",
            checkboxAccent: "text-amber-600 focus:ring-amber-500",
            submitButton: "bg-amber-700 hover:bg-amber-600",
        },
    };

    return palettes[themeKey.value];
});

const login = async () => {
    loading.value = true;
    errors.value = {};

    try {
        await axios.post("/login", form);
        window.location.href = "/dashboard";
    } catch (error) {
        if (error?.response?.status === 422) {
            errors.value = error.response.data?.errors ?? {};
        } else {
            errors.value = {
                email: ["Unable to login right now. Please try again."],
            };
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Login" />
    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100">
        <div :class="theme.bgGlowClass"></div>
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,rgba(15,23,42,0.35),rgba(2,6,23,0.85))]"></div>

        <div class="relative mx-auto grid min-h-screen w-full max-w-6xl items-center gap-8 px-4 py-10 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="hidden rounded-3xl border bg-slate-900/50 p-8 backdrop-blur lg:block" :class="theme.panelBorder">
                <p class="text-xs uppercase tracking-[0.22em]" :class="theme.accentText">Barangay Portal</p>
                <h1 class="mt-4 max-w-lg text-4xl font-semibold leading-tight text-white">
                    {{ barangayName }}
                </h1>
                <p class="mt-4 max-w-xl text-sm leading-6 text-slate-300">
                    Centralized records, transparent operations, and secure role-based access for daily barangay services.
                </p>

                <div class="mt-8 grid gap-3">
                    <div class="flex items-start gap-3 rounded-xl border border-slate-700 bg-slate-900/70 p-3">
                        <ShieldCheckIcon class="mt-0.5 h-5 w-5" :class="theme.iconAccent" />
                        <div>
                            <p class="text-sm font-medium text-white">Role-based security</p>
                            <p class="text-xs text-slate-400">Permission-scoped access for every module and action.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl border border-slate-700 bg-slate-900/70 p-3">
                        <ServerStackIcon class="mt-0.5 h-5 w-5" :class="theme.iconAccent" />
                        <div>
                            <p class="text-sm font-medium text-white">Unified modules</p>
                            <p class="text-xs text-slate-400">Residents, certificates, blotter, finance, and reports in one system.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl border border-slate-700 bg-slate-900/70 p-3">
                        <UsersIcon class="mt-0.5 h-5 w-5" :class="theme.iconAccent" />
                        <div>
                            <p class="text-sm font-medium text-white">Operational transparency</p>
                            <p class="text-xs text-slate-400">Audit trails and system logs for accountability.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-full rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-8">
                <div class="mb-6">
                    <p class="text-xs uppercase tracking-[0.18em]" :class="theme.accentStrong">Secure Access</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">Sign in to continue</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ footerNote || `Use your authorized ${barangayName} account credentials.` }}
                    </p>
                </div>

                <form @submit.prevent="login" class="space-y-5">
                    <div
                        v-if="errors.email?.length"
                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
                    >
                        {{ errors.email[0] }}
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <div class="relative">
                            <EnvelopeIcon class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-4 text-slate-900 focus:outline-none"
                                :class="theme.inputFocus"
                                placeholder="name@barangay.gov"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label class="block text-sm font-medium text-slate-700">Password</label>
                            <button type="button" class="text-xs font-medium" :class="theme.accentStrong" @click="showPassword = !showPassword">
                                {{ showPassword ? "Hide" : "Show" }}
                            </button>
                        </div>
                        <div class="relative">
                            <LockClosedIcon class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-4 text-slate-900 focus:outline-none"
                                :class="theme.inputFocus"
                                placeholder="Enter your password"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300" :class="theme.checkboxAccent" />
                        Keep me signed in
                    </label>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full rounded-lg py-2.5 font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                        :class="theme.submitButton"
                    >
                        <span v-if="!loading">Login</span>
                        <span v-else>Signing in...</span>
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-slate-500">
                    &copy; {{ currentYear }} {{ barangayName }}
                </p>
            </section>
        </div>
    </div>
</template>
