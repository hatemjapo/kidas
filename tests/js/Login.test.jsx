import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: Login } = await import('../../resources/js/pages/Login');

function renderLogin(login = vi.fn()) {
    useAuth.mockReturnValue({ user: null, role: null, loading: false, login, logout: vi.fn() });

    return render(
        <MemoryRouter>
            <Login />
        </MemoryRouter>
    );
}

function fillIn(email, password) {
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: email } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: password } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
}

function rejectWith(status, data) {
    return vi.fn().mockRejectedValue({ response: { status, data } });
}

test('hands the typed credentials to the session', async () => {
    const login = vi.fn().mockResolvedValue({ role: 'staff' });

    renderLogin(login);
    fillIn('rampe@kidas.test', 'password');

    await waitFor(() =>
        expect(login).toHaveBeenCalledWith({
            email: 'rampe@kidas.test',
            password: 'password',
        })
    );
});

test('shows the field error the server sends back', async () => {
    const login = rejectWith(422, {
        errors: { email: ['These credentials do not match our records.'] },
    });

    renderLogin(login);
    fillIn('rampe@kidas.test', 'wrong-password');

    expect(
        await screen.findByText('These credentials do not match our records.')
    ).toBeInTheDocument();
});

test('shows a missing password as a field error', async () => {
    const login = rejectWith(422, {
        errors: { password: ['The password field is required.'] },
    });

    renderLogin(login);
    fillIn('rampe@kidas.test', '');

    expect(await screen.findByText('The password field is required.')).toBeInTheDocument();
});

test('explains a throttled login', async () => {
    const login = rejectWith(429, {});

    renderLogin(login);
    fillIn('rampe@kidas.test', 'wrong-password');

    expect(
        await screen.findByText('Too many attempts. Please try again in a minute.')
    ).toBeInTheDocument();
});

test('stays usable after a failed attempt', async () => {
    const login = rejectWith(500, {});

    renderLogin(login);
    fillIn('rampe@kidas.test', 'password');

    expect(await screen.findByText('Sign in failed. Please try again.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeEnabled();
});

test('sends a signed in visitor away from the login form', () => {
    useAuth.mockReturnValue({
        user: { role: 'admin' },
        role: 'admin',
        loading: false,
        login: vi.fn(),
        logout: vi.fn(),
    });

    render(
        <MemoryRouter>
            <Login />
        </MemoryRouter>
    );

    expect(screen.queryByRole('button', { name: 'Sign in' })).not.toBeInTheDocument();
});

test('shows why a supplier without approval is kept out', async () => {
    const login = rejectWith(403, {
        message: 'Your registration is still being reviewed. You will receive an email as soon as it is approved.',
    });

    renderLogin(login);
    fillIn('lena@nordfracht.test', 'open-sesame-42');

    expect(await screen.findByRole('alert')).toHaveTextContent(
        'Your registration is still being reviewed.'
    );
});

test('points new suppliers at the registration form', () => {
    renderLogin(vi.fn());

    expect(screen.getByRole('link', { name: 'Register as a supplier' })).toHaveAttribute(
        'href',
        '/register'
    );
});

test('marks both fields as required', () => {
    renderLogin(vi.fn());

    expect(screen.getByLabelText('Email')).toBeRequired();
    expect(screen.getByLabelText('Password')).toBeRequired();
});

test('explains what KiDas is beside the form', () => {
    renderLogin();

    expect(screen.getByRole('heading', { name: /Every lorry at the right dock/ })).toBeInTheDocument();
    expect(screen.getByText(/the warehouse sees the day per dock/)).toBeInTheDocument();
});

test('says that the warehouse approves a new company', () => {
    renderLogin();

    expect(screen.getByText(/checked by the warehouse/)).toBeInTheDocument();
});

test('offers the way to the registration', () => {
    renderLogin();

    expect(screen.getByRole('link', { name: 'Register as a supplier' })).toHaveAttribute('href', '/register');
});
