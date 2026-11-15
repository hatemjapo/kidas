import { render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { AuthProvider, useAuth } = await import('../../resources/js/auth/AuthContext');

function Probe() {
    const { role, loading } = useAuth();

    return <span>{loading ? 'loading' : (role ?? 'guest')}</span>;
}

beforeEach(() => {
    api.get.mockReset();
});

test('takes the role from the session endpoint', async () => {
    api.get.mockResolvedValue({ data: { data: { role: 'admin' } } });

    render(<AuthProvider><Probe /></AuthProvider>);

    await waitFor(() => expect(screen.getByText('admin')).toBeInTheDocument());
});

test('reads an unwrapped response as well', async () => {
    api.get.mockResolvedValue({ data: { role: 'staff' } });

    render(<AuthProvider><Probe /></AuthProvider>);

    await waitFor(() => expect(screen.getByText('staff')).toBeInTheDocument());
});

test('treats a rejected session request as a guest', async () => {
    api.get.mockRejectedValue(new Error('Unauthenticated'));

    render(<AuthProvider><Probe /></AuthProvider>);

    await waitFor(() => expect(screen.getByText('guest')).toBeInTheDocument());
});

test('refuses to be used outside the provider', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => render(<Probe />)).toThrow('useAuth must be used inside an AuthProvider');

    console.error.mockRestore();
});
