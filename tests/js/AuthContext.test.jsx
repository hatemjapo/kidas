import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { AuthProvider, useAuth } = await import('../../resources/js/auth/AuthContext');

function Probe() {
    const { role, loading } = useAuth();

    return <span>{loading ? 'loading' : (role ?? 'guest')}</span>;
}

beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
});

function SessionProbe() {
    const { user, login, logout } = useAuth();

    return (
        <div>
            <span>{user === null ? 'guest' : user.email}</span>
            <button
                type="button"
                onClick={() => login({ email: 'rampe@kidas.test', password: 'password' })}
            >
                sign in
            </button>
            <button type="button" onClick={logout}>
                sign out
            </button>
        </div>
    );
}

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

test('fetches a csrf cookie before posting the credentials', async () => {
    api.get.mockRejectedValue(new Error('Unauthenticated'));
    api.post.mockResolvedValue({ data: { data: { email: 'rampe@kidas.test', role: 'staff' } } });

    render(<AuthProvider><SessionProbe /></AuthProvider>);
    await waitFor(() => expect(screen.getByText('guest')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'sign in' }));

    await waitFor(() => expect(screen.getByText('rampe@kidas.test')).toBeInTheDocument());

    expect(csrf).toHaveBeenCalled();
    expect(api.post).toHaveBeenCalledWith('/login', {
        email: 'rampe@kidas.test',
        password: 'password',
    });
});

test('keeps the user of an unwrapped login response', async () => {
    api.get.mockRejectedValue(new Error('Unauthenticated'));
    api.post.mockResolvedValue({ data: { email: 'rampe@kidas.test', role: 'admin' } });

    render(<AuthProvider><SessionProbe /></AuthProvider>);
    await waitFor(() => expect(screen.getByText('guest')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'sign in' }));

    await waitFor(() => expect(screen.getByText('rampe@kidas.test')).toBeInTheDocument());
});

test('drops the user when signing out', async () => {
    api.get.mockResolvedValue({ data: { data: { email: 'rampe@kidas.test', role: 'admin' } } });
    api.post.mockResolvedValue({ data: null });

    render(<AuthProvider><SessionProbe /></AuthProvider>);
    await waitFor(() => expect(screen.getByText('rampe@kidas.test')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'sign out' }));

    await waitFor(() => expect(screen.getByText('guest')).toBeInTheDocument());

    expect(api.post).toHaveBeenCalledWith('/logout');
});
