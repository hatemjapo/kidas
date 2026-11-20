import { useState } from 'react';
import api, { csrf } from '../lib/api';
import { formatDateTime, formatLongDate, formatTime } from '../lib/datetime';
import { getStatusLabel } from '../lib/statusStyles';
import DockStatus from './DockStatus';
import SlotPicker from './SlotPicker';
import Alert from './ui/Alert';
import Button from './ui/Button';
import Card from './ui/Card';
import InfoBox from './ui/InfoBox';
import NumberField from './ui/NumberField';
import StatusChip from './ui/StatusChip';
import TextField from './ui/TextField';
import { ChevronLeftIcon } from './ui/icons';

const typeNames = {
    inbound: 'Delivery',
    outbound: 'Pickup',
};

const underway = ['booked', 'arrived', 'in_progress'];

function describeEvent(event) {
    if (event.from_status === null) {
        return 'Booked';
    }

    if (event.from_status === event.to_status) {
        return 'Changed';
    }

    return getStatusLabel(event.to_status);
}

export default function BookingDetail({ booking, onBack, onChanged }) {
    const [action, setAction] = useState('none');
    const [reason, setReason] = useState('');
    const [pallets, setPallets] = useState(booking.pallets);
    const [message, setMessage] = useState(null);
    const [working, setWorking] = useState(false);

    const mayBeChanged =
        booking.status === 'booked' && new Date(booking.change_deadline_at ?? booking.starts_at) > new Date();

    async function cancelBooking() {
        setMessage(null);
        setWorking(true);

        try {
            await csrf();

            const response = await api.post(`/bookings/${booking.id}/cancel`, {
                reason: reason === '' ? null : reason,
            });

            setAction('none');
            onChanged(response.data.data ?? response.data);
        } catch (failure) {
            setMessage(failure.response?.data?.message ?? 'The booking could not be cancelled.');
        } finally {
            setWorking(false);
        }
    }

    async function moveBooking(slot) {
        setMessage(null);
        setWorking(true);

        try {
            await csrf();

            const response = await api.patch(`/bookings/${booking.id}`, {
                starts_at: slot.starts_at,
                pallets,
            });

            setAction('none');
            onChanged(response.data.data ?? response.data);
        } catch (failure) {
            setMessage(failure.response?.data?.message ?? 'The booking could not be changed.');
        } finally {
            setWorking(false);
        }
    }

    return (
        <div className="space-y-6">
            <button
                type="button"
                onClick={onBack}
                className="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:text-brand-800"
            >
                <ChevronLeftIcon className="size-4" />
                My bookings
            </button>

            <div>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-3xl font-bold tracking-tight text-ink">
                        {typeNames[booking.type] ?? booking.type} · {formatLongDate(booking.starts_at)}
                    </h1>

                    <StatusChip status={booking.status} type={booking.type} />
                </div>

                <p className="mt-2 text-[15px] text-muted">
                    Booking #{booking.id} · Order number {booking.order_number}
                </p>
            </div>

            {message && <Alert>{message}</Alert>}

            {underway.includes(booking.status) && <DockStatus booking={booking} />}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <Card title="Details">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-[15px]">
                        <div>
                            <dt className="text-sm text-muted">Type</dt>
                            <dd className="font-semibold text-ink">{typeNames[booking.type] ?? booking.type}</dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Pallets</dt>
                            <dd className="font-semibold text-ink" data-numeric>
                                {booking.pallets}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Vehicle</dt>
                            <dd className="font-mono font-semibold text-ink">{booking.license_plate ?? '—'}</dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Driver phone</dt>
                            <dd className="font-semibold text-ink">{booking.driver_phone ?? '—'}</dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Order number</dt>
                            <dd className="font-semibold text-ink">{booking.order_number}</dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Dock</dt>
                            <dd className="font-semibold text-ink">Dock {booking.dock_number}</dd>
                        </div>

                        <div>
                            <dt className="text-sm text-muted">Slot</dt>
                            <dd className="font-semibold text-ink" data-numeric>
                                {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)}
                            </dd>
                        </div>

                        {booking.cancel_reason && (
                            <div className="col-span-2">
                                <dt className="text-sm text-muted">Reason for the cancellation</dt>
                                <dd className="font-semibold text-ink">{booking.cancel_reason}</dd>
                            </div>
                        )}
                    </dl>

                    {action === 'none' && (
                        <div className="mt-6">
                            {mayBeChanged ? (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button variant="secondary" size={40} onClick={() => setAction('move')}>
                                        Change this booking
                                    </Button>

                                    <Button variant="danger" size={40} onClick={() => setAction('cancel')}>
                                        Cancel this booking
                                    </Button>
                                </div>
                            ) : (
                                <InfoBox>
                                    {booking.status === 'booked'
                                        ? 'Changing and cancelling is possible up to three hours before the slot. Please call the warehouse for anything later than that.'
                                        : `A booking that is ${getStatusLabel(booking.status).toLowerCase()} cannot be changed any more.`}
                                </InfoBox>
                            )}
                        </div>
                    )}

                    {action === 'cancel' && (
                        <div className="mt-6 space-y-3 rounded-tile border border-danger-700 bg-danger-50 p-4">
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

                                <Button variant="secondary" size={40} onClick={() => setAction('none')}>
                                    Keep it
                                </Button>
                            </div>
                        </div>
                    )}

                    {action === 'move' && (
                        <div className="mt-6 space-y-4">
                            <NumberField
                                id="move_pallets"
                                label="Pallets"
                                min="1"
                                max="70"
                                value={pallets}
                                onChange={(event) => setPallets(Number(event.target.value))}
                            />

                            <SlotPicker type={booking.type} pallets={pallets} onPick={moveBooking} />

                            <Button variant="secondary" size={40} onClick={() => setAction('none')}>
                                Leave it
                            </Button>
                        </div>
                    )}
                </Card>

                <Card title="History">
                    {(booking.history ?? []).length === 0 ? (
                        <p className="text-sm text-muted">Nothing has happened to this booking yet.</p>
                    ) : (
                        <ol className="space-y-5">
                            {booking.history.map((event, index) => (
                                <li key={`${event.created_at}-${index}`} className="flex gap-3">
                                    <span className="mt-1.5 flex flex-col items-center">
                                        <span
                                            aria-hidden="true"
                                            className={`size-3 rounded-full ${
                                                index === 0 ? 'bg-brand-700' : 'bg-brand-300'
                                            }`}
                                        />
                                        {index < booking.history.length - 1 && (
                                            <span aria-hidden="true" className="mt-1 w-px flex-1 bg-line" />
                                        )}
                                    </span>

                                    <div>
                                        <p className="font-semibold text-ink">{describeEvent(event)}</p>
                                        <p className="text-sm text-muted">
                                            {event.by ?? 'KiDas'} · {formatDateTime(event.created_at)}
                                        </p>
                                        {event.note && <p className="mt-1 text-sm text-ink">{event.note}</p>}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>
            </div>
        </div>
    );
}
