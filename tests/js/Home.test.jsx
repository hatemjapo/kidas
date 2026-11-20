import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: Home } = await import('../../resources/js/pages/Home');

function renderAs(role, loading = false) {
    useAuth.mockReturnValue({ user: role === null ? null : { role }, role, loading });

    return render(
        <MemoryRouter>
            <Home />
        </MemoryRouter>
    );
}

test('waits while the session is being checked', () => {
    renderAs(null, true);

    expect(screen.getByText('Checking session…')).toBeInTheDocument();
});

test('offers a supplier their three pages', () => {
    renderAs('supplier');

    expect(screen.getByRole('heading', { name: 'Your slots at the dock', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Book a slot' })).toHaveAttribute('href', '/book');
    expect(screen.getByRole('link', { name: 'My bookings' })).toHaveAttribute('href', '/bookings');
    expect(screen.getByRole('link', { name: 'Profile' })).toHaveAttribute('href', '/profile');
});

test('sends the warehouse to the dock schedule', () => {
    renderAs('staff');

    expect(screen.getByRole('heading', { name: 'The day at the docks', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Dock schedule' })).toHaveAttribute('href', '/schedule');
    expect(screen.queryByRole('link', { name: 'Book for a supplier' })).not.toBeInTheDocument();
});

test('gives the admin the panel as well', () => {
    renderAs('admin');

    expect(screen.getByRole('heading', { name: 'KiDas administration', level: 1 })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Book for a supplier' })).toHaveAttribute('href', '/book');
    expect(screen.getByRole('link', { name: 'Admin panel' })).toHaveAttribute('href', '/admin');
});

test('says what KiDas is to somebody without a role', () => {
    renderAs(null);

    expect(screen.getByRole('heading', { name: 'KiDas', level: 1 })).toBeInTheDocument();
});
