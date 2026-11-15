import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: AppLayout } = await import('../../resources/js/layouts/AppLayout');

function renderAs(role, loading = false) {
    useAuth.mockReturnValue({ user: null, role, loading, setUser: vi.fn() });

    return render(
        <MemoryRouter>
            <AppLayout />
        </MemoryRouter>
    );
}

test('shows a supplier their bookings but not the dock schedule', () => {
    renderAs('supplier');

    expect(screen.getByRole('link', { name: 'My bookings' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Dock schedule' })).not.toBeInTheDocument();
});

test('shows warehouse staff the dock schedule but no supplier bookings', () => {
    renderAs('staff');

    expect(screen.getByRole('link', { name: 'Dock schedule' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'My bookings' })).not.toBeInTheDocument();
});

test('shows an admin every area', () => {
    renderAs('admin');

    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Dock schedule' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Bookings' })).toBeInTheDocument();
});

test('shows a guest no navigation at all', () => {
    renderAs(null);

    expect(screen.queryAllByRole('link')).toHaveLength(0);
    expect(screen.getByText('Not signed in')).toBeInTheDocument();
});

test('says so while the session is still being resolved', () => {
    renderAs(null, true);

    expect(screen.getByText('Loading…')).toBeInTheDocument();
});
