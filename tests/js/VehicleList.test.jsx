import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { default: VehicleList } = await import('../../resources/js/components/VehicleList');

beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
    api.delete.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
});

async function renderWith(vehicles) {
    api.get.mockResolvedValue({ data: { data: vehicles } });

    render(<VehicleList />);

    await waitFor(() => expect(screen.queryByText('Loading vehicles…')).not.toBeInTheDocument());
}

test('lists the saved plates', async () => {
    await renderWith([
        { id: 1, license_plate: 'HHAB1234' },
        { id: 2, license_plate: 'HHCD5678' },
    ]);

    expect(screen.getByText('HHAB1234')).toBeInTheDocument();
    expect(screen.getByText('HHCD5678')).toBeInTheDocument();
});

test('says so when nothing is saved yet', async () => {
    await renderWith([]);

    expect(screen.getByText('No vehicles saved yet.')).toBeInTheDocument();
});

test('adds a plate and shows it afterwards', async () => {
    await renderWith([]);

    api.post.mockResolvedValue({ data: { data: { id: 7, license_plate: 'HHAB1234' } } });

    fireEvent.change(screen.getByLabelText('License plate'), { target: { value: 'hh-ab 1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/vehicles', { license_plate: 'hh-ab 1234' })
    );

    expect(csrf).toHaveBeenCalled();
    expect(await screen.findByText('HHAB1234')).toBeInTheDocument();
    expect(screen.getByLabelText('License plate')).toHaveValue('');
});

test('shows why a plate was refused', async () => {
    await renderWith([]);

    api.post.mockRejectedValue({
        response: {
            status: 422,
            data: { errors: { license_plate: ['This license plate is already saved.'] } },
        },
    });

    fireEvent.change(screen.getByLabelText('License plate'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('already saved');
});

test('removes a plate', async () => {
    await renderWith([{ id: 7, license_plate: 'HHAB1234' }]);

    api.delete.mockResolvedValue({});

    fireEvent.click(screen.getByRole('button', { name: 'Remove HHAB1234' }));

    await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/vehicles/7'));

    await waitFor(() => expect(screen.queryByText('HHAB1234')).not.toBeInTheDocument());
});

test('keeps the plate when removing fails', async () => {
    await renderWith([{ id: 7, license_plate: 'HHAB1234' }]);

    api.delete.mockRejectedValue(new Error('nope'));

    fireEvent.click(screen.getByRole('button', { name: 'Remove HHAB1234' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be removed');
    expect(screen.getByText('HHAB1234')).toBeInTheDocument();
});

test('says so when the vehicles cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<VehicleList />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});
