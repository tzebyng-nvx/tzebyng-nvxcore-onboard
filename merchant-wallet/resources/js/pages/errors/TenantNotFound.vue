<script setup lang="ts">
import { Head } from "@inertiajs/vue3";

defineProps<{
    host: string;
    centralUrl: string;
}>();
</script>

<template>
    <main class="page">

        <Head title="Tenant not found" />

        <section class="card">
            <span class="badge">404 · Tenant not found</span>
            <h1>This workspace doesn't exist</h1>
            <p class="lead">
                We couldn't find a tenant for
                <code>{{ host }}</code>.
                It may have been removed, or the address may be mistyped.
            </p>

            <ul class="hints">
                <li>Double-check the spelling of the workspace name in the address.</li>
                <li>If the tenant was recently created, give it a moment and retry.</li>
                <li>Otherwise, head back to the central entry point to continue.</li>
            </ul>

            <div class="actions">
                <a class="btn primary" :href="centralUrl">Go to entry point</a>
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

    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
    background: var(--paper);
    font-family: "Inter", system-ui, sans-serif;
    color: var(--ink);
}

.card {
    width: 100%;
    max-width: 460px;
    background: white;
    border: 1px solid var(--hairline);
    border-radius: 12px;
    padding: 32px;
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
    margin-bottom: 18px;
}

h1 {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 12px;
}

.lead {
    font-size: 14px;
    line-height: 1.6;
    color: var(--slate);
    margin: 0 0 20px;
    overflow-wrap: anywhere;
}

.lead code {
    font-family: "JetBrains Mono", monospace;
    background: var(--hairline);
    padding: 1px 6px;
    border-radius: 4px;
    color: var(--ink);
}

.hints {
    margin: 0 0 24px;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
}

.hints li {
    font-size: 13px;
    line-height: 1.5;
    color: var(--slate);
    padding-left: 18px;
    position: relative;
}

.hints li::before {
    content: "◆";
    position: absolute;
    left: 0;
    top: 2px;
    font-size: 9px;
    color: var(--signal);
}

.actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.btn.primary {
    background: var(--signal);
    color: white;
}

.btn.primary:hover {
    background: #1c44d1;
}
</style>
