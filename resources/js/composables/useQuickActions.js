import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const normalizeUrl = (url) => String(url || '').split('?')[0].split('#')[0];
const isActiveByPrefix = (currentUrl, prefix) => {
    const u = normalizeUrl(currentUrl);
    return u === prefix || u.startsWith(`${prefix}/`);
};

const ADMIN_ROLES = ['admin', 'operator', 'ops'];

export function useQuickActions() {
    const page = usePage();
    const userRole = computed(() => page.props.auth?.user?.role ?? '');
    const isAdmin = computed(() => ADMIN_ROLES.includes(String(userRole.value)));

    const adminActions = computed(() => {
        const currentUrl = page.url || '';
        return [
            { key: 'back-to-app', label: 'Back to app', icon: 'arrow-left', href: route('dashboard'), active: false },
            { key: 'admin-dashboard', label: 'Dashboard', icon: 'dashboard', href: route('admin.dashboard'), active: currentUrl === '/admin' || currentUrl === '/admin/' },
            { key: 'admin-inquiries', label: 'Inquiries', icon: 'inbox', href: route('admin.inquiries.index'), active: isActiveByPrefix(currentUrl, '/admin/inquiries') },
            { key: 'admin-rentals', label: 'Rentals', icon: 'rental', href: route('admin.rentals.index'), active: isActiveByPrefix(currentUrl, '/admin/rentals') },
            { key: 'admin-approvals', label: 'Approvals', icon: 'approval', href: route('admin.approvals'), active: isActiveByPrefix(currentUrl, '/admin/approvals') },
            { key: 'admin-finance', label: 'Finance', icon: 'finance', href: route('admin.finance.index'), active: isActiveByPrefix(currentUrl, '/admin/finance') },
            { key: 'admin-containers', label: 'Containers', icon: 'container', href: route('admin.containers.index'), active: isActiveByPrefix(currentUrl, '/admin/containers') },
            { key: 'admin-ports', label: 'Ports', icon: 'port', href: route('admin.ports.index'), active: isActiveByPrefix(currentUrl, '/admin/ports') },
            { key: 'admin-routes', label: 'Routes', icon: 'route', href: route('admin.routes.index'), active: isActiveByPrefix(currentUrl, '/admin/routes') },
            { key: 'admin-vessels', label: 'Vessels', icon: 'vessel', href: route('admin.vessels.index'), active: isActiveByPrefix(currentUrl, '/admin/vessels') },
            { key: 'admin-owners', label: 'Owners', icon: 'owner', href: route('admin.owners.index'), active: isActiveByPrefix(currentUrl, '/admin/owners') },
            { key: 'admin-users', label: 'Users', icon: 'users', href: route('admin.users.index'), active: isActiveByPrefix(currentUrl, '/admin/users') },
            { key: 'admin-activity-logs', label: 'Activity logs', icon: 'activity', href: route('admin.activity-logs.index'), active: isActiveByPrefix(currentUrl, '/admin/activity-logs') },
            { key: 'admin-request-logs', label: 'Request logs', icon: 'activity', href: route('admin.request-logs.index'), active: isActiveByPrefix(currentUrl, '/admin/request-logs') },
        ];
    });

    const userActions = computed(() => {
        const currentUrl = page.url || '';
        const actions = [
            { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', href: route('dashboard'), active: isActiveByPrefix(currentUrl, '/dashboard') },
            { key: 'finance-monitoring', label: 'Finance', icon: 'finance', href: route('finance.monitoring'), active: isActiveByPrefix(currentUrl, '/finance-monitoring') },
            { key: 'rental', label: 'Rental', icon: 'rental', href: route('rentals.center'), active: isActiveByPrefix(currentUrl, '/rentals-center') },
            { key: 'rental-request', label: 'Request Rental', icon: 'rental-request', href: route('rentals.request.create'), active: isActiveByPrefix(currentUrl, '/rentals/request') },
            { key: 'support', label: 'Support', icon: 'support', href: route('contact'), active: isActiveByPrefix(currentUrl, '/contact') },
        ];
        if (isAdmin.value) {
            actions.push({ key: 'admin', label: 'Admin', icon: 'admin', href: route('admin.dashboard'), active: isActiveByPrefix(currentUrl, '/admin') });
        }
        return actions;
    });

    const quickActions = computed(() => {
        const currentUrl = page.url || '';
        if (currentUrl.startsWith('/admin')) {
            return adminActions.value;
        }
        return userActions.value;
    });

    return {
        quickActions,
        isAdmin,
    };
}
