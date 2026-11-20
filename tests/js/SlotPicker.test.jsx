import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { get: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { default: SlotPicker } = await import('../../resources/js/components/SlotPicker');

const days = [
    {
        date: '2027-06-02',
        slots: [
            { starts_at: '2027-06-02T04:00:00Z', ends_at: '2027-06-02T04:45:00Z', shift: 'early' },
            { starts_at: '2027-06-02T04:15:00Z', ends_at: '2027-06-02T05:00:00Z', shift: 'early' },
        ],
    },
    {
        date: '2027-06-03',
        slots: [{ starts_at: '2027-06-03T04:00:00Z', ends_at: '2027-06-03T04:45:00Z', shift: 'early' }],
    },
];

beforeEach(() => {
    api.get.mockReset();
    api.get.mockResolvedValue({ data: { data: days, meta: { duration_minutes: 45 } } });
});

test('asks for the slots of this load', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/slots?type=inbound&pallets=24'));

    expect(await screen.findByText('24 pallets block a dock for 45 minutes.')).toBeInTheDocument();
});

test('offers a tile per day with the number of free times', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    expect(await screen.findByRole('button', { name: 'Wednesday 02/06, 2 free times' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Thursday 03/06, 1 free time' })).toBeInTheDocument();
});

test('shows the start times of the chosen day, grouped by shift', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    expect(await screen.findByRole('button', { name: '06:00' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '06:15' })).toBeInTheDocument();
    expect(screen.getByText(/Early shift/)).toBeInTheDocument();
    expect(screen.getByText(/No free start time left in this shift/)).toBeInTheDocument();
});

test('shows another day once its tile is chosen', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    fireEvent.click(await screen.findByRole('button', { name: /Thursday 03\/06/ }));

    expect(screen.getAllByRole('button', { name: '06:00' })).toHaveLength(1);
    expect(screen.queryByRole('button', { name: '06:15' })).not.toBeInTheDocument();
});

test('marks the start time that was picked', async () => {
    render(
        <SlotPicker type="inbound" pallets={24} selected={days[0].slots[1]} onPick={vi.fn()} />
    );

    expect(await screen.findByRole('button', { name: '06:15' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: '06:00' })).toHaveAttribute('aria-pressed', 'false');
});

test('hands the chosen slot upwards', async () => {
    const onPick = vi.fn();

    render(<SlotPicker type="inbound" pallets={24} onPick={onPick} />);

    fireEvent.click(await screen.findByRole('button', { name: '06:15' }));

    expect(onPick).toHaveBeenCalledWith(days[0].slots[1]);
});

test('pages to the later days', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    fireEvent.click(await screen.findByRole('button', { name: 'Later days' }));

    await waitFor(() =>
        expect(api.get).toHaveBeenCalledWith('/slots?type=inbound&pallets=24&after=2027-06-03')
    );
});

test('pages to the earlier days', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    fireEvent.click(await screen.findByRole('button', { name: 'Earlier days' }));

    await waitFor(() =>
        expect(api.get).toHaveBeenCalledWith('/slots?type=inbound&pallets=24&before=2027-06-02')
    );
});

test('falls back to the first days when a page comes back empty', async () => {
    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    await screen.findByRole('button', { name: /Wednesday 02\/06/ });

    api.get.mockResolvedValueOnce({ data: { data: [], meta: { duration_minutes: 45 } } });

    fireEvent.click(screen.getByRole('button', { name: 'Later days' }));

    await waitFor(() => expect(api.get).toHaveBeenCalledTimes(3));

    expect(screen.getByRole('button', { name: /Wednesday 02\/06/ })).toBeInTheDocument();
});

test('says so when nothing is free', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: { duration_minutes: 45 } } });

    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    expect(await screen.findByText(/No free start times for this load/)).toBeInTheDocument();
});

test('says so when the slots cannot be loaded', async () => {
    api.get.mockRejectedValue(new Error('nope'));

    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} />);

    expect(await screen.findByRole('alert')).toHaveTextContent('could not be loaded');
});

test('tells the page how long the load blocks a dock', async () => {
    const onDuration = vi.fn();

    render(<SlotPicker type="inbound" pallets={24} onPick={vi.fn()} onDuration={onDuration} />);

    await waitFor(() => expect(onDuration).toHaveBeenCalledWith(45));
});
