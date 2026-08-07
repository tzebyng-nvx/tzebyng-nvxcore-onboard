<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { reactive, ref } from "vue";

const form = reactive({
  name: "",
  email: "",
  phone_number: "",
  password: "",
  password_confirmation: "",
});

const submitting = ref(false);
const authError = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

interface RegisterResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
}

async function submit() {
  submitting.value = true;
  authError.value = null;
  fieldErrors.value = {};

  try {
    const response = await fetch("api/register", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Tenant": window.location.hostname.split(".")[0],
      },
      body: JSON.stringify(form),
    });

    if (response.status === 422) {
      const body = await response.json().catch(() => null);
      fieldErrors.value = body?.errors ?? {};
      authError.value = body?.message ?? "Please fix the errors below.";
      return;
    }

    if (!response.ok) {
      const body = await response.json().catch(() => null);
      authError.value = body?.message ?? "Could not create your account.";
      return;
    }

    const data: RegisterResponse = await response.json();

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
    form.password_confirmation = "";
  }
}

function fieldError(name: string): string | null {
  return fieldErrors.value[name]?.[0] ?? null;
}
</script>

<template>

  <Head title="Create account">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jetbrains-mono/2.304/jetbrains-mono.min.css" />
  </Head>

  <div class="shell">
    <!-- Left: brand panel -->
    <aside class="intro-panel" aria-hidden="true">
      <span class="eyebrow">Welcome to Ledger</span>
      <h2 class="intro-heading">Your wallet, ready in a minute.</h2>
      <ul class="intro-list">
        <li>Instant deposits and withdrawals</li>
        <li>Every balance change backed by an entry</li>
        <li>Full transaction history at a glance</li>
      </ul>
      <p class="intro-caption">Create an account to get started.</p>
    </aside>

    <!-- Right: registration form -->
    <main class="form-panel">
      <div class="form-card">
        <div class="brand">
          <span class="brand-mark">◆</span>
          <span class="brand-name">Ledger</span>
        </div>

        <h1>Create your account</h1>
        <p class="subtitle">Set up your wallet in a few seconds.</p>

        <form @submit.prevent="submit" novalidate>
          <div class="field">
            <label for="name">Full name</label>
            <input id="name" v-model="form.name" type="text" autocomplete="name" autofocus required />
            <p v-if="fieldError('name')" class="error">{{ fieldError('name') }}</p>
          </div>

          <div class="field">
            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" required />
            <p v-if="fieldError('email')" class="error">{{ fieldError('email') }}</p>
          </div>

          <div class="field">
            <label for="phone">Phone number</label>
            <input id="phone" v-model="form.phone_number" type="tel" autocomplete="tel" required />
            <p v-if="fieldError('phone_number')" class="error">{{ fieldError('phone_number') }}</p>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" required />
            <p v-if="fieldError('password')" class="error">{{ fieldError('password') }}</p>
          </div>

          <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password"
              autocomplete="new-password" required />
          </div>

          <p v-if="authError" class="error auth-error">{{ authError }}</p>

          <button type="submit" class="submit-btn" :disabled="submitting">
            {{ submitting ? "Creating account…" : "Create account" }}
          </button>
        </form>

        <p class="footnote">Already have an account? <a href="/login" class="link-accent">Log in</a></p>
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
  min-height: 100vh;
  background: var(--paper);
  font-family: "Inter", system-ui, sans-serif;
  color: var(--ink);
}

/* ---------- Intro panel ---------- */
.intro-panel {
  background: linear-gradient(135deg, #1b2430 0%, #232f3e 100%);
  color: var(--paper);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 56px 48px;
  position: relative;
  overflow: hidden;
}

.intro-panel::after {
  content: "◆";
  position: absolute;
  right: -20px;
  bottom: -40px;
  font-size: 220px;
  color: rgba(36, 84, 255, 0.10);
  pointer-events: none;
}

.eyebrow {
  font-family: "JetBrains Mono", monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(250, 250, 248, 0.55);
}

.intro-heading {
  font-size: 30px;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin: 16px 0 28px;
  max-width: 320px;
}

.intro-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}

.intro-list li {
  font-size: 14px;
  color: rgba(250, 250, 248, 0.75);
  padding-left: 20px;
  position: relative;
}

.intro-list li::before {
  content: "◆";
  position: absolute;
  left: 0;
  top: 2px;
  font-size: 9px;
  color: var(--signal);
}

.intro-caption {
  font-size: 13px;
  color: rgba(250, 250, 248, 0.45);
  margin-top: 28px;
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
  max-width: 400px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 36px;
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

h1 {
  font-size: 28px;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin: 0 0 8px;
}

.subtitle {
  color: var(--slate);
  font-size: 14px;
  margin: 0 0 28px;
}

.field {
  margin-bottom: 18px;
}

label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 6px;
  color: var(--ink);
}

input {
  width: 100%;
  padding: 11px 12px;
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

.error {
  color: var(--failed);
  font-size: 12px;
  margin: 6px 0 0;
}

.auth-error {
  margin: 4px 0 16px;
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
  margin-top: 24px;
  text-align: center;
}

/* ---------- Responsive ---------- */
@media (max-width: 860px) {
  .shell {
    grid-template-columns: 1fr;
  }

  .intro-panel {
    display: none;
  }

  .form-panel {
    padding: 24px;
  }
}
</style>
