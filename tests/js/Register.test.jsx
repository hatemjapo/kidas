import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { post: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { default: Register } = await import('../../resources/js/pages/Register');

beforeEach(() => {
    api.post.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
});

function renderRegister() {
    return render(
        <MemoryRouter>
            <Register />
        </MemoryRouter>
    );
}

function fillIn() {
    fireEvent.change(screen.getByLabelText('Company'), {
        target: { value: 'Nordfracht GmbH' },
    });
    fireEvent.change(screen.getByLabelText('Company phone'), {
        target: { value: '+49 40 1234567' },
    });
    fireEvent.change(screen.getByLabelText('Your name'), { target: { value: 'Lena Supplier' } });
    fireEvent.change(screen.getByLabelText('Email'), {
        target: { value: 'lena@nordfracht.test' },
    });
    fireEvent.change(screen.getByLabelText('Password'), {
        target: { value: 'open-sesame-42' },
    });
    fireEvent.change(screen.getByLabelText('Repeat password'), {
        target: { value: 'open-sesame-42' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Register' }));
}

test('sends the whole form to the api behind a csrf cookie', async () => {
    api.post.mockResolvedValue({ data: { data: { role: 'supplier' } } });

    renderRegister();
    fillIn();

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/register', {
            company_name: 'Nordfracht GmbH',
            company_phone: '+49 40 1234567',
            name: 'Lena Supplier',
            email: 'lena@nordfracht.test',
            password: 'open-sesame-42',
            password_confirmation: 'open-sesame-42',
        })
    );

    expect(csrf).toHaveBeenCalled();
});

test('says that the registration is being reviewed', async () => {
    api.post.mockResolvedValue({ data: { data: { role: 'supplier' } } });

    renderRegister();
    fillIn();

    expect(await screen.findByRole('status')).toHaveTextContent(
        'We are reviewing your registration.'
    );
    expect(screen.queryByRole('button', { name: 'Register' })).not.toBeInTheDocument();
});

test('shows the field errors the server sends back', async () => {
    api.post.mockRejectedValue({
        response: {
            status: 422,
            data: { errors: { email: ['The email has already been taken.'] } },
        },
    });

    renderRegister();
    fillIn();

    expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Register' })).toBeEnabled();
});

test('explains a throttled registration', async () => {
    api.post.mockRejectedValue({ response: { status: 429, data: {} } });

    renderRegister();
    fillIn();

    expect(await screen.findByRole('alert')).toHaveTextContent(
        'Too many attempts. Please try again in a minute.'
    );
});

test('offers the way back to the login form', () => {
    renderRegister();

    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
});

test('marks every field as required', () => {
    renderRegister();

    ['Company', 'Company phone', 'Your name', 'Email', 'Password', 'Repeat password'].forEach(
        (label) => expect(screen.getByLabelText(label)).toBeRequired()
    );
});
