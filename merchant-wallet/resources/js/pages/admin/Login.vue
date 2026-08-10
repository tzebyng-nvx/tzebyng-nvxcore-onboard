<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
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
        const response = await fetch('/api/admin/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Tenant': window.location.hostname.split('.')[0],
            },
            body: JSON.stringify({
                email: form.email,
                password: form.password,
            }),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            authError.value = body?.message ?? 'Invalid email or password.';

            return;
        }

        const data: LoginResponse = await response.json();

        // Separate storage keys from the player login, so an admin and a
        // player session can't accidentally clobber each other in the
        // same browser.
        const expiresAt = Date.now() + data.expires_in * 1000;
        localStorage.setItem('admin_access_token', data.access_token);
        localStorage.setItem('admin_token_type', data.token_type);
        localStorage.setItem('admin_expires_at', String(expiresAt));

        router.visit('/admin/dashboard');
    } catch (e) {
        authError.value =
            e instanceof Error
                ? e.message
                : 'Something went wrong. Please try again.';
    } finally {
        submitting.value = false;
        form.password = '';
    }
}
</script>

<template>
    <Head title="Admin Log in">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/jetbrains-mono/2.304/jetbrains-mono.min.css"
        />
    </Head>

    <div class="shell">
        <!-- Left: restricted-access panel, replaces the ledger tape from player login -->
        <aside class="info-panel" aria-hidden="true">
            <div class="info-header">
                <span class="eyebrow">Admin access</span>
            </div>
            <div class="info-body">
                <p class="info-heading">Tenant administration</p>
                <p class="info-copy">
                    Review transactions, manage users, and approve withdrawals
                    for this tenant. Every action here is logged and tied to
                    your account.
                </p>
            </div>
            <p class="info-caption">Restricted area · staff only</p>
        </aside>

        <!-- Right: actual login form -->
        <main class="form-panel">
            <div class="form-card">
                <div class="brand">
                    <span class="brand-mark">▲</span>
                    <span class="brand-name">Ledger · Admin</span>
                </div>

                <h1>Admin log in</h1>
                <p class="subtitle">Sign in with your administrator account.</p>

                <form @submit.prevent="submit" novalidate>
                    <div class="field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            autofocus
                            required
                        />
                        <p v-if="form.errors.email" class="error">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                        />
                        <p v-if="form.errors.password" class="error">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <p v-if="authError" class="error auth-error">
                        {{ authError }}
                    </p>

                    <button
                        type="submit"
                        class="submit-btn"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Logging in…' : 'Log in' }}
                    </button>
                </form>

                <p class="footnote">
                    Not an admin?
                    <a href="/login" class="link-accent">Go to player login</a>
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.shell {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #b5651d;
    --success: #1f9d55;
    --pending: #c98a1c;
    --failed: #c9432c;

    display: grid;
    grid-template-columns: 1fr 1fr;
    height: 100vh;
    overflow: hidden;
    background: var(--paper);
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--ink);
}

/* ---------- Restricted-access info panel ---------- */
.info-panel {
    background: #0f1115;
    color: var(--paper);
    display: flex;
    flex-direction: column;
    padding: 48px 40px;
    position: relative;
    overflow: hidden;
}

.info-header {
    margin-bottom: 24px;
}

.eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(250, 250, 248, 0.5);
}

.info-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 340px;
}

.info-heading {
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 14px;
}

.info-copy {
    font-size: 14px;
    line-height: 1.6;
    color: rgba(250, 250, 248, 0.6);
    margin: 0;
}

.info-caption {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(181, 101, 29, 0.85);
    margin-top: 20px;
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
    margin-bottom: 40px;
}

.brand-mark {
    color: var(--signal);
    font-size: 14px;
}

.brand-name {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--slate);
}

h1 {
    font-size: 28px;
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

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--ink);
}

input[type='email'],
input[type='password'] {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
}

input[type='email']:focus,
input[type='password']:focus {
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
    background: #96521a;
}

.submit-btn:focus-visible {
    outline: 2px solid var(--ink);
    outline-offset: 2px;
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.link-accent {
    color: var(--signal);
    text-decoration: none;
    font-weight: 500;
}

.link-accent:hover {
    text-decoration: underline;
}

.footnote {
    font-size: 13px;
    color: var(--slate);
    margin-top: 28px;
    text-align: center;
}

/* ---------- Responsive ---------- */
@media (max-width: 860px) {
    .shell {
        grid-template-columns: 1fr;
    }

    .info-panel {
        display: none;
    }

    .form-panel {
        padding: 24px;
    }
}
</style>
