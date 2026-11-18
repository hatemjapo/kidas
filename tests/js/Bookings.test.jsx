import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
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
});

function answerWith(bookings, meta = { current_page: 1, last_page: 1, total: bookings.length }) {
    api.get.mockImplementation((url) =>
        url.startsWith('/bookings?')
            ? Promise.resolve({ data: { data: bookings, meta } })
            : Promise.resolve({ data: { data: detail } })
    );
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
