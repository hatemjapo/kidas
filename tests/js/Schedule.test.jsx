import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/auth/AuthContext', () => ({
    useAuth: vi.fn(),
}));

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { useAuth } = await import('../../resources/js/auth/AuthContext');
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

    useAuth.mockReturnValue({ role: 'staff' });

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

test('names the day it is showing', async () => {
    render(<Schedule />);

    expect(await screen.findByRole('heading', { name: 'Wednesday, 2 June 2027', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('Dock schedule')).toBeInTheDocument();
});

test('shows a column for every dock and what it serves', async () => {
    render(<Schedule />);

    expect(await screen.findByText('Dock 1')).toBeInTheDocument();
    expect(screen.getByText('Dock 2')).toBeInTheDocument();
    expect(screen.getByText('Dock 3')).toBeInTheDocument();
    expect(screen.getByText('Out of service')).toBeInTheDocument();
    expect(screen.getAllByText('Deliveries / Pickups')).toHaveLength(2);
});

test('names a dock that serves one kind all day by that kind', async () => {
    api.get.mockResolvedValue(
        schedule([{ number: 1, is_active: true, usage: { early: 'outbound', late: 'outbound' }, bookings: [] }])
    );

    render(<Schedule />);

    expect(await screen.findByText('Pickups')).toBeInTheDocument();
});

test('counts the day in four numbers', async () => {
    api.get.mockResolvedValue(
        schedule([
            dock(1, [booking, { ...booking, id: 8, status: 'in_progress' }]),
            dock(2, [
                { ...booking, id: 9, status: 'completed' },
                { ...booking, id: 10, status: 'arrived' },
                { ...booking, id: 11, status: 'no_show' },
            ]),
        ])
    );

    render(<Schedule />);

    expect(await screen.findByText('Expected')).toBeInTheDocument();

    const tile = (label) => screen.getAllByText(label)[0].parentElement;

    expect(tile('Expected')).toHaveTextContent('1');
    expect(tile('At the dock or waiting')).toHaveTextContent('2');
    expect(tile('Completed')).toHaveTextContent('1');
    expect(tile('No show')).toHaveTextContent('1');
});

test('explains the colours it uses', async () => {
    render(<Schedule />);

    expect(await screen.findByText('Delivery')).toBeInTheDocument();
    ['Pickup', 'Arrived', 'In progress', 'Completed', 'No show'].forEach((label) => {
        expect(screen.getAllByText(label).length).toBeGreaterThan(0);
    });
});

test('puts the booking where it belongs on the timeline', async () => {
    render(<Schedule />);

    const block = await screen.findByRole('button', { name: /Nordfracht GmbH/ });

    expect(block).toHaveTextContent('09:00–09:45');
    expect(block.style.gridRow).toBe('14 / span 3');
    expect(block.style.gridColumn).toBe('2');
});

test('says everything about a block in its name', async () => {
    render(<Schedule />);

    expect(
        await screen.findByRole('button', {
            name: '09:00 to 09:45, dock 1, Nordfracht GmbH, 24 pallets, Booked',
        })
    ).toBeInTheDocument();
});

test('writes pallets and status into a block that has room', async () => {
    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).toHaveTextContent('24 pal. · Booked');
});

test('leaves a short block with the time and the company', async () => {
    api.get.mockResolvedValue(
        schedule([dock(1, [{ ...booking, ends_at: '2027-06-02T07:15:00Z' }])])
    );

    render(<Schedule />);

    const block = await screen.findByRole('button', { name: /Nordfracht GmbH/ });

    expect(block).toHaveTextContent('09:00–09:15');
    expect(block).not.toHaveTextContent('pal.');
});

test('marks a delivery in the brand tint', async () => {
    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).toHaveClass('bg-brand-50');
});

test('marks a pickup in its own tint', async () => {
    api.get.mockResolvedValue(schedule([dock(1, [{ ...booking, type: 'outbound' }])]));

    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).toHaveClass('bg-outbound-50');
});

test('marks a booking that is being handled', async () => {
    api.get.mockResolvedValue(schedule([dock(1, [{ ...booking, status: 'in_progress' }])]));

    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).toHaveClass('bg-brand-700');
});

test('hatches a dock that is switched off', async () => {
    const { container } = render(<Schedule />);

    await screen.findByText('Dock 1');

    expect(container.querySelector('[class*="repeating-linear-gradient"]')).toBeInTheDocument();
});

test('draws the current time across the day', async () => {
    const { container } = render(<Schedule />);

    await screen.findByText('Dock 1');

    expect(container.querySelector('.bg-now')).toHaveTextContent('11:00');
});

test('draws no current time on another day', async () => {
    const { container } = render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: 'Next day' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/schedule?date=2027-06-03'));
    await waitFor(() => expect(container.querySelector('.bg-now')).toBeNull());
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

    expect(await screen.findByText(/not been released for booking/)).toBeInTheDocument();
});

test('says when a day is closed', async () => {
    api.get.mockResolvedValue(schedule([dock(1)], { is_closed: true }));

    render(<Schedule />);

    expect(await screen.findByText(/closed on this day/)).toBeInTheDocument();
});

test('says so when the schedule cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<Schedule />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});

test('opens a booking in a dialog', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: /Nordfracht GmbH/ }));

    const dialog = await screen.findByRole('dialog');

    expect(dialog).toHaveAccessibleName('09:00 · Dock 1 · Nordfracht GmbH');
    expect(screen.getByLabelText('License plate that arrived')).toBeInTheDocument();
});

test('closes the dialog again', async () => {
    render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: /Nordfracht GmbH/ }));
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
});

test('marks a booking whose plate is in question', async () => {
    api.get.mockResolvedValue(
        schedule([dock(1, [{ ...booking, status: 'arrived', plate_mismatch: true, plate_verified: false }])])
    );

    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).toHaveTextContent('Check plate');
});

test('says nothing about a plate that was confirmed', async () => {
    api.get.mockResolvedValue(
        schedule([dock(1, [{ ...booking, status: 'arrived', plate_mismatch: true, plate_verified: true }])])
    );

    render(<Schedule />);

    expect(await screen.findByRole('button', { name: /Nordfracht GmbH/ })).not.toHaveTextContent('Check plate');
});

test('reloads the day after an arrival was registered', async () => {
    api.post.mockResolvedValue({ data: { data: { ...booking, status: 'arrived', arrived_plate: 'HHAB1234' } } });

    render(<Schedule />);

    fireEvent.click(await screen.findByRole('button', { name: /Nordfracht GmbH/ }));

    const before = api.get.mock.calls.filter(([url]) => url.startsWith('/schedule')).length;

    fireEvent.change(screen.getByLabelText('License plate that arrived'), { target: { value: 'HHAB1234' } });
    fireEvent.click(screen.getByRole('button', { name: 'Register the arrival' }));

    await waitFor(() =>
        expect(api.get.mock.calls.filter(([url]) => url.startsWith('/schedule')).length).toBe(before + 1)
    );
});
