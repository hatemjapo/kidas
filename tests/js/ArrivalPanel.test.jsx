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
