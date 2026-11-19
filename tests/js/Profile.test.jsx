import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), patch: vi.fn(), post: vi.fn(), delete: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { default: Profile } = await import('../../resources/js/pages/Profile');

const profile = {
    name: 'Lena Supplier',
    email: 'lena@nordfracht.test',
    company_name: 'Nordfracht GmbH',
    company_phone: '+49 40 1234567',
    status: 'approved',
};

beforeEach(() => {
    api.get.mockReset();
    api.patch.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
    api.get.mockImplementation((url) =>
        url === '/vehicles'
            ? Promise.resolve({ data: { data: [] } })
            : Promise.resolve({ data: { data: profile } })
    );
});

async function renderProfile(answer = profile) {
    api.get.mockImplementation((url) =>
        url === '/vehicles'
            ? Promise.resolve({ data: { data: [] } })
            : Promise.resolve({ data: { data: answer } })
    );

    render(<Profile />);

    await waitFor(() => expect(screen.getByLabelText('Company')).toHaveValue('Nordfracht GmbH'));
}

test('fills the form from the api', async () => {
    await renderProfile();

    expect(screen.getByLabelText('Company phone')).toHaveValue('+49 40 1234567');
    expect(screen.getByLabelText('Contact name')).toHaveValue('Lena Supplier');
});

test('shows the email address without letting it be changed', async () => {
    await renderProfile();

    expect(screen.getByText(/lena@nordfracht.test/)).toBeInTheDocument();
    expect(screen.queryByLabelText('Email')).not.toBeInTheDocument();
});

test('sends the changed values behind a csrf cookie', async () => {
    api.patch.mockResolvedValue({ data: { data: profile } });

    await renderProfile();

    fireEvent.change(screen.getByLabelText('Company phone'), {
        target: { value: '+49 40 7654321' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
        expect(api.patch).toHaveBeenCalledWith('/profile', {
            name: 'Lena Supplier',
            company_name: 'Nordfracht GmbH',
            company_phone: '+49 40 7654321',
            driver_phone: null,
        })
    );

    expect(csrf).toHaveBeenCalled();
    expect(await screen.findByRole('status')).toHaveTextContent('has been saved');
});

test('shows the field error the server sends back', async () => {
    api.patch.mockRejectedValue({
        response: { status: 422, data: { errors: { company_name: ['The company field is required.'] } } },
    });

    await renderProfile();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('The company field is required.')).toBeInTheDocument();
});

test('explains a failure that is not about a field', async () => {
    api.patch.mockRejectedValue({ response: { status: 500, data: {} } });

    await renderProfile();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Saving failed');
});

test('says so when the profile cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<Profile />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});

test('keeps a default driver phone for the next booking', async () => {
    api.patch.mockResolvedValue({ data: { data: profile } });

    await renderProfile();

    const field = screen.getByLabelText('Default driver phone');

    expect(field).not.toBeRequired();
    expect(screen.getByText(/prefilled with this number/)).toBeInTheDocument();

    fireEvent.change(field, { target: { value: '+49 170 1234567' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
        expect(api.patch).toHaveBeenCalledWith(
            '/profile',
            expect.objectContaining({ driver_phone: '+49 170 1234567' })
        )
    );
});

test('shows the number that was kept', async () => {
    await renderProfile({ ...profile, driver_phone: '+49 170 1234567' });

    expect(screen.getByLabelText('Default driver phone')).toHaveValue('+49 170 1234567');
});
