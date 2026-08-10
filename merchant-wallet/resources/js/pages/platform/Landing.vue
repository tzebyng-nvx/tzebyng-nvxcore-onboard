<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";

type Audience = "platform" | "tenant";

const audience = ref<Audience>("platform");
const tenantName = ref("");

// Normalise to a valid subdomain label: lowercase, digits, and hyphens only.
const normalisedTenant = computed(() =>
    tenantName.value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, "")
);

const canContinue = computed(() =>
    audience.value === "platform" || normalisedTenant.value.length > 0
);

const tenantPreview = computed(() => {
    const host = window.location.host; // includes port in dev, e.g. localhost:8000
    return normalisedTenant.value
        ? `${normalisedTenant.value}.${host}`
        : `<tenant>.${host}`;
});

function proceed() {
    if (!canContinue.value) return;

    if (audience.value === "platform") {
        window.location.assign("/login");
        return;
    }

    const { protocol, host } = window.location;
    window.location.assign(`${protocol}//${normalisedTenant.value}.${host}/`);
}
</script>

<template>
    <main class="page">

        <Head title="Merchant Platform" />

        <section class="hero-panel">
            <div class="hero-content">
                <span class="eyebrow">Merchant Platform</span>
                <h1>Where would you like to go?</h1>
                <p class="description">
                    This is the central entry point. Continue to the platform back office,
                    or open a specific tenant workspace by name.
                </p>
                <ul class="feature-list">
                    <li>Platform-wide tenant management</li>
                    <li>Per-tenant admin &amp; wallet portals</li>
                    <li>Isolated data per tenant</li>
                </ul>
            </div>
        </section>

        <section class="form-panel">
            <div class="form-card">
                <div class="brand">
                    <span class="brand-mark">◆</span>
                    <span class="brand-name">Merchant Platform</span>
                </div>

                <span class="badge">Choose destination</span>
                <h2>Continue as…</h2>
                <p class="subtitle">Select where you're headed to continue.</p>

                <div class="choice-grid">
                    <button type="button" class="choice" :class="{ 'is-selected': audience === 'platform' }"
                        @click="audience = 'platform'">
                        <span class="choice-mark">▣</span>
                        <span class="choice-title">Platform administrator</span>
                        <span class="choice-note">Central back office &amp; tenant management</span>
                    </button>

                    <button type="button" class="choice" :class="{ 'is-selected': audience === 'tenant' }"
                        @click="audience = 'tenant'">
                        <span class="choice-mark">◈</span>
                        <span class="choice-title">Tenant workspace</span>
                        <span class="choice-note">Admin &amp; wallet users of a tenant</span>
                    </button>
                </div>

                <form @submit.prevent="proceed" novalidate>
                    <div v-if="audience === 'tenant'" class="field">
                        <label for="tenant">Tenant name</label>
                        <input id="tenant" v-model="tenantName" type="text" autocomplete="off"
                            placeholder="e.g. demo" @keyup.enter="proceed" />
                        <p class="preview">You'll be taken to <code>{{ tenantPreview }}</code></p>
                    </div>

                    <button class="submit-btn" :disabled="!canContinue" type="submit">
                        {{ audience === 'platform' ? 'Continue to Platform' : 'Open tenant workspace' }}
                    </button>
                </form>

                <div class="notice">
                    <strong>Not sure?</strong>
                    <p>
                        Platform administrators manage every tenant. If you're a tenant admin
                        or a wallet user, choose <em>Tenant workspace</em> and enter your
                        tenant's name.
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
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #2454ff;
    --failed: #c9432c;

    display: grid;
    grid-template-columns: 1fr 1fr;
    height: 100vh;
    width: 100%;
    max-width: 100%;
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
    min-width: 0;
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
    padding: 32px 40px;
    min-width: 0;
    overflow: hidden;
}

.form-card {
    width: 100%;
    max-width: 400px;
    min-width: 0;
}

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
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
    margin: 0 0 20px;
}

/* ---------- Choice cards ---------- */
.choice-grid {
    display: grid;
    gap: 10px;
    margin-bottom: 20px;
}

.choice {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 12px;
    align-items: center;
    text-align: left;
    padding: 14px 16px;
    border: 1px solid var(--hairline);
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.choice:hover {
    border-color: var(--slate);
}

.choice.is-selected {
    border-color: var(--signal);
    box-shadow: 0 0 0 3px rgba(36, 84, 255, 0.12);
}

.choice-mark {
    grid-row: 1 / span 2;
    font-family: "JetBrains Mono", monospace;
    font-size: 18px;
    color: var(--signal);
}

.choice-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
}

.choice-note {
    font-size: 12px;
    color: var(--slate);
}

/* ---------- Tenant field ---------- */
.field {
    margin: 0 0 16px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--ink);
}

input[type="text"] {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
}

input[type="text"]:focus {
    outline: 2px solid var(--signal);
    outline-offset: 1px;
    border-color: var(--signal);
}

.preview {
    font-size: 12px;
    color: var(--slate);
    margin: 8px 0 0;
    overflow-wrap: anywhere;
}

.preview code {
    font-family: "JetBrains Mono", monospace;
    background: var(--hairline);
    padding: 1px 6px;
    border-radius: 4px;
    color: var(--ink);
    overflow-wrap: anywhere;
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

.submit-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.notice {
    margin-top: 20px;
    padding: 14px 16px;
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
    }

    .hero-panel {
        display: none;
    }

    .form-panel {
        padding: 24px;
    }
}
</style>
