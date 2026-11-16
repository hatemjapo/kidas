import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: RequireAuth } = await import('../../resources/js/auth/RequireAuth');

function renderAt(session) {
    useAuth.mockReturnValue({ setUser: vi.fn(), login: vi.fn(), logout: vi.fn(), ...session });

    return render(
        <MemoryRouter initialEntries={['/bookings']}>
            <Routes>
                <Route path="/login" element={<p>Sign in form</p>} />
                <Route element={<RequireAuth />}>
                    <Route path="/bookings" element={<p>Booking list</p>} />
                </Route>
            </Routes>
        </MemoryRouter>
    );
}

test('sends a guest to the login form', () => {
    renderAt({ user: null, role: null, loading: false });

    expect(screen.getByText('Sign in form')).toBeInTheDocument();
    expect(screen.queryByText('Booking list')).not.toBeInTheDocument();
});

test('waits while the session is still being resolved', () => {
    renderAt({ user: null, role: null, loading: true });

    expect(screen.getByText('Loading…')).toBeInTheDocument();
    expect(screen.queryByText('Sign in form')).not.toBeInTheDocument();
});

test('lets a signed in user through', () => {
    renderAt({ user: { role: 'supplier' }, role: 'supplier', loading: false });

    expect(screen.getByText('Booking list')).toBeInTheDocument();
});
