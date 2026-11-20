import { useEffect, useState } from 'react';
import SlotPicker from './SlotPicker';
import { useAuth } from '../auth/AuthContext';
import api, { csrf } from '../lib/api';
import { formatTime } from '../lib/datetime';
import { plateMismatchStyle } from '../lib/statusStyles';
import Alert from './ui/Alert';
import Button from './ui/Button';
import InfoBox from './ui/InfoBox';
import NumberField from './ui/NumberField';
import StatusChip from './ui/StatusChip';
import TextField from './ui/TextField';

const typeNames = {
    inbound: 'Delivery',
    outbound: 'Pickup',
};

export default function ArrivalPanel({ booking, onDone }) {
    const { role } = useAuth();
    const mayChangeTheBooking = role === 'admin' && booking.status === 'booked';
    const [plate, setPlate] = useState(booking.arrived_plate ?? '');
    const [note, setNote] = useState('');
    const [releasing, setReleasing] = useState(false);
    const [marking, setMarking] = useState(false);
    const [changing, setChanging] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [pallets, setPallets] = useState(booking.pallets);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [working, setWorking] = useState(false);

    useEffect(() => {
        setPlate(booking.arrived_plate ?? '');
    }, [booking.arrived_plate]);

    async function changeBooking(startsAt) {
        await send(
            `/admin/bookings/${booking.id}`,
            {
                starts_at: startsAt,
                pallets,
                reason: reason === '' ? null : reason,
            },
            api.patch
        );

        setChanging(false);
    }

    async function cancelBooking() {
        await send(`/admin/bookings/${booking.id}/cancel`, { reason: reason === '' ? null : reason });

        setCancelling(false);
    }

    async function send(url, payload, request = api.post) {
        setErrors({});
        setMessage(null);
        setWorking(true);

        try {
            await csrf();

            const response = await request(url, payload);

            onDone(response.data.data ?? response.data);
        } catch (failure) {
            if (failure.response?.status === 422 && failure.response.data?.errors) {
                setErrors(failure.response.data.errors);
            } else {
                setMessage(failure.response?.data?.message ?? 'This could not be saved.');
            }
        } finally {
            setWorking(false);
        }
    }

    const waiting = booking.status === 'booked' || booking.status === 'arrived';
    const slotIsOver = new Date(booking.ends_at) <= new Date();

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted">
                    {typeNames[booking.type] ?? booking.type} · {booking.pallets} pallets · {booking.order_number} ·
                    expected <span className="font-mono text-ink">{booking.license_plate ?? '—'}</span>
                </p>

                <div className="flex items-center gap-2">
                    <StatusChip status={booking.status} type={booking.type} />

                    {booking.plate_mismatch && ! booking.plate_verified && (
                        <span
                            className={`inline-flex items-center rounded-nav px-2 py-1 text-xs font-semibold ${plateMismatchStyle.chip}`}
                        >
                            {plateMismatchStyle.label}
                        </span>
                    )}
                </div>
            </div>

            {message && <Alert>{message}</Alert>}

            {waiting && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (booking.status === 'booked') {
                            send(`/bookings/${booking.id}/arrival`, { license_plate: plate });
                        } else {
                            send(`/bookings/${booking.id}/arrival`, { license_plate: plate }, api.patch);
                        }
                    }}
                    className="flex flex-wrap items-end gap-3"
                    noValidate
                >
                    <TextField
                        id="arrived_plate"
                        label="License plate that arrived"
                        placeholder="HH-AB 1234"
                        value={plate}
                        error={errors.license_plate?.[0]}
                        onChange={(event) => setPlate(event.target.value)}
                        className="w-56"
                    />

                    <Button type="submit" loading={working}>
                        {booking.status === 'booked' ? 'Register the arrival' : 'Correct the plate'}
                    </Button>
                </form>
            )}

            {booking.arrived_at !== null && booking.arrived_at !== undefined && (
                <p className="text-sm text-muted" data-numeric>
                    Arrived at {formatTime(booking.arrived_at)} with{' '}
                    <span className="font-mono text-ink">{booking.arrived_plate}</span>.
                </p>
            )}

            {booking.status === 'booked' && slotIsOver && ! marking && (
                <Button variant="secondary" size={40} disabled={working} onClick={() => setMarking(true)}>
                    Nobody turned up
                </Button>
            )}

            {booking.status === 'booked' && marking && (
                <div className="space-y-3 rounded-tile border border-line bg-canvas p-4">
                    <p className="text-sm text-ink">
                        Mark this booking as a no-show? The slot is then free for somebody else.
                    </p>

                    <div className="flex items-center gap-3">
                        <Button
                            size={40}
                            loading={working}
                            onClick={() => {
                                setMarking(false);
                                send(`/bookings/${booking.id}/no-show`, {});
                            }}
                        >
                            Yes, nobody turned up
                        </Button>

                        <Button variant="secondary" size={40} onClick={() => setMarking(false)}>
                            Not yet
                        </Button>
                    </div>
                </div>
            )}

            {booking.plate_mismatch && ! booking.plate_verified && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        send(`/bookings/${booking.id}/plate-verification`, { note: note === '' ? null : note });
                    }}
                    className="space-y-3 rounded-tile border border-warn-700 bg-warn-50 p-4"
                    noValidate
                >
                    <p role="status" className="text-sm text-warn-700">
                        The plate does not match the booking. Please check with the company before anything is
                        unloaded.
                    </p>

                    <TextField
                        id="verify_note"
                        label="What did they say? (optional)"
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                    />

                    <Button type="submit" size={40} loading={working}>
                        Confirm the plate
                    </Button>
                </form>
            )}

            {booking.plate_mismatch && booking.plate_verified && (
                <p className="text-sm text-muted">The plate was different and has been confirmed with the company.</p>
            )}

            {booking.status === 'arrived' && (
                <Button
                    disabled={working || (booking.plate_mismatch && ! booking.plate_verified)}
                    onClick={() => send(`/bookings/${booking.id}/handling`, {})}
                >
                    Start the handling
                </Button>
            )}

            {booking.started_at !== null && booking.started_at !== undefined && (
                <p className="text-sm text-muted" data-numeric>
                    Handling started at {formatTime(booking.started_at)}.
                </p>
            )}

            {booking.status === 'in_progress' && ! releasing && (
                <Button disabled={working} onClick={() => setReleasing(true)}>
                    The dock is free
                </Button>
            )}

            {booking.status === 'in_progress' && releasing && (
                <div className="space-y-3 rounded-tile border border-brand-500 bg-brand-50 p-4">
                    <p className="text-sm text-brand-800">
                        Release dock {booking.dock_number} and finish this booking? This cannot be undone.
                    </p>

                    <div className="flex items-center gap-3">
                        <Button
                            size={40}
                            loading={working}
                            onClick={() => {
                                setReleasing(false);
                                send(`/bookings/${booking.id}/completion`, {});
                            }}
                        >
                            Yes, the dock is free
                        </Button>

                        <Button variant="secondary" size={40} onClick={() => setReleasing(false)}>
                            Not yet
                        </Button>
                    </div>
                </div>
            )}

            {booking.completed_at !== null && booking.completed_at !== undefined && (
                <p className="text-sm text-muted" data-numeric>
                    Dock released at {formatTime(booking.completed_at)}.
                </p>
            )}

            {mayChangeTheBooking && ! changing && ! cancelling && (
                <div className="flex items-center gap-3 border-t border-line pt-4">
                    <Button variant="secondary" size={40} disabled={working} onClick={() => setChanging(true)}>
                        Change this booking
                    </Button>

                    <Button variant="danger" size={40} disabled={working} onClick={() => setCancelling(true)}>
                        Cancel this booking
                    </Button>
                </div>
            )}

            {mayChangeTheBooking && cancelling && (
                <div className="space-y-3 rounded-tile border border-danger-700 bg-danger-50 p-4">
                    <p className="text-sm text-danger-700">
                        Cancel this booking? The slot is then free for somebody else.
                    </p>

                    <TextField
                        id="cancel_reason"
                        label="Reason (optional)"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                    />

                    <div className="flex items-center gap-3">
                        <Button variant="danger" size={40} loading={working} onClick={cancelBooking}>
                            Yes, cancel it
                        </Button>

                        <Button variant="secondary" size={40} onClick={() => setCancelling(false)}>
                            Keep it
                        </Button>
                    </div>
                </div>
            )}

            {mayChangeTheBooking && changing && (
                <div className="space-y-4 rounded-tile border border-line p-4">
                    <h3 className="text-sm font-bold text-ink">Change this booking</h3>

                    <div className="flex flex-wrap items-end gap-3">
                        <NumberField
                            id="change_pallets"
                            label="Pallets"
                            min="1"
                            max="70"
                            value={pallets}
                            onChange={(event) => setPallets(Number(event.target.value))}
                        />

                        <TextField
                            id="change_reason"
                            label="Reason (optional)"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="w-56"
                        />

                        <Button loading={working} onClick={() => changeBooking(null)}>
                            Keep the time
                        </Button>

                        <Button variant="secondary" onClick={() => setChanging(false)}>
                            Leave it
                        </Button>
                    </div>

                    <InfoBox>Or pick another start time below; the dock is assigned again when you do.</InfoBox>

                    <SlotPicker
                        type={booking.type}
                        pallets={pallets}
                        onPick={(slot) => changeBooking(slot.starts_at)}
                    />
                </div>
            )}
        </div>
    );
}
