import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: RequireRole } = await import('../../resources/js/auth/RequireRole');

function renderSchedule(role) {
    useAuth.mockReturnValue({ user: role === null ? null : { role }, role, loading: false });

    return render(
        <MemoryRouter initialEntries={['/schedule']}>
            <Routes>
                <Route path="/" element={<p>Dashboard</p>} />
                <Route element={<RequireRole allow={['admin', 'staff']} />}>
                    <Route path="/schedule" element={<p>Dock schedule</p>} />
                </Route>
            </Routes>
        </MemoryRouter>
    );
}

test('lets a named role see the page', function () {
    renderSchedule('staff');

    expect(screen.getByText('Dock schedule')).toBeInTheDocument();
});

test('lets an admin see the page as well', function () {
    renderSchedule('admin');

    expect(screen.getByText('Dock schedule')).toBeInTheDocument();
});

test('tells another role that the page is not theirs', function () {
    renderSchedule('supplier');

    expect(screen.getByRole('heading', { name: 'This page is not yours to open', level: 1 })).toBeInTheDocument();
    expect(screen.getByText(/signed in as a supplier/)).toBeInTheDocument();
    expect(screen.queryByText('Dock schedule')).not.toBeInTheDocument();
});

test('offers the way back to the dashboard', function () {
    renderSchedule('supplier');

    expect(screen.getByRole('link', { name: 'Back to the dashboard' })).toHaveAttribute('href', '/');
});

test('turns a visitor without a role away as well', function () {
    renderSchedule(null);

    expect(screen.getByRole('heading', { name: 'This page is not yours to open', level: 1 })).toBeInTheDocument();
    expect(screen.queryByText('Dock schedule')).not.toBeInTheDocument();
});
