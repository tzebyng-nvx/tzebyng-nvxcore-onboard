<script setup lang="ts">
import AdminShell from "@/components/AdminShell.vue";
import { useToast } from "@/composables/useToast";
import { useTour } from "@/composables/useTour";
import { adminAuthHeaders } from "@/utils/authHeaders";
import { type Errors, maxLength, required, url, validate } from "@/utils/validation";
import { Head } from "@inertiajs/vue3";
import { onMounted, reactive, ref } from "vue";

const form = reactive({
    merchant_username: "",
    api_key: "",
    secret_key: "",
    base_url: "",
});

const loading = ref(true);
const saving = ref(false);
const loadError = ref<string | null>(null);
const formError = ref<string | null>(null);
const fieldErrors = ref<Errors>({});
const showSecret = ref(false);

const { showToast } = useToast();

function validateForm(): boolean {
    fieldErrors.value = validate(form, {
        merchant_username: [required("Merchant username is required."), maxLength(255)],
        api_key: [required("API key is required."), maxLength(255)],
        secret_key: [required("Secret key is required."), maxLength(255)],
        base_url: [required("Base URL is required."), url(), maxLength(255)],
    });
    return Object.keys(fieldErrors.value).length === 0;
}

useTour("admin-gateway-v2", [
    {
        element: '[data-tour="gateway-form"]',
        popover: {
            title: "Payment gateway settings",
            description:
                "These credentials connect your tenant to the payment gateway. They power every deposit and withdrawal, so keep them accurate and private.",
        },
    },
    {
        element: "#merchant_username",
        popover: {
            title: "Merchant username",
            description:
                "Your merchant account identifier at the gateway. Find it in your gateway dashboard under account or API settings.",
        },
    },
    {
        element: "#api_key",
        popover: {
            title: "API key",
            description:
                "The public key that identifies your integration on each request to the gateway.",
        },
    },
    {
        element: "#secret_key",
        popover: {
            title: "Secret key",
            description:
                "The private key used to sign requests. Treat it like a password — use “Show” to reveal it only when you need to verify it.",
        },
    },
    {
        element: '[data-tour="secret-toggle"]',
        popover: {
            title: "Show / hide secret",
            description: "Toggle visibility of the secret key while editing.",
        },
    },
    {
        element: "#base_url",
        popover: {
            title: "Base URL",
            description:
                "The gateway endpoint requests are sent to. Use the sandbox URL while testing and the live URL in production.",
        },
    },
    {
        element: '[data-tour="gateway-save"]',
        popover: {
            title: "Save settings",
            description:
                "Store your changes. They take effect immediately for all new transactions in this tenant.",
        },
    },
]);

onMounted(async () => {
    await loadSettings();
});

async function loadSettings() {
    loading.value = true;

    try {
        const response = await fetch("/api/admin/payment-gateway-settings", {
            headers: adminAuthHeaders(),
        });

        if (!response.ok) {
            throw new Error("Failed to load settings");
        }

        const data = await response.json();

        if (data) {
            Object.assign(form, data);
        }
    } catch (error) {
        loadError.value = error instanceof Error
            ? error.message
            : "Failed to load settings";
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!validateForm()) {
        return;
    }

    saving.value = true;
    formError.value = null;

    try {
        const response = await fetch(
            "/api/admin/payment-gateway-settings",
            {
                method: "PUT",
                headers: {
                    ...adminAuthHeaders(),
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(form),
            }
        );

        if (!response.ok) {
            const body = await response.json().catch(() => null);

            const message = body?.message ?? "Failed to save settings";
            formError.value = message;

            showToast(message, "error");

            return;
        }

        const data = await response.json();
        Object.assign(form, data);

        showToast("Gateway settings saved.", "success");

    } catch (error) {
        formError.value = "Something went wrong. Please try again.";
        showToast("Something went wrong. Please try again.", "error");
    } finally {
        saving.value = false;
    }
}
</script>

<template>

    <Head title="Payment Gateway Settings" />

    <AdminShell active="gateway" eyebrow="Back office" title="Gateway settings">
        <div class="settings-card">
            <p v-if="loadError" class="error">{{ loadError }}</p>
            <p v-if="formError" class="error">{{ formError }}</p>

            <div v-if="loading" class="loading-state">
                Loading…
            </div>

            <form v-else @submit.prevent="save" class="settings-form" data-tour="gateway-form" novalidate>
                <div class="field">
                    <label for="merchant_username">Merchant username</label>
                    <input id="merchant_username" v-model="form.merchant_username" type="text" autocomplete="off"
                        :class="{ 'is-invalid': fieldErrors.merchant_username }" />
                    <span v-if="fieldErrors.merchant_username" class="field-error">
                        {{ fieldErrors.merchant_username }}
                    </span>
                </div>

                <div class="field">
                    <label for="api_key">API key</label>
                    <input id="api_key" v-model="form.api_key" type="text" autocomplete="off"
                        :class="{ 'is-invalid': fieldErrors.api_key }" />
                    <span v-if="fieldErrors.api_key" class="field-error">{{ fieldErrors.api_key }}</span>
                </div>

                <div class="field">
                    <div class="field-row">
                        <label for="secret_key">Secret key</label>

                        <button type="button" class="link-btn" data-tour="secret-toggle"
                            @click="showSecret = !showSecret">
                            {{ showSecret ? "Hide" : "Show" }}
                        </button>
                    </div>

                    <input id="secret_key" v-model="form.secret_key" :type="showSecret ? 'text' : 'password'"
                        autocomplete="off" :class="{ 'is-invalid': fieldErrors.secret_key }" />
                    <span v-if="fieldErrors.secret_key" class="field-error">{{ fieldErrors.secret_key }}</span>
                </div>

                <div class="field">
                    <label for="base_url">Base URL</label>

                    <input id="base_url" v-model="form.base_url" type="url"
                        placeholder="https://sandbox.gateway.example.com"
                        :class="{ 'is-invalid': fieldErrors.base_url }" />
                    <span v-if="fieldErrors.base_url" class="field-error">{{ fieldErrors.base_url }}</span>
                </div>

                <div class="panel-actions">
                    <button type="submit" class="submit-btn" data-tour="gateway-save" :disabled="saving">
                        {{ saving ? "Saving…" : "Save settings" }}
                    </button>
                </div>
            </form>
        </div>
    </AdminShell>
</template>

<style scoped>
.settings-card {
    --ink: #1b2430;
    --slate: #5b6570;
    --paper: #fafaf8;
    --hairline: #e4e1d8;
    --signal: #b5651d;
    --success: #1f9d55;
    --failed: #c9432c;

    max-width: 560px;
    background: white;
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 28px;
    color: var(--ink);
    font-family: "Inter", system-ui, sans-serif;
}

.eyebrow {
    font-family: "JetBrains Mono", monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--slate);
}

h1 {
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 8px 0 6px;
}

.subtitle {
    color: var(--slate);
    font-size: 13px;
    margin: 0;
}

.subtitle code {
    font-family: "JetBrains Mono", monospace;
    background: var(--hairline);
    padding: 1px 5px;
    border-radius: 4px;
}

.add-btn {
    background: var(--signal);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.add-btn:hover {
    background: #96521a;
}

.settings-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.settings-table th {
    text-align: left;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--slate);
    padding: 8px 10px;
    border-bottom: 1px solid var(--hairline);
}

.settings-table td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--hairline);
}

.mono {
    font-family: "JetBrains Mono", monospace;
}

.muted {
    color: var(--slate);
}

.truncate {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
}

.status-pill.is-active {
    background: rgba(31, 157, 85, 0.12);
    color: var(--success);
}

.status-pill.is-inactive {
    background: var(--hairline);
    color: var(--slate);
}

.row-actions {
    display: flex;
    gap: 8px;
    white-space: nowrap;
}

.row-btn {
    background: transparent;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12px;
    color: var(--slate);
    cursor: pointer;
}

.row-btn:hover {
    border-color: var(--slate);
    color: var(--ink);
}

.row-btn.is-danger:hover {
    border-color: var(--failed);
    color: var(--failed);
}

.row-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.empty-state,
.loading-state {
    color: var(--slate);
    font-size: 14px;
    padding: 32px 0;
    text-align: center;
}

.error {
    color: var(--failed);
    font-size: 13px;
    margin: 0 0 16px;
}

/* ---------- Slide-over panel ---------- */
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(27, 36, 48, 0.4);
    display: flex;
    justify-content: flex-end;
    z-index: 50;
}

.panel {
    width: 100%;
    max-width: 440px;
    height: 100%;
    background: var(--paper);
    padding: 32px;
    overflow-y: auto;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.panel-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.close-btn {
    background: transparent;
    border: none;
    font-size: 16px;
    color: var(--slate);
    cursor: pointer;
}

.field {
    margin-bottom: 16px;
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
}

input[type="text"],
input[type="url"],
input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: var(--ink);
}

input:focus {
    outline: 2px solid var(--signal);
    outline-offset: 1px;
    border-color: var(--signal);
}

input.is-invalid {
    border-color: var(--failed);
}

input.is-invalid:focus {
    outline-color: var(--failed);
    border-color: var(--failed);
}

.field-error {
    display: block;
    font-size: 12px;
    color: var(--failed);
    margin-top: 5px;
}

.link-btn {
    background: none;
    border: none;
    color: var(--signal);
    font-size: 12px;
    cursor: pointer;
    padding: 0;
}

.hint {
    font-size: 12px;
    color: var(--slate);
    margin: 6px 0 0;
}

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--slate);
    margin: 4px 0 20px;
    cursor: pointer;
}

.checkbox-row input {
    accent-color: var(--signal);
}

.panel-actions {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.cancel-btn {
    flex: 1;
    padding: 11px;
    background: transparent;
    border: 1px solid var(--hairline);
    border-radius: 6px;
    font-size: 14px;
    color: var(--slate);
    cursor: pointer;
}

.cancel-btn:hover {
    border-color: var(--slate);
    color: var(--ink);
}

.submit-btn {
    flex: 2;
    padding: 11px;
    background: var(--signal);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.submit-btn:hover:not(:disabled) {
    background: #96521a;
}

.submit-btn:disabled {
    opacity: 0.5;
    cursor: default;
}
</style>