import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: AppLayout } = await import('../../resources/js/layouts/AppLayout');

const logout = vi.fn();

function renderAs(role, loading = false) {
    const user = role === null ? null : { role };

    useAuth.mockReturnValue({ user, role, loading, setUser: vi.fn(), login: vi.fn(), logout });

    return render(
        <MemoryRouter>
            <AppLayout />
        </MemoryRouter>
    );
}

beforeEach(() => {
    logout.mockReset();
});

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

test('offers a signed in user the way out', () => {
    renderAs('staff');

    fireEvent.click(screen.getByRole('button', { name: 'Sign out' }));

    expect(logout).toHaveBeenCalled();
});

test('offers a guest no way out', () => {
    renderAs(null);

    expect(screen.queryByRole('button', { name: 'Sign out' })).not.toBeInTheDocument();
});
