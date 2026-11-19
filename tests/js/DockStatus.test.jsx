import { render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { default: DockStatus } = await import('../../resources/js/components/DockStatus');

const booking = { id: 7, status: 'booked', dock_number: 3 };

function answer(overrides = {}) {
    return {
        data: {
            data: {
                window_open: true,
                dock_free: true,
                busy_until: null,
                poll_seconds: 30,
                ...overrides,
            },
        },
    };
}

beforeEach(() => {
    api.get.mockReset();
});

test('says nothing while the window is shut', async () => {
    api.get.mockResolvedValue(answer({ window_open: false, dock_free: false }));

    const { container } = render(<DockStatus booking={booking} />);

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/bookings/7/status'));

    expect(container).toBeEmptyDOMElement();
});

test('invites the driver once the dock is free', async () => {
    api.get.mockResolvedValue(answer());

    render(<DockStatus booking={booking} />);

    expect(await screen.findByText('Your dock is free – you may arrive now.')).toBeInTheDocument();
    expect(screen.getByText('Dock 3')).toBeInTheDocument();
});

test('names until when the dock is still taken', async () => {
    api.get.mockResolvedValue(answer({ dock_free: false, busy_until: '2027-06-02T08:00:00Z' }));

    render(<DockStatus booking={booking} />);

    expect(await screen.findByText(/Another lorry is still at your dock until about 10:00/)).toBeInTheDocument();
});

test('tells an arrived driver to wait', async () => {
    api.get.mockResolvedValue(answer());

    render(<DockStatus booking={{ ...booking, status: 'arrived' }} />);

    expect(await screen.findByText(/registered your arrival/)).toBeInTheDocument();
    expect(screen.queryByText('Your dock is free – you may arrive now.')).not.toBeInTheDocument();
});

test('says when the handling is under way', async () => {
    api.get.mockResolvedValue(answer({ dock_free: false }));

    render(<DockStatus booking={{ ...booking, status: 'in_progress' }} />);

    expect(await screen.findByText('Your lorry is being handled.')).toBeInTheDocument();
});

test('asks again after the time the server gave', async () => {
    vi.useFakeTimers();

    api.get.mockResolvedValue(answer({ poll_seconds: 30 }));

    render(<DockStatus booking={booking} />);

    await vi.waitFor(() => expect(api.get).toHaveBeenCalledTimes(1));

    await vi.advanceTimersByTimeAsync(30_000);

    expect(api.get).toHaveBeenCalledTimes(2);

    vi.useRealTimers();
});

test('stops asking once the window is shut', async () => {
    vi.useFakeTimers();

    api.get.mockResolvedValue(answer({ window_open: false }));

    render(<DockStatus booking={booking} />);

    await vi.waitFor(() => expect(api.get).toHaveBeenCalledTimes(1));

    await vi.advanceTimersByTimeAsync(120_000);

    expect(api.get).toHaveBeenCalledTimes(1);

    vi.useRealTimers();
});

test('stops asking when the detail view is left', async () => {
    vi.useFakeTimers();

    api.get.mockResolvedValue(answer());

    const { unmount } = render(<DockStatus booking={booking} />);

    await vi.waitFor(() => expect(api.get).toHaveBeenCalledTimes(1));

    unmount();

    await vi.advanceTimersByTimeAsync(120_000);

    expect(api.get).toHaveBeenCalledTimes(1);

    vi.useRealTimers();
});

test('stays quiet when the status cannot be loaded', async () => {
    api.get.mockRejectedValue({ response: { status: 404 } });

    const { container } = render(<DockStatus booking={booking} />);

    await waitFor(() => expect(api.get).toHaveBeenCalled());

    expect(container).toBeEmptyDOMElement();
});
