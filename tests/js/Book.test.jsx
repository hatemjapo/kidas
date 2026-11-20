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

const suppliers = [
    { id: 4, company_name: 'Nordfracht GmbH' },
    { id: 5, company_name: 'Aalbert Logistik' },
];

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

async function pickSlot() {
    renderBook();

    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));
    fireEvent.click(await screen.findByRole('button', { name: '06:00' }));
}

test('looks for no slot before the load is named', async () => {
    renderBook();

    await screen.findByLabelText('Order number');

    expect(api.get).not.toHaveBeenCalledWith(expect.stringContaining('/slots'));
    expect(screen.getByText(/then show the free slots/)).toBeInTheDocument();
});

test('asks for the slots of the chosen load', async () => {
    renderBook();

    fireEvent.change(screen.getByLabelText('Pallets'), { target: { value: '24' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/slots?type=inbound&pallets=24'));
});

test('asks for pickups when that is what is wanted', async () => {
    renderBook();

    fireEvent.click(screen.getByRole('button', { name: 'Pickup' }));
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/slots?type=outbound&pallets=10'));
});

test('looks again once the load changed', async () => {
    renderBook();

    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    await screen.findByRole('button', { name: '06:00' });

    fireEvent.change(screen.getByLabelText('Pallets'), { target: { value: '40' } });

    expect(screen.queryByRole('button', { name: '06:00' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeInTheDocument();
});

test('says how long the load blocks a dock', async () => {
    renderBook();

    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));

    expect(await screen.findByText(/Blocks a dock for/)).toHaveTextContent('45 minutes');
});

test('waits for a start time before it lets anything be booked', async () => {
    renderBook();

    await screen.findByLabelText('Order number');

    expect(screen.getByRole('button', { name: 'Book this slot' })).toBeDisabled();
    expect(screen.getByText(/Pick a start time to see your booking/)).toBeInTheDocument();
});

test('summarises the slot that was picked', async () => {
    await pickSlot();

    expect(screen.getByText('Wednesday, 2 June 2027')).toBeInTheDocument();
    expect(screen.getByText('06:00 – 06:45')).toBeInTheDocument();
    expect(screen.getByText('Delivery · 10 pallets')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Book this slot' })).toBeEnabled();
});

test('offers the saved vehicles', async () => {
    await pickSlot();

    expect(await screen.findByRole('option', { name: 'HHAB1234' })).toBeInTheDocument();
});

test('books the slot with a new plate', async () => {
    api.post.mockResolvedValue({
        data: { data: { ...slot, dock_number: 3, pallets: 24, license_plate: 'HHZZ999' } },
    });

    await pickSlot();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.change(screen.getByLabelText('License plate'), { target: { value: 'hh-zz 999' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings', {
            type: 'inbound',
            pallets: 10,
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

    await pickSlot();

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

    expect(screen.queryByLabelText('License plate')).not.toBeInTheDocument();
});

test('names the dock once the slot is booked', async () => {
    api.post.mockResolvedValue({
        data: { data: { ...slot, dock_number: 3, pallets: 24, license_plate: 'HHAB1234' } },
    });

    await pickSlot();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 170 1' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByRole('status')).toHaveTextContent('dock 3');
    expect(screen.getByRole('heading', { name: 'Dock 3', level: 1 })).toBeInTheDocument();
});

test('shows the field errors the server sends back', async () => {
    api.post.mockRejectedValue({
        response: { status: 422, data: { errors: { order_number: ['The order number field is required.'] } } },
    });

    await pickSlot();

    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByText('The order number field is required.')).toBeInTheDocument();
});

test('drops the slot when it has just been taken', async () => {
    api.post.mockRejectedValue({
        response: { status: 409, data: { message: 'This slot has just been taken. Please pick another one.' } },
    });

    await pickSlot();

    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('has just been taken');

    await waitFor(() => expect(screen.getByText(/Pick a start time to see your booking/)).toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Book this slot' })).toBeDisabled();
});

test('says what the deadline for changes is', async () => {
    renderBook();

    expect(await screen.findByText(/up to three hours before the slot/)).toBeInTheDocument();
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

test('asks the admin whom the slot is for before anything else', async () => {
    renderAsAdmin();

    expect(await screen.findByRole('option', { name: 'Nordfracht GmbH' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeDisabled();

    fireEvent.change(screen.getByLabelText('Supplier'), { target: { value: '4' } });

    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeEnabled();
});

test('forgets the slot when the admin picks another supplier', async () => {
    renderAsAdmin();

    fireEvent.change(await screen.findByLabelText('Supplier'), { target: { value: '4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Show free slots' }));
    fireEvent.click(await screen.findByRole('button', { name: '06:00' }));

    fireEvent.change(screen.getByLabelText('Supplier'), { target: { value: '5' } });

    expect(screen.getByText(/Pick a start time to see your booking/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show free slots' })).toBeInTheDocument();
});

test('loads the plates of the chosen supplier', async () => {
    renderAsAdmin();

    fireEvent.change(await screen.findByLabelText('Supplier'), { target: { value: '4' } });

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/suppliers/4/vehicles'));

    expect(api.get).not.toHaveBeenCalledWith('/vehicles');
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

test('prefills the driver phone a supplier keeps in their profile', async () => {
    api.get.mockImplementation((url) => {
        if (url === '/profile') {
            return Promise.resolve({ data: { data: { driver_phone: '+49 170 1234567' } } });
        }

        if (url.startsWith('/vehicles')) {
            return Promise.resolve({ data: { data: vehicles } });
        }

        return Promise.resolve({ data: { data: days, meta: { duration_minutes: 45 } } });
    });

    renderBook();

    expect(await screen.findByLabelText('Driver phone')).toHaveValue('+49 170 1234567');
});

test('lets another driver be typed over the default', async () => {
    api.get.mockImplementation((url) => {
        if (url === '/profile') {
            return Promise.resolve({ data: { data: { driver_phone: '+49 170 1234567' } } });
        }

        if (url.startsWith('/vehicles')) {
            return Promise.resolve({ data: { data: vehicles } });
        }

        return Promise.resolve({ data: { data: days, meta: { duration_minutes: 45 } } });
    });

    api.post.mockResolvedValue({
        data: { data: { ...slot, dock_number: 1, pallets: 10, license_plate: 'HHAB1234' } },
    });

    await pickSlot();

    fireEvent.change(screen.getByLabelText('Order number'), { target: { value: 'PO-4711' } });
    fireEvent.change(screen.getByLabelText('Driver phone'), { target: { value: '+49 171 9999999' } });
    fireEvent.change(screen.getByLabelText('License plate'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Book this slot' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings', expect.objectContaining({ driver_phone: '+49 171 9999999' }))
    );
});

test('asks no profile when the admin books for somebody else', async () => {
    renderAsAdmin();

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/suppliers'));

    expect(api.get).not.toHaveBeenCalledWith('/profile');
});
