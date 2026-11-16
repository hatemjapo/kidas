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

test('sends another role back to the dashboard', function () {
    renderSchedule('supplier');

    expect(screen.getByText('Dashboard')).toBeInTheDocument();
    expect(screen.queryByText('Dock schedule')).not.toBeInTheDocument();
});

test('sends a visitor without a role back as well', function () {
    renderSchedule(null);

    expect(screen.getByText('Dashboard')).toBeInTheDocument();
});
