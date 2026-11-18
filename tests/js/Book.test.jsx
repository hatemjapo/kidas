import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn() },
    csrf: vi.fn(),
}));

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { useAuth } = await import('../../resources/js/auth/AuthContext');
const { default: Book } = await import('../../resources/js/pages/Book');

const slot = { starts_at: '2027-06-02T04:00:00Z', ends_at: '2027-06-02T04:45:00Z', shift: 'early' };

const days = [{ date: '2027-06-02', slots: [slot] }];

const vehicles = [{ id: 3, license_plate: 'HHAB1234' }];

const suppliers = [{ id: 4, company_name: 'Nordfracht GmbH' }];

beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});

    useAuth.mockReturnValue({ role: 'supplier' });

    api.get.mockImplementation((url) => {
        if (url.startsWith('/suppliers') && url.endsWith('/vehicles')) {
            return Promise.resolve({ data: { data: vehicles } });
        }

        if (url.startsWith('/suppliers')) {
            return Promise.resolve({ data: { data: suppliers } });
        }

        if (url.startsWith('/vehicles')) {
            return Promise.resolve({ data: { data: vehicles } });
        }

        return Promise.resolve({ data: { data: days, meta: { duration_minutes: 45 } } });
    });
});

function renderBook() {
    render(
        <MemoryRouter>
            <Book />
        </MemoryRouter>
    );
}

async function walkToDetails() {
    renderBook();

    fireEvent.change(screen.getByLabelText('Pallets'), { target: { value: '24' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    fireEvent.click(await screen.findByRole('button', { name: '06:00' }));

    await screen.findByLabelText('Order number');
}

test('asks for the slots of the chosen load', async () => {
    renderBook();

    fireEvent.change(screen.getByLabelText('Pallets'), { target: { value: '24' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/slots?type=inbound&pallets=24'));
});

test('asks for pickups when that is what is wanted', async () => {
    renderBook();

    fireEvent.change(screen.getByLabelText('I want to'), { target: { value: 'outbound' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/slots?type=outbound&pallets=10'));
});

test('shows the chosen slot on the detail step', async () => {
    await walkToDetails();

    expect(screen.getByText(/Wed, 02\/06\/2027, 06:00 – 06:45/)).toBeInTheDocument();
});

test('offers the saved vehicles', async () => {
    await walkToDetails();

    expect(await screen.findByRole('option', { name: 'HHAB1234' })).toBeInTheDocument();
});

test('books the slot with a new plate', async () => {
    api.post.mockResolvedValue({
        data: { data: { ...slot, dock_number: 3, pallets: 24, license_plate: 'HHZZ999' } },
    });

    await walkToDetails();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.change(screen.getByLabelText('License plate'), { target: { value: 'hh-zz 999' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings', {
            type: 'inbound',
            pallets: 24,
            starts_at: '2027-06-02T04:00:00Z',
            order_number: 'PO-4711',
            driver_phone: '+49 170 1',
            vehicle_id: null,
            license_plate: 'hh-zz 999',
        })
    );

    expect(csrf).toHaveBeenCalled();
});

test('books the slot with a saved vehicle', async () => {
    api.post.mockResolvedValue({ data: { data: { ...slot, dock_number: 1, pallets: 24, license_plate: 'HHAB1234' } } });

    await walkToDetails();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.change(screen.getByLabelText('Vehicle'), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith(
            '/bookings',
            expect.objectContaining({ vehicle_id: 3, license_plate: null })
        )
    );
});

test('names the dock once the slot is booked', async () => {
    api.post.mockResolvedValue({
        data: { data: { ...slot, dock_number: 3, pallets: 24, license_plate: 'HHAB1234' } },
    });

    await walkToDetails();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByRole('status')).toHaveTextContent('dock 3');
    expect(screen.getByText('Slot booked')).toBeInTheDocument();
});

test('shows the field errors the server sends back', async () => {
    api.post.mockRejectedValue({
        response: { status: 422, data: { errors: { order_number: ['The order number field is required.'] } } },
    });

    await walkToDetails();

    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByText('The order number field is required.')).toBeInTheDocument();
});

test('sends the supplier back to the slots when the slot is gone', async () => {
    api.post.mockRejectedValue({
        response: { status: 409, data: { message: 'This slot has just been taken. Please pick another one.' } },
    });

    await walkToDetails();

    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('has just been taken');

    await waitFor(() => expect(screen.getByRole('button', { name: '06:00' })).toBeInTheDocument());
    expect(screen.queryByLabelText('Order number')).not.toBeInTheDocument();
});

test('goes back to the slots on request', async () => {
    await walkToDetails();

    fireEvent.click(screen.getByRole('button', { name: 'Pick another slot' }));

    expect(await screen.findByRole('button', { name: '06:00' })).toBeInTheDocument();
});

function renderAsAdmin() {
    useAuth.mockReturnValue({ role: 'admin' });

    render(
        <MemoryRouter>
            <Book />
        </MemoryRouter>
    );
}

test('leaves the supplier out of a supplier booking', async () => {
    renderBook();

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/vehicles'));

    expect(screen.queryByLabelText('Supplier')).not.toBeInTheDocument();
});

test('asks the admin whom the slot is for', async () => {
    renderAsAdmin();

    expect(await screen.findByRole('option', { name: 'Nordfracht GmbH' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeDisabled();
});

test('loads the plates of the chosen supplier', async () => {
    renderAsAdmin();

    fireEvent.change(await screen.findByLabelText('Supplier'), { target: { value: '4' } });

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/suppliers/4/vehicles'));

    expect(api.get).not.toHaveBeenCalledWith('/vehicles');
    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeEnabled();
});

test('books for the chosen supplier', async () => {
    api.post.mockResolvedValue({
        data: { data: { id: 9, dock_number: 1, starts_at: slot.starts_at, ends_at: slot.ends_at, pallets: 10, license_plate: 'HHAB1234' } },
    });

    renderAsAdmin();

    fireEvent.change(await screen.findByLabelText('Supplier'), { target: { value: '4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));
    fireEvent.click(await screen.findByRole('button', { name: '06:00' }));

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.change(await screen.findByLabelText('License plate'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/admin/bookings', {
            supplier_id: 4,
            type: 'inbound',
            pallets: 10,
            starts_at: slot.starts_at,
            order_number: 'PO-4711',
            driver_phone: '+49 170 1',
            vehicle_id: null,
            license_plate: 'HHAB1234',
        })
    );
});

test('shows that a supplier is missing', async () => {
    api.post.mockRejectedValue({
        response: { status: 422, data: { errors: { supplier_id: ['This supplier is not approved.'] } } },
    });

    renderAsAdmin();

    fireEvent.change(await screen.findByLabelText('Supplier'), { target: { value: '4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));
    fireEvent.click(await screen.findByRole('button', { name: '06:00' }));

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.change(await screen.findByLabelText('License plate'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByText('This supplier is not approved.')).toBeInTheDocument();
});
