const navigationByRole = {
    admin: [
        { to: '/', label: 'Dashboard' },
        { to: '/schedule', label: 'Dock schedule' },
        { to: '/bookings', label: 'Bookings' },
    ],
    staff: [
        { to: '/', label: 'Dashboard' },
        { to: '/schedule', label: 'Dock schedule' },
    ],
    supplier: [
        { to: '/', label: 'Dashboard' },
        { to: '/bookings', label: 'My bookings' },
    ],
};

export function navigationFor(role) {
    return navigationByRole[role] ?? [];
}
