<script setup lang="ts">
import AdminShell from "@/components/AdminShell.vue";
import Pagination from "@/components/Pagination.vue";
import { DEFAULT_PAGE_SIZE } from "@/config/pagination";
import { useTour } from "@/composables/useTour";
import { adminAuthHeaders } from "@/utils/authHeaders";
import { email, type Errors, maxLength, minLength, required, validate } from "@/utils/validation";
import { Head, router } from "@inertiajs/vue3";
import { onMounted, reactive, ref } from "vue";

interface UserRow {
    id: string;
    name: string;
    email: string;
    phone_number: string;
    created_at: string;
}

interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const users = ref<UserRow[]>([]);
const meta = ref<PageMeta | null>(null);
const page = ref(1);
const perPage = ref(DEFAULT_PAGE_SIZE);
const loading = ref(true);
const loadError = ref<string | null>(null);

const saving = ref(false);
const formError = ref<string | null>(null);
const fieldErrors = ref<Errors>({});
const editingId = ref<string | null>(null);
const showModal = ref(false);
const form = reactive({ name: "", email: "", phone_number: "", password: "" });

function validateForm(): boolean {
    const isEdit = editingId.value !== null;
    fieldErrors.value = validate(form, {
        name: [required("Name is required."), maxLength(255)],
        email: [required("Email is required."), email(), maxLength(255)],
        phone_number: [required("Phone number is required."), maxLength(30)],
        // Password is required on create; optional on edit (blank keeps current).
        password: isEdit
            ? [minLength(6, "Password must be at least 6 characters.")]
            : [required("Password is required."), minLength(6, "Password must be at least 6 characters.")],
    });
    return Object.keys(fieldErrors.value).length === 0;
}

function closeModal() {
    showModal.value = false;
    editingId.value = null;
    form.name = form.email = form.phone_number = form.password = "";
    formError.value = null;
    fieldErrors.value = {};
}

function openCreate() {
    editingId.value = null;
    form.name = form.email = form.phone_number = form.password = "";
    formError.value = null;
    fieldErrors.value = {};
    showModal.value = true;
}

function editUser(u: UserRow) {
    editingId.value = u.id;
    form.name = u.name;
    form.email = u.email;
    form.phone_number = u.phone_number;
    form.password = "";
    formError.value = null;
    fieldErrors.value = {};
    showModal.value = true;
}

async function loadUsers() {
    loading.value = true;
    loadError.value = null;
    try {
        const res = await fetch(`/api/admin/users?page=${page.value}&per_page=${perPage.value}`, {
            headers: adminAuthHeaders(),
        });
        if (res.status === 401) {
            router.visit("/admin/login");
            return;
        }
        if (res.status === 403) {
            loadError.value = "You don't have access to user management.";
            return;
        }
        if (!res.ok) {
            loadError.value = "Could not load users.";
            return;
        }
        const body = await res.json();

        // If we've paged past the end (e.g. after deleting the last row on a
        // page), fall back to the last real page and reload.
        if (body.current_page > body.last_page && body.last_page >= 1) {
            page.value = body.last_page;
            await loadUsers();
            return;
        }

        users.value = body.data ?? [];
        meta.value = {
            current_page: body.current_page,
            last_page: body.last_page,
            per_page: body.per_page,
            total: body.total,
        };
    } catch (e) {
        loadError.value = "Something went wrong loading users.";
    } finally {
        loading.value = false;
    }
}

function changePage(next: number) {
    page.value = next;
    loadUsers();
}

function changePerPage(next: number) {
    perPage.value = next;
    page.value = 1;
    loadUsers();
}

async function submitForm() {
    if (!validateForm()) {
        return;
    }

    saving.value = true;
    formError.value = null;
    const isEdit = editingId.value !== null;
    const url = isEdit ? `/api/admin/users/${editingId.value}` : "/api/admin/users";
    const payload: Record<string, string> = {
        name: form.name,
        email: form.email,
        phone_number: form.phone_number,
    };
    if (form.password) payload.password = form.password;

    try {
        const res = await fetch(url, {
            method: isEdit ? "PUT" : "POST",
            headers: adminAuthHeaders(),
            body: JSON.stringify(payload),
        });
        if (res.status === 401) {
            router.visit("/admin/login");
            return;
        }
        if (res.status === 422) {
            const body = await res.json();
            formError.value = Object.values(body.errors ?? { m: ["Validation failed."] })
                .flat()
                .join(" ");
            return;
        }
        if (!res.ok) {
            formError.value = "Could not save the user.";
            return;
        }
        closeModal();
        await loadUsers();
    } catch (e) {
        formError.value = "Something went wrong saving the user.";
    } finally {
        saving.value = false;
    }
}

async function deleteUser(u: UserRow) {
    if (!confirm(`Delete user "${u.email}"?`)) return;
    try {
        const res = await fetch(`/api/admin/users/${u.id}`, {
            method: "DELETE",
            headers: adminAuthHeaders(),
        });
        if (res.ok) await loadUsers();
    } catch (e) { }
}

useTour("admin-users", [
    {
        element: '[data-tour="add-user"]',
        popover: {
            title: "Add a user",
            description: "Create a new admin or wallet user for this tenant.",
        },
    },
    {
        element: '[data-tour="user-table"]',
        popover: {
            title: "User list",
            description: "All users in this tenant. Edit or remove them from here.",
        },
    },
]);

onMounted(loadUsers);
</script>

<template>

    <Head title="User Management" />

    <AdminShell active="users" eyebrow="Back office" title="User management">
        <template #actions>
            <button class="action-btn primary" data-tour="add-user" @click="openCreate">+ Add user</button>
        </template>

        <section class="panel">
            <h2>Users</h2>
            <p v-if="loadError" class="form-error">{{ loadError }}</p>
            <div v-else-if="!loading && users.length" class="table-wrap" data-tour="user-table">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(u, i) in users" :key="u.id">
                            <td class="mono muted num">
                                {{ ((meta?.current_page ?? 1) - 1) * (meta?.per_page ?? perPage) + i + 1 }}
                            </td>
                            <td class="truncate" :title="u.name">{{ u.name }}</td>
                            <td class="mono truncate" :title="u.email">{{ u.email }}</td>
                            <td class="mono muted">{{ u.phone_number }}</td>
                            <td class="row-actions">
                                <button class="link-btn" @click="editUser(u)">Edit</button>
                                <button class="link-btn danger" @click="deleteUser(u)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else-if="!loading" class="empty-state">No users yet.</p>
            <p v-else class="empty-state">Loading users…</p>

            <Pagination v-if="meta" :current-page="meta.current_page" :last-page="meta.last_page"
                :total="meta.total" :per-page="meta.per_page" @change="changePage"
                @per-page-change="changePerPage" />
        </section>

        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal" role="dialog" aria-modal="true">
                    <div class="modal-header">
                        <h2>{{ editingId ? "Edit user" : "Add user" }}</h2>
                        <button class="modal-close" type="button" aria-label="Close" @click="closeModal">×</button>
                    </div>
                    <form class="user-form" @submit.prevent="submitForm" novalidate>
                        <label>
                            <span>Name</span>
                            <input v-model="form.name" placeholder="Name" :class="{ 'is-invalid': fieldErrors.name }" />
                            <span v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</span>
                        </label>
                        <label>
                            <span>Email</span>
                            <input v-model="form.email" type="email" placeholder="Email"
                                :class="{ 'is-invalid': fieldErrors.email }" />
                            <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</span>
                        </label>
                        <label>
                            <span>Phone</span>
                            <input v-model="form.phone_number" placeholder="Phone"
                                :class="{ 'is-invalid': fieldErrors.phone_number }" />
                            <span v-if="fieldErrors.phone_number" class="field-error">{{ fieldErrors.phone_number }}</span>
                        </label>
                        <label>
                            <span>{{ editingId ? "New password (optional)" : "Password" }}</span>
                            <input v-model="form.password" type="password"
                                :placeholder="editingId ? 'Leave blank to keep current' : 'Password'"
                                :class="{ 'is-invalid': fieldErrors.password }" />
                            <span v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</span>
                        </label>
                        <p v-if="formError" class="form-error">{{ formError }}</p>
                        <div class="form-actions">
                            <button class="action-btn" type="button" @click="closeModal">Cancel</button>
                            <button class="action-btn primary" :disabled="saving" type="submit">
                                {{ saving ? "Saving…" : editingId ? "Update" : "Create" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminShell>
</template>

<style scoped>
.panel {
    background: white;
    border: 1px solid #e4e1d8;
    border-radius: 12px;
    padding: 24px 28px;
    margin-bottom: 24px;
    font-family: "Inter", system-ui, sans-serif;
    color: #1b2430;
}

.panel h2 {
    font-size: 16px;
    margin: 0 0 16px;
}

.user-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.user-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #5b6570;
}

.user-form input {
    padding: 9px 12px;
    border: 1px solid #e4e1d8;
    border-radius: 6px;
    font-size: 13px;
    color: #1b2430;
}

.user-form input:focus {
    outline: none;
    border-color: #2454ff;
    box-shadow: 0 0 0 3px rgba(36, 84, 255, 0.12);
}

.user-form input.is-invalid {
    border-color: #c9432c;
}

.user-form input.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(201, 67, 44, 0.12);
}

.field-error {
    font-size: 12px;
    color: #c9432c;
    margin-top: 4px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(27, 36, 48, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 50;
    font-family: "Inter", system-ui, sans-serif;
}

.modal {
    background: white;
    border-radius: 14px;
    padding: 24px 28px 28px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 24px 60px rgba(27, 36, 48, 0.25);
    color: #1b2430;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.modal-header h2 {
    font-size: 18px;
    margin: 0;
}

.modal-close {
    border: none;
    background: none;
    font-size: 24px;
    line-height: 1;
    color: #5b6570;
    cursor: pointer;
    padding: 0 4px;
}

.modal-close:hover {
    color: #1b2430;
}

.action-btn {
    padding: 10px 18px;
    border-radius: 6px;
    border: 1px solid #e4e1d8;
    background: white;
    cursor: pointer;
}

.action-btn.primary {
    background: #2454ff;
    color: white;
    border-color: #2454ff;
}

.action-btn:disabled {
    opacity: .6;
    cursor: default;
}

.form-error {
    color: #c9432c;
    font-size: 13px;
    margin-top: 12px;
}

.table-wrap {
    overflow-x: auto;
}

.user-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.user-table th {
    text-align: left;
    font-size: 11px;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #5b6570;
    padding: 8px 20px 8px 0;
    border-bottom: 1px solid #e4e1d8;
    white-space: nowrap;
}

.user-table td {
    padding: 12px 20px 12px 0;
    border-bottom: 1px solid #e4e1d8;
    vertical-align: middle;
}

.user-table th:last-child,
.user-table td:last-child {
    padding-right: 0;
}

.truncate {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mono {
    font-family: "JetBrains Mono", monospace;
}

.num {
    width: 1%;
    white-space: nowrap;
    text-align: right;
    padding-right: 16px;
}

.muted {
    color: #5b6570;
}

.row-actions {
    display: flex;
    gap: 12px;
}

.link-btn {
    border: none;
    background: none;
    cursor: pointer;
    color: #2454ff;
    font-size: 13px;
    padding: 0;
}

.link-btn.danger {
    color: #c9432c;
}

.empty-state {
    color: #5b6570;
    font-size: 14px;
    padding: 16px 0;
}
</style>
