<script setup>
import { reactive, ref } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import { EnvelopeIcon, LockClosedIcon } from "@heroicons/vue/24/outline";

const loading = ref(false);

const form = reactive({
    email: "",
    password: "",
    remember: false,
});

const login = async () => {
    loading.value = true;

    try {
        await axios.post("/login", form);
        window.location.href = "/dashboard";
    } catch (error) {
        alert("Invalid credentials");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Login" />
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-600 to-emerald-800 px-4"
    >
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">
            <!-- Logo / Title -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    Barangay Management System
                </h1>
                <p class="text-sm text-gray-500 mt-1">Sign in to continue</p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="login" class="space-y-5">
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <div class="relative">
                        <EnvelopeIcon
                            class="w-5 h-5 absolute left-3 top-3 text-gray-400"
                        />

                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none"
                            placeholder="Enter your email"
                        />
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <LockClosedIcon
                            class="w-5 h-5 absolute left-3 top-3 text-gray-400"
                        />

                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none"
                            placeholder="Enter your password"
                        />
                    </div>
                </div>

                <!-- Button -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-semibold transition duration-300 disabled:opacity-60"
                >
                    <span v-if="!loading">Login</span>
                    <span v-else>Signing in...</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-6 text-center text-xs text-gray-500">
                © 2026 Barangay Management System
            </div>
        </div>
    </div>
</template>
