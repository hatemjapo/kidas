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

function renderWith(user, path = '/') {
    useAuth.mockReturnValue({ user, role: user?.role ?? null, loading: false, setUser: vi.fn(), login: vi.fn(), logout });

    return render(
        <MemoryRouter initialEntries={[path]}>
            <AppLayout />
        </MemoryRouter>
    );
}

test('carries the logo and the wordmark', () => {
    renderAs('staff');

    expect(screen.getByText('KiDas')).toBeInTheDocument();
});

test('marks the page one is on', () => {
    renderWith({ role: 'staff', email: 'staff@kidas.test' }, '/schedule');

    const active = screen.getByRole('link', { name: 'Dock schedule' });

    expect(active).toHaveAttribute('aria-current', 'page');
    expect(active).toHaveClass('bg-brand-50');
    expect(active).toHaveClass('text-brand-800');

    expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute('aria-current');
});

test('names the company of a supplier and their address', () => {
    renderWith({ role: 'supplier', email: 'nordfracht@kidas.test', company_name: 'Nordfracht GmbH' });

    expect(screen.getByText('Nordfracht GmbH')).toBeInTheDocument();
    expect(screen.getByText('nordfracht@kidas.test')).toBeInTheDocument();
});

test('names the part of the house for the warehouse and the admin', () => {
    renderWith({ role: 'staff', email: 'staff@kidas.test' });

    expect(screen.getByText('Warehouse')).toBeInTheDocument();

    renderWith({ role: 'admin', email: 'admin@kidas.test' });

    expect(screen.getByText('Administration')).toBeInTheDocument();
});

test('gives the schedule more room than the other pages', () => {
    const { container: wide } = renderWith({ role: 'staff', email: 'a@b.test' }, '/schedule');

    expect(wide.querySelector('main')).toHaveClass('max-w-[1360px]');

    const { container: normal } = renderWith({ role: 'staff', email: 'a@b.test' }, '/');

    expect(normal.querySelector('main')).toHaveClass('max-w-[1240px]');
});

test('keeps the navigation behind a menu button on a small screen', () => {
    renderWith({ role: 'supplier', email: 'a@b.test', company_name: 'Nordfracht GmbH' });

    expect(screen.getByRole('navigation', { name: 'Main' })).toHaveClass('hidden');

    fireEvent.click(screen.getByRole('button', { name: 'Open the menu' }));

    const menu = screen.getByRole('navigation', { name: 'Main, small screen' });

    expect(menu).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Close the menu' })).toHaveAttribute('aria-expanded', 'true');
});

test('closes the menu once a page was chosen', () => {
    renderWith({ role: 'supplier', email: 'a@b.test', company_name: 'Nordfracht GmbH' });

    fireEvent.click(screen.getByRole('button', { name: 'Open the menu' }));
    fireEvent.click(screen.getAllByRole('link', { name: 'My bookings' })[1]);

    expect(screen.queryByRole('navigation', { name: 'Main, small screen' })).not.toBeInTheDocument();
});

test('offers no menu button to somebody who is not signed in', () => {
    renderWith(null);

    expect(screen.queryByRole('button', { name: 'Open the menu' })).not.toBeInTheDocument();
    expect(screen.getByText('Not signed in')).toBeInTheDocument();
});

test('signs out from the small screen as well', () => {
    renderWith({ role: 'supplier', email: 'a@b.test', company_name: 'Nordfracht GmbH' });

    fireEvent.click(screen.getByRole('button', { name: 'Open the menu' }));
    fireEvent.click(screen.getAllByRole('button', { name: 'Sign out' })[1]);

    expect(logout).toHaveBeenCalled();
});
