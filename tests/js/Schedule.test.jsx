import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { default: Schedule } = await import('../../resources/js/pages/Schedule');

function dock(number, bookings = [], isActive = true) {
    return { number, is_active: isActive, usage: { early: 'inbound', late: 'outbound' }, bookings };
}

const booking = {
    id: 7,
    arrived_at: null,
    arrived_plate: null,
    plate_mismatch: false,
    plate_verified: false,
    type: 'inbound',
    status: 'booked',
    starts_at: '2027-06-02T07:00:00Z',
    ends_at: '2027-06-02T07:45:00Z',
    pallets: 24,
    order_number: 'PO-4711',
    license_plate: 'HHAB1234',
    company: 'Nordfracht GmbH',
};

function schedule(docks, overrides = {}) {
    return {
        data: {
            data: {
                date: '2027-06-02',
                is_released: true,
                is_closed: false,
                opens_at: '2027-06-02T04:00:00Z',
                closes_at: '2027-06-02T16:00:00Z',
                docks,
                ...overrides,
            },
        },
    };
}

beforeEach(() => {
    vi.setSystemTime(new Date('2027-06-02T09:00:00Z'));

    api.get.mockReset();
    api.get.mockResolvedValue(schedule([dock(1, [booking]), dock(2), dock(3, [], false)]));
});

afterEach(() => {
    vi.useRealTimers();
});

test('asks for today when it opens', async () => {
    render(<Schedule />);

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/schedule?date=2027-06-02'));
});

test('shows a row for every dock, switched off ones included', async () => {
    render(<Schedule />);

    expect(await screen.findByText('Dock 1')).toBeInTheDocument();
    expect(screen.getByText('Dock 2')).toBeInTheDocument();
    expect(screen.getByText('Dock 3')).toBeInTheDocument();
    expect(screen.getByText('(off)')).toBeInTheDocument();
});

test('shows the window of the day', async () => {
    render(<Schedule />);

    expect(await screen.findByText(/06:00 – 18:00/)).toBeInTheDocument();
});

test('puts the booking where it belongs on the timeline', async () => {
    render(<Schedule />);

    const block = await screen.findByTitle(/Nordfracht GmbH/);

    expect(block).toHaveTextContent('09:00 Nordfracht GmbH');
    expect(block.style.gridRow).toBe('14 / span 3');
    expect(block.style.gridColumn).toBe('2');
});

test('marks the type of traffic', async () => {
    render(<Schedule />);

    expect(await screen.findByTitle(/Nordfracht GmbH/)).toHaveClass('bg-sky-100');
});

test('marks a booking that is being handled', async () => {
    api.get.mockResolvedValue(schedule([dock(1, [{ ...booking, status: 'in_progress' }])]));

    render(<Schedule />);

    expect(await screen.findByTitle(/Nordfracht GmbH/)).toHaveClass('ring-blue-500');
});

test('walks to the next day and back', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: 'Next day' }));
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/schedule?date=2027-06-03'));

    fireEvent.click(screen.getByRole('button', { name: 'Previous day' }));
    fireEvent.click(screen.getByRole('button', { name: 'Previous day' }));
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/schedule?date=2027-06-01'));
});

test('jumps back to today', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: 'Next day' }));
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/schedule?date=2027-06-03'));

    fireEvent.click(screen.getByRole('button', { name: 'Today' }));
    await waitFor(() => expect(api.get).toHaveBeenLastCalledWith('/schedule?date=2027-06-02'));
});

test('says when a day is not released', async () => {
    api.get.mockResolvedValue(schedule([dock(1)], { is_released: false }));

    render(<Schedule />);

    expect(await screen.findByText(/not released for booking/)).toBeInTheDocument();
});

test('says when a day is closed', async () => {
    api.get.mockResolvedValue(schedule([dock(1)], { is_closed: true }));

    render(<Schedule />);

    expect(await screen.findByText(/closed/)).toBeInTheDocument();
});

test('says so when the schedule cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<Schedule />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});

test('opens a booking from the timeline', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByTitle(/Nordfracht GmbH/));

    expect(await screen.findByLabelText('License plate that arrived')).toBeInTheDocument();
    expect(screen.getByText(/dock 1 · Nordfracht GmbH/)).toBeInTheDocument();
});

test('closes the panel again', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByTitle(/Nordfracht GmbH/));
    fireEvent.click(await screen.findByRole('button', { name: 'Close' }));

    expect(screen.queryByLabelText('License plate that arrived')).not.toBeInTheDocument();
});

test('marks a booking whose plate is in question', async () => {
    api.get.mockResolvedValue(
        schedule([dock(1, [{ ...booking, status: 'arrived', plate_mismatch: true, plate_verified: false }])])
    );

    render(<Schedule />);

    expect(await screen.findByTitle(/Nordfracht GmbH/)).toHaveTextContent('⚠');
});

test('reloads the day after an arrival was registered', async () => {
    api.post.mockResolvedValue({ data: { data: { ...booking, status: 'arrived', arrived_plate: 'HHAB1234' } } });

    render(<Schedule />);

    fireEvent.click(await screen.findByTitle(/Nordfracht GmbH/));

    const before = api.get.mock.calls.filter(([url]) => url.startsWith('/schedule')).length;

    fireEvent.change(screen.getByLabelText('License plate that arrived'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Register the arrival' }));

    await waitFor(() =>
        expect(api.get.mock.calls.filter(([url]) => url.startsWith('/schedule')).length).toBe(before + 1)
    );
});

test('puts the time down the left and the docks across the top', async () => {
    render(<Schedule />);

    const hour = await screen.findByText('06:00');

    expect(hour.style.gridColumn).toBe('1');
    expect(hour.style.gridRow).toBe('2 / span 4');

    const head = screen.getByText('Dock 2');

    expect(head.style.gridColumn).toBe('3');
    expect(head.style.gridRow).toBe('1');
});

test('opens the booking in a dialog', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByTitle(/Nordfracht GmbH/));

    const dialog = await screen.findByRole('dialog');

    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog).toHaveTextContent('Nordfracht GmbH');
});

test('closes the dialog on escape', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByTitle(/Nordfracht GmbH/));
    await screen.findByRole('dialog');

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
});
