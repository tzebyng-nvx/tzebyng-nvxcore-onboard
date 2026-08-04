import { createInertiaApp, router } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Derive tenant from subdomain, e.g. acme.merchant-wallet.test -> "acme"
function currentTenant(): string | null {
    const host = window.location.hostname; // "acme.merchant-wallet.test"
    const parts = host.split('.');

    // Adjust this rule to match your actual domain shape — this assumes
    // "merchant-wallet.test" is the central domain and anything with an
    // extra leading segment is a tenant subdomain.
    return parts.length > 2 ? parts[0] : null;
}

// Runs before every Inertia visit (page navigation, form.post, etc.)
router.on('before', (event) => {
    const tenant = currentTenant();
    if (tenant) {
        event.detail.visit.headers['X-Tenant'] = tenant;
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});
