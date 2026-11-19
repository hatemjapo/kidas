import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { default: Bookings } = await import('../../resources/js/pages/Bookings');

const booking = {
    id: 7,
    type: 'inbound',
    status: 'booked',
    starts_at: '2027-06-02T04:00:00Z',
    ends_at: '2027-06-02T04:15:00Z',
    pallets: 10,
    order_number: 'PO-4711',
    license_plate: 'HHAB1234',
    driver_phone: '+49 170 1234567',
    dock_number: 3,
};

const detail = {
    ...booking,
    history: [
        { from_status: null, to_status: 'booked', by: 'Lena Supplier', note: null, created_at: '2027-06-01T08:00:00Z' },
    ],
};

beforeEach(() => {
    api.get.mockReset();
    api.post.mockReset();
    api.patch.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
});

function answerWith(bookings, meta = { current_page: 1, last_page: 1, total: bookings.length }, shown = detail) {
    api.get.mockImplementation((url) => {
        if (url.startsWith('/bookings?')) {
            return Promise.resolve({ data: { data: bookings, meta } });
        }

        if (url.startsWith('/slots')) {
            return Promise.resolve({
                data: {
                    data: [
                        {
                            date: '2027-06-04',
                            slots: [
                                {
                                    starts_at: '2027-06-04T08:00:00Z',
                                    ends_at: '2027-06-04T08:15:00Z',
                                    shift: 'early',
                                },
                            ],
                        },
                    ],
                    meta: { duration_minutes: 15 },
                },
            });
        }

        return Promise.resolve({ data: { data: shown } });
    });
}

async function openDetail(shown = detail) {
    answerWith([booking], { current_page: 1, last_page: 1, total: 1 }, shown);

    render(<Bookings />);

    fireEvent.click(await screen.findByRole('button', { name: /02\/06\/2027/ }));

    await screen.findByText('History');
}

test('lists a booking with its day, dock and status', async () => {
    answerWith([booking]);

    render(<Bookings />);

    expect(await screen.findByText(/Wed, 02\/06\/2027, 06:00/)).toBeInTheDocument();
    expect(screen.getByText(/inbound · 10 pallets · dock 3/)).toBeInTheDocument();
    expect(screen.getByText('booked')).toBeInTheDocument();
});

test('says so when nothing is booked yet', async () => {
    answerWith([]);

    render(<Bookings />);

    expect(await screen.findByText('You have not booked a slot yet.')).toBeInTheDocument();
});

test('opens a booking with its history', async () => {
    answerWith([booking]);

    render(<Bookings />);

    fireEvent.click(await screen.findByRole('button', { name: /02\/06\/2027/ }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/bookings/7'));

    expect(await screen.findByText('History')).toBeInTheDocument();
    expect(screen.getByText('PO-4711')).toBeInTheDocument();
    expect(screen.getByText(/booked by Lena Supplier/)).toBeInTheDocument();
    expect(screen.getByText(/06:00 – 06:15/)).toBeInTheDocument();
});

test('goes back from a booking to the list', async () => {
    answerWith([booking]);

    render(<Bookings />);

    fireEvent.click(await screen.findByRole('button', { name: /02\/06\/2027/ }));

    fireEvent.click(await screen.findByRole('button', { name: /Back to the list/ }));

    expect(await screen.findByText('My bookings')).toBeInTheDocument();
});

test('walks to the next page', async () => {
    answerWith([booking], { current_page: 1, last_page: 2, total: 25 });

    render(<Bookings />);

    fireEvent.click(await screen.findByRole('button', { name: 'Next' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/bookings?page=2'));
});

test('hides the pages when there is only one', async () => {
    answerWith([booking]);

    render(<Bookings />);

    await screen.findByText('My bookings');

    expect(screen.queryByRole('button', { name: 'Next' })).not.toBeInTheDocument();
});

test('says so when the bookings cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<Bookings />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});

test('offers moving and cancelling while a booking is merely booked', async () => {
    await openDetail();

    expect(screen.getByRole('button', { name: 'Change this booking' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel this booking' })).toBeInTheDocument();
});

test('offers neither on a cancelled booking', async () => {
    await openDetail({ ...detail, status: 'cancelled' });

    expect(screen.queryByRole('button', { name: 'Change this booking' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cancel this booking' })).not.toBeInTheDocument();
});

test('cancels a booking with a reason', async () => {
    await openDetail();

    api.post.mockResolvedValue({ data: { data: { ...detail, status: 'cancelled' } } });

    fireEvent.click(screen.getByRole('button', { name: 'Cancel this booking' }));
    fireEvent.change(screen.getByLabelText('Reason (optional)'), { target: { value: 'No driver.' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cancel the booking' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings/7/cancel', { reason: 'No driver.' })
    );

    expect(csrf).toHaveBeenCalled();
    expect(await screen.findByText('cancelled')).toBeInTheDocument();
});

test('cancels a booking without a reason', async () => {
    await openDetail();

    api.post.mockResolvedValue({ data: { data: { ...detail, status: 'cancelled' } } });

    fireEvent.click(screen.getByRole('button', { name: 'Cancel this booking' }));
    fireEvent.click(screen.getByRole('button', { name: 'Cancel the booking' }));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/bookings/7/cancel', { reason: null }));
});

test('keeps the booking when the cancellation is called off', async () => {
    await openDetail();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel this booking' }));
    fireEvent.click(screen.getByRole('button', { name: 'Keep it' }));

    expect(api.post).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: 'Cancel this booking' })).toBeInTheDocument();
});

test('shows why a cancellation was refused', async () => {
    await openDetail();

    api.post.mockRejectedValue({
        response: {
            status: 403,
            data: { message: 'A booking can only be cancelled up to three hours before its slot.' },
        },
    });

    fireEvent.click(screen.getByRole('button', { name: 'Cancel this booking' }));
    fireEvent.click(screen.getByRole('button', { name: 'Cancel the booking' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('three hours before its slot');
});

test('moves a booking to a slot that was picked', async () => {
    await openDetail();

    api.patch.mockResolvedValue({
        data: { data: { ...detail, starts_at: '2027-06-04T08:00:00Z', ends_at: '2027-06-04T08:15:00Z' } },
    });

    fireEvent.click(screen.getByRole('button', { name: 'Change this booking' }));

    fireEvent.click(await screen.findByRole('button', { name: '10:00' }));

    await waitFor(() =>
        expect(api.patch).toHaveBeenCalledWith('/bookings/7', {
            starts_at: '2027-06-04T08:00:00Z',
            pallets: 10,
        })
    );
});

test('takes a new pallet count along when moving', async () => {
    await openDetail();

    api.patch.mockResolvedValue({ data: { data: detail } });

    fireEvent.click(screen.getByRole('button', { name: 'Change this booking' }));
    fireEvent.change(screen.getByLabelText('Pallets'), { target: { value: '30' } });

    fireEvent.click(await screen.findByRole('button', { name: '10:00' }));

    await waitFor(() =>
        expect(api.patch).toHaveBeenCalledWith('/bookings/7', expect.objectContaining({ pallets: 30 }))
    );
});

test('shows why a move was refused', async () => {
    await openDetail();

    api.patch.mockRejectedValue({
        response: { status: 409, data: { message: 'This slot has just been taken. Please pick another one.' } },
    });

    fireEvent.click(screen.getByRole('button', { name: 'Change this booking' }));
    fireEvent.click(await screen.findByRole('button', { name: '10:00' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('has just been taken');
});
