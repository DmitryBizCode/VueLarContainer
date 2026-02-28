import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const isActiveByPrefix = (currentUrl, prefix) => currentUrl === prefix || currentUrl.startsWith(`${prefix}/`);

export function useQuickActions() {
    const page = usePage();

    const quickActions = computed(() => {
        const currentUrl = page.url || '';

        return [
            {
                key: 'dashboard',
                label: 'Dashboard',
                icon: 'dashboard',
                href: route('dashboard'),
                active: isActiveByPrefix(currentUrl, '/dashboard'),
            },
            {
                key: 'profile',
                label: 'Profile',
                icon: 'profile',
                href: route('profile.edit'),
                active: isActiveByPrefix(currentUrl, '/profile'),
            },
            {
                key: 'rental',
                label: 'Rental',
                icon: 'rental',
                href: route('services'),
                active: isActiveByPrefix(currentUrl, '/services'),
            },
            {
                key: 'support',
                label: 'Support',
                icon: 'support',
                href: route('contact'),
                active: isActiveByPrefix(currentUrl, '/contact'),
            },
        ];
    });

    return {
        quickActions,
    };
}
