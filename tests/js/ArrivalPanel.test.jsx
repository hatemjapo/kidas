import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { vi } from 'vitest';

vi.mock('../../resources/js/lib/api', () => ({
    default: { post: vi.fn(), patch: vi.fn() },
    csrf: vi.fn(),
}));

const api = (await import('../../resources/js/lib/api')).default;
const { csrf } = await import('../../resources/js/lib/api');
const { default: ArrivalPanel } = await import('../../resources/js/components/ArrivalPanel');

const awaited = {
    id: 7,
    type: 'inbound',
    status: 'booked',
    starts_at: '2027-06-02T10:00:00Z',
    ends_at: '2027-06-02T10:15:00Z',
    pallets: 10,
    order_number: 'PO-4711',
    license_plate: 'HHAB1234',
    company: 'Nordfracht GmbH',
    dock_number: 1,
    arrived_at: null,
    arrived_plate: null,
    plate_mismatch: false,
    plate_verified: false,
};

beforeEach(() => {
    api.post.mockReset();
    api.patch.mockReset();
    csrf.mockReset();
    csrf.mockResolvedValue({});
});

test('names the booking that is awaited', () => {
    render(<ArrivalPanel booking={awaited} onDone={vi.fn()} onClose={vi.fn()} />);

    expect(screen.getByText(/12:00 – 12:15 · dock 1 · Nordfracht GmbH/)).toBeInTheDocument();
    expect(screen.getByText(/expected/)).toHaveTextContent('HHAB1234');
});

test('registers the arrival with the plate that came', async () => {
    const onDone = vi.fn();

    api.post.mockResolvedValue({ data: { data: { ...awaited, status: 'arrived', arrived_plate: 'HHAB1234' } } });

    render(<ArrivalPanel booking={awaited} onDone={onDone} onClose={vi.fn()} />);

    fireEvent.change(screen.getByLabelText('License plate that arrived'), {
        target: { value: 'hh-ab 1234' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Register the arrival' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings/7/arrival', { license_plate: 'hh-ab 1234' })
    );

    expect(csrf).toHaveBeenCalled();
    expect(onDone).toHaveBeenCalled();
});

test('keeps the plate editable once the lorry is there', () => {
    render(
        <ArrivalPanel
            booking={{ ...awaited, status: 'arrived', arrived_at: '2027-06-02T10:05:00Z', arrived_plate: 'HHAB1234' }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByLabelText('License plate that arrived')).toHaveValue('HHAB1234');
    expect(screen.getByRole('button', { name: 'Correct the plate' })).toBeInTheDocument();
    expect(screen.getByText(/Arrived at 12:05 with/)).toHaveTextContent('HHAB1234');
});

test('corrects a plate that was typed wrongly', async () => {
    api.patch.mockResolvedValue({ data: { data: { ...awaited, status: 'arrived', arrived_plate: 'HHAB1234' } } });

    render(
        <ArrivalPanel
            booking={{
                ...awaited,
                status: 'arrived',
                arrived_at: '2027-06-02T10:05:00Z',
                arrived_plate: 'HHAB1243',
                plate_mismatch: true,
            }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByLabelText('License plate that arrived')).toHaveValue('HHAB1243');

    fireEvent.change(screen.getByLabelText('License plate that arrived'), {
        target: { value: 'HHAB1234' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Correct the plate' }));

    await waitFor(() =>
        expect(api.patch).toHaveBeenCalledWith('/bookings/7/arrival', { license_plate: 'HHAB1234' })
    );

    expect(api.post).not.toHaveBeenCalled();
});

test('shows the corrected plate after a reload', () => {
    const { rerender } = render(
        <ArrivalPanel
            booking={{ ...awaited, status: 'arrived', arrived_plate: 'HHAB1243', plate_mismatch: true }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    rerender(
        <ArrivalPanel
            booking={{ ...awaited, status: 'arrived', arrived_plate: 'HHAB1234', plate_mismatch: false }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByLabelText('License plate that arrived')).toHaveValue('HHAB1234');
    expect(screen.queryByRole('button', { name: 'Confirm the plate' })).not.toBeInTheDocument();
});

test('warns about a plate that does not match', () => {
    render(
        <ArrivalPanel
            booking={{
                ...awaited,
                status: 'arrived',
                arrived_at: '2027-06-02T10:05:00Z',
                arrived_plate: 'HHZZ9999',
                plate_mismatch: true,
            }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByRole('status')).toHaveTextContent('does not match');
    expect(screen.getByRole('button', { name: 'Confirm the plate' })).toBeInTheDocument();
});

test('confirms the plate with what the company said', async () => {
    api.post.mockResolvedValue({ data: { data: { ...awaited, plate_verified: true } } });

    render(
        <ArrivalPanel
            booking={{ ...awaited, status: 'arrived', arrived_plate: 'HHZZ9999', plate_mismatch: true }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    fireEvent.change(screen.getByLabelText('What did they say? (optional)'), {
        target: { value: 'Rang the office.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm the plate' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings/7/plate-verification', { note: 'Rang the office.' })
    );
});

test('confirms the plate without a note', async () => {
    api.post.mockResolvedValue({ data: { data: { ...awaited, plate_verified: true } } });

    render(
        <ArrivalPanel
            booking={{ ...awaited, status: 'arrived', arrived_plate: 'HHZZ9999', plate_mismatch: true }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Confirm the plate' }));

    await waitFor(() =>
        expect(api.post).toHaveBeenCalledWith('/bookings/7/plate-verification', { note: null })
    );
});

test('says so once a mismatch has been confirmed', () => {
    render(
        <ArrivalPanel
            booking={{
                ...awaited,
                status: 'arrived',
                arrived_plate: 'HHZZ9999',
                plate_mismatch: true,
                plate_verified: true,
            }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.queryByRole('button', { name: 'Confirm the plate' })).not.toBeInTheDocument();
    expect(screen.getByText(/has been confirmed with the company/)).toBeInTheDocument();
});

test('shows why the arrival was refused', async () => {
    api.post.mockRejectedValue({
        response: {
            status: 403,
            data: { message: 'Only a booking that is still awaited can be registered as arrived.' },
        },
    });

    render(<ArrivalPanel booking={awaited} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Register the arrival' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('still awaited');
});

test('shows the field error for a missing plate', async () => {
    api.post.mockRejectedValue({
        response: { status: 422, data: { errors: { license_plate: ['The license plate field is required.'] } } },
    });

    render(<ArrivalPanel booking={awaited} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Register the arrival' }));

    expect(await screen.findByText('The license plate field is required.')).toBeInTheDocument();
});

const arrived = {
    ...awaited,
    status: 'arrived',
    arrived_at: '2027-06-02T10:05:00Z',
    arrived_plate: 'HHAB1234',
    started_at: null,
    completed_at: null,
};

test('starts the handling of a lorry that is waiting', async () => {
    api.post.mockResolvedValue({ data: { data: { ...arrived, status: 'in_progress' } } });

    render(<ArrivalPanel booking={arrived} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Start the handling' }));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/bookings/7/handling', {}));
});

test('does not let the handling start while the plate is in question', () => {
    render(
        <ArrivalPanel
            booking={{ ...arrived, arrived_plate: 'HHZZ9999', plate_mismatch: true }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByRole('button', { name: 'Start the handling' })).toBeDisabled();
});

test('lets the handling start once the plate was confirmed', () => {
    render(
        <ArrivalPanel
            booking={{ ...arrived, arrived_plate: 'HHZZ9999', plate_mismatch: true, plate_verified: true }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByRole('button', { name: 'Start the handling' })).toBeEnabled();
});

test('releases the dock when the handling is done', async () => {
    api.post.mockResolvedValue({ data: { data: { ...arrived, status: 'completed' } } });

    render(
        <ArrivalPanel
            booking={{ ...arrived, status: 'in_progress', started_at: '2027-06-02T10:10:00Z' }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByText(/Handling started at 12:10/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Start the handling' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'The dock is free' }));

    expect(api.post).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, the dock is free' }));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/bookings/7/completion', {}));
});

test('asks before the dock is released', () => {
    render(
        <ArrivalPanel
            booking={{ ...arrived, status: 'in_progress', started_at: '2027-06-02T10:10:00Z' }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    fireEvent.click(screen.getByRole('button', { name: 'The dock is free' }));

    expect(screen.getByText(/Release dock 1 and finish this booking/)).toBeInTheDocument();
});

test('lets the question be waved away', () => {
    render(
        <ArrivalPanel
            booking={{ ...arrived, status: 'in_progress', started_at: '2027-06-02T10:10:00Z' }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    fireEvent.click(screen.getByRole('button', { name: 'The dock is free' }));
    fireEvent.click(screen.getByRole('button', { name: 'Not yet' }));

    expect(screen.queryByText(/Release dock 1 and finish this booking/)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'The dock is free' })).toBeInTheDocument();
    expect(api.post).not.toHaveBeenCalled();
});

test('shows a finished booking without any buttons', () => {
    render(
        <ArrivalPanel
            booking={{
                ...arrived,
                status: 'completed',
                started_at: '2027-06-02T10:10:00Z',
                completed_at: '2027-06-02T10:40:00Z',
            }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.getByText(/Dock released at 12:40/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'The dock is free' })).not.toBeInTheDocument();
    expect(screen.queryByLabelText('License plate that arrived')).not.toBeInTheDocument();
});

test('shows why a step was refused', async () => {
    api.post.mockRejectedValue({
        response: {
            status: 422,
            data: { message: 'The plate has to be confirmed with the company before handling starts.' },
        },
    });

    render(<ArrivalPanel booking={arrived} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Start the handling' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('confirmed with the company');
});

const passed = { ...awaited, starts_at: '2020-06-02T10:00:00Z', ends_at: '2020-06-02T10:15:00Z' };

test('offers the no-show only once the slot is over', () => {
    const { rerender } = render(<ArrivalPanel booking={awaited} onDone={vi.fn()} onClose={vi.fn()} />);

    expect(screen.queryByRole('button', { name: 'Nobody turned up' })).not.toBeInTheDocument();

    rerender(<ArrivalPanel booking={passed} onDone={vi.fn()} onClose={vi.fn()} />);

    expect(screen.getByRole('button', { name: 'Nobody turned up' })).toBeInTheDocument();
});

test('asks before a no-show is marked', async () => {
    api.post.mockResolvedValue({ data: { data: { ...passed, status: 'no_show' } } });

    render(<ArrivalPanel booking={passed} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Nobody turned up' }));

    expect(api.post).not.toHaveBeenCalled();
    expect(screen.getByText(/free for somebody else/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, nobody turned up' }));

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/bookings/7/no-show', {}));
});

test('lets the no-show question be waved away', () => {
    render(<ArrivalPanel booking={passed} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Nobody turned up' }));
    fireEvent.click(screen.getByRole('button', { name: 'Not yet' }));

    expect(screen.getByRole('button', { name: 'Nobody turned up' })).toBeInTheDocument();
    expect(api.post).not.toHaveBeenCalled();
});

test('offers no no-show once the lorry is there', () => {
    render(
        <ArrivalPanel
            booking={{ ...passed, status: 'arrived', arrived_plate: 'HHAB1234' }}
            onDone={vi.fn()}
            onClose={vi.fn()}
        />
    );

    expect(screen.queryByRole('button', { name: 'Nobody turned up' })).not.toBeInTheDocument();
});

test('shows why a no-show was refused', async () => {
    api.post.mockRejectedValue({
        response: { status: 422, data: { message: 'The slot is not over yet.' } },
    });

    render(<ArrivalPanel booking={passed} onDone={vi.fn()} onClose={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Nobody turned up' }));
    fireEvent.click(screen.getByRole('button', { name: 'Yes, nobody turned up' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('not over yet');
});
