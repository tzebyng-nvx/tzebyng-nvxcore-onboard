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
        const response = await fetch('api/login', {
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

        // Store token + when it expires (absolute timestamp, easier to check later).
        const expiresAt = Date.now() + data.expires_in * 1000;
        localStorage.setItem('access_token', data.access_token);
        localStorage.setItem('token_type', data.token_type);
        localStorage.setItem('expires_at', String(expiresAt));

        router.visit('dashboard');
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

// Purely decorative — fake rows for the ledger tape signature element.
const tape = [
    { ref: 'TXN-88213', amount: '+240.00', status: 'success' },
    { ref: 'TXN-88214', amount: '-75.50', status: 'pending' },
    { ref: 'TXN-88215', amount: '+1,200.00', status: 'success' },
    { ref: 'TXN-88216', amount: '-320.00', status: 'success' },
    { ref: 'TXN-88217', amount: '+58.20', status: 'pending' },
    { ref: 'TXN-88218', amount: '-14.99', status: 'failed' },
    { ref: 'TXN-88219', amount: '+900.00', status: 'success' },
    { ref: 'TXN-88220', amount: '-450.00', status: 'success' },
];
</script>

<template>
    <Head title="Log in">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/jetbrains-mono/2.304/jetbrains-mono.min.css"
        />
    </Head>

    <div class="shell">
        <!-- Left: ledger tape signature panel -->
        <aside class="tape-panel" aria-hidden="true">
            <div class="tape-header">
                <span class="eyebrow">Live ledger</span>
            </div>
            <div class="tape-viewport">
                <div class="tape-track">
                    <div
                        v-for="(row, i) in [...tape, ...tape]"
                        :key="i"
                        class="tape-row"
                    >
                        <span class="tape-ref">{{ row.ref }}</span>
                        <span
                            class="tape-amount"
                            :class="
                                row.amount.startsWith('+')
                                    ? 'is-credit'
                                    : 'is-debit'
                            "
                            >{{ row.amount }}</span
                        >
                        <span class="tape-dot" :class="`is-${row.status}`" />
                    </div>
                </div>
            </div>
            <p class="tape-caption">
                Every balance change, backed by an entry.
            </p>
        </aside>

        <!-- Right: actual login form -->
        <main class="form-panel">
            <div class="form-card">
                <div class="brand">
                    <span class="brand-mark">◆</span>
                    <span class="brand-name">Ledger</span>
                </div>

                <h1>Log in</h1>
                <p class="subtitle">
                    Access your wallet and transaction history.
                </p>

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
                        <div class="field-row">
                            <label for="password">Password</label>
                            <a href="#" class="link-muted">Forgot password?</a>
                        </div>
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
                    No account?
                    <a href="/register" class="link-accent">Create one</a>
                </p>

                <div class="admin-switch">
                    <span>Are you an administrator?</span>
                    <a href="/admin/login" class="admin-switch-link"
                        >Admin login →</a
                    >
                </div>
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
    --signal: #2454ff;
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

/* ---------- Ledger tape panel ---------- */
.tape-panel {
    background: var(--ink);
    color: var(--paper);
    display: flex;
    flex-direction: column;
    padding: 48px 40px;
    position: relative;
    overflow: hidden;
}

.tape-header {
    margin-bottom: 24px;
}

.eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(250, 250, 248, 0.55);
}

.tape-viewport {
    flex: 1;
    overflow: hidden;
    mask-image: linear-gradient(
        to bottom,
        transparent 0%,
        black 12%,
        black 88%,
        transparent 100%
    );
}

.tape-track {
    display: flex;
    flex-direction: column;
    animation: scrollTape 18s linear infinite;
}

@keyframes scrollTape {
    from {
        transform: translateY(0);
    }

    to {
        transform: translateY(-50%);
    }
}

.tape-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid rgba(250, 250, 248, 0.08);
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
}

.tape-ref {
    color: rgba(250, 250, 248, 0.5);
}

.tape-amount.is-credit {
    color: #7fd99a;
}

.tape-amount.is-debit {
    color: rgba(250, 250, 248, 0.75);
}

.tape-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    justify-self: end;
}

.tape-dot.is-success {
    background: var(--success);
}

.tape-dot.is-pending {
    background: var(--pending);
}

.tape-dot.is-failed {
    background: var(--failed);
}

.tape-caption {
    font-size: 13px;
    color: rgba(250, 250, 248, 0.45);
    margin-top: 20px;
}

@media (prefers-reduced-motion: reduce) {
    .tape-track {
        animation: none;
    }
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

.field-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
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

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--slate);
    margin-bottom: 24px;
    cursor: pointer;
}

.checkbox-row input {
    accent-color: var(--signal);
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
    background: #1c40cc;
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
    text-decoration: underline;
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

.admin-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
    padding: 12px 16px;
    border: 1px solid var(--hairline);
    border-radius: 8px;
    font-size: 13px;
    color: var(--slate);
}

.admin-switch-link {
    font-weight: 500;
    color: var(--signal);
    text-decoration: none;
    white-space: nowrap;
}

.admin-switch-link:hover {
    text-decoration: underline;
}

/* ---------- Responsive ---------- */
@media (max-width: 860px) {
    .shell {
        grid-template-columns: 1fr;
    }

    .tape-panel {
        display: none;
    }

    .form-panel {
        padding: 24px;
    }
}
</style>
