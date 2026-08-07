<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const form = useForm({
    email: "",
    password: "",
});

const submitting = ref(false);
const authError = ref<string | null>(null);

interface LoginResponse {
    access_token: string;
    token_type: string;
    expires_in: number;
}

async function submit() {
    submitting.value = true;
    authError.value = null;

    try {
        const response = await fetch("api/login", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Tenant": window.location.hostname.split(".")[0],
            },
            body: JSON.stringify({
                email: form.email,
                password: form.password,
            }),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            authError.value = body?.message ?? "Invalid email or password.";
            return;
        }

        const data: LoginResponse = await response.json();

        // Store token + when it expires (absolute timestamp, easier to check later).
        const expiresAt = Date.now() + data.expires_in * 1000;
        localStorage.setItem("access_token", data.access_token);
        localStorage.setItem("token_type", data.token_type);
        localStorage.setItem("expires_at", String(expiresAt));

        router.visit("dashboard");
    } catch (e) {
        authError.value = e instanceof Error ? e.message : "Something went wrong. Please try again.";
    } finally {
        submitting.value = false;
        form.password = "";
    }
}
</script>

<template>
    <main class="page">
        <section class="hero-panel">
            <div class="hero-content">
                <span class="eyebrow">Merchant Platform</span>

                <h1>Platform Administration</h1>

                <p class="description">
                    Access the central back office to manage tenants, monitor payment
                    processing, review system activity, and configure platform-wide
                    settings.
                </p>

                <ul class="feature-list">
                    <li>Tenant Management</li>
                    <li>Payment Monitoring</li>
                    <li>Gateway Configuration</li>
                    <li>Platform Analytics</li>
                </ul>
            </div>
        </section>

        <section class="form-panel">
            <div class="form-card">
                <div class="brand">
                    <span class="brand-mark">◆</span>
                    <span class="brand-name">Merchant Platform</span>
                </div>

                <span class="badge">Central Back Office</span>

                <h2>Administrator Login</h2>

                <p class="subtitle">
                    Sign in with your platform administrator account.
                </p>

                <form @submit.prevent="submit" novalidate>
                    <div class="field">
                        <label for="email">Administrator Email</label>

                        <input id="email" v-model="form.email" type="email" autocomplete="username" required
                            autofocus />

                        <p v-if="form.errors.email" class="error">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="field">
                        <div class="field-row">
                            <label for="password">Password</label>

                            <a href="#" class="link-muted">
                                Forgot password?
                            </a>
                        </div>

                        <input id="password" v-model="form.password" type="password" autocomplete="current-password"
                            required />

                        <p v-if="form.errors.password" class="error">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <p v-if="authError" class="error auth-error">
                        {{ authError }}
                    </p>

                    <button class="submit-btn" :disabled="submitting" type="submit">
                        {{ submitting ? "Signing in..." : "Sign in to Platform" }}
                    </button>
                </form>

                <div class="notice">
                    <strong>Restricted Area</strong>

                    <p>
                        This portal is reserved for platform administrators only. Tenant
                        administrators and wallet users must log in through their respective
                        tenant domains.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    height: 100vh;
    overflow: hidden;
    background: var(--paper);
    font-family: "Inter", system-ui, sans-serif;
    color: var(--ink);
}

/* ---------- Hero panel ---------- */
.hero-panel {
    background: #0f1115;
    color: var(--paper);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 48px 40px;
    overflow: hidden;
}

.hero-content {
    max-width: 380px;
}

.eyebrow {
    font-family: "JetBrains Mono", monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(250, 250, 248, 0.5);
}

.hero-content h1 {
    font-size: 30px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 16px 0 14px;
}

.description {
    font-size: 14px;
    line-height: 1.6;
    color: rgba(250, 250, 248, 0.6);
    margin: 0 0 28px;
}

.feature-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
}

.feature-list li {
    font-size: 13px;
    color: rgba(250, 250, 248, 0.75);
    padding-left: 18px;
    position: relative;
}

.feature-list li::before {
    content: "◆";
    position: absolute;
    left: 0;
    color: var(--signal);
    font-size: 9px;
    top: 3px;
}

/* ---------- Form panel ---------- */
.form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.form-card {
    width: 100%;
    max-width: 380px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 28px;
}

.brand-mark {
    color: var(--signal);
    font-size: 14px;
}

.brand-name {
    font-family: "JetBrains Mono", monospace;
    font-size: 13px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--slate);
}

.badge {
    display: inline-block;
    font-family: "JetBrains Mono", monospace;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--signal);
    border: 1px solid var(--hairline);
    border-radius: 999px;
    padding: 4px 10px;
    margin-bottom: 16px;
}

.form-card h2 {
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 8px;
}

.subtitle {
    color: var(--slate);
    font-size: 14px;
    margin: 0 0 32px;
}

.field {
    margin-bottom: 20px;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--ink);
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
}

input[type="email"]:focus,
input[type="password"]:focus {
    outline: 2px solid var(--signal);
    outline-offset: 1px;
    border-color: var(--signal);
}

.error {
    color: var(--failed);
    font-size: 12px;
    margin: 6px 0 0;
}

.auth-error {
    margin: 0 0 16px;
}

.submit-btn {
    width: 100%;
    padding: 12px;
    background: var(--signal);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.submit-btn:hover:not(:disabled) {
    background: #1c44d1;
}

.submit-btn:focus-visible {
    outline: 2px solid var(--ink);
    outline-offset: 2px;
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.link-muted {
    font-size: 12px;
    color: var(--slate);
    text-decoration: none;
}

.link-muted:hover {
    color: var(--signal);
    text-decoration: underline;
}

.notice {
    margin-top: 28px;
    padding: 16px;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    background: rgba(36, 84, 255, 0.03);
}

.notice strong {
    display: block;
    font-size: 13px;
    margin-bottom: 6px;
    color: var(--ink);
}

.notice p {
    font-size: 12px;
    line-height: 1.55;
    color: var(--slate);
    margin: 0;
}

/* ---------- Responsive ---------- */
@media (max-width: 860px) {
    .page {
        grid-template-columns: 1fr;
        height: auto;
        min-height: 100vh;
    }

    .hero-panel {
        display: none;
    }

    .form-panel {
        padding: 24px;
    }
}
</style>
