import { useState } from 'react';
import api, { csrf } from '../lib/api';
import { formatDate, formatDateTime, formatTime } from '../lib/datetime';
import DockStatus from './DockStatus';
import SlotPicker from './SlotPicker';
import StatusBadge from './StatusBadge';

export default function BookingDetail({ booking, onBack, onChanged }) {
    const [action, setAction] = useState('none');
    const [reason, setReason] = useState('');
    const [pallets, setPallets] = useState(booking.pallets);
    const [message, setMessage] = useState(null);
    const [working, setWorking] = useState(false);

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
            setMessage(failure.response?.data?.message ?? 'The booking could not be moved.');
        } finally {
            setWorking(false);
        }
    }

    return (
        <div className="max-w-2xl space-y-4">
            <button
                type="button"
                onClick={onBack}
                className="text-sm font-medium text-slate-600 hover:underline"
            >
                ← Back to the list
            </button>

            <h1 className="text-2xl font-semibold tracking-tight">{formatDate(booking.starts_at)}</h1>

            {message && (
                <p
                    role="alert"
                    className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                >
                    {message}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-2 text-sm">
                <dt className="text-slate-500">Time</dt>
                <dd>
                    {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)}
                </dd>

                <dt className="text-slate-500">Status</dt>
                <dd>
                    <StatusBadge status={booking.status} />
                </dd>

                <dt className="text-slate-500">Dock</dt>
                <dd>{booking.dock_number}</dd>

                <dt className="text-slate-500">Type</dt>
                <dd>{booking.type}</dd>

                <dt className="text-slate-500">Pallets</dt>
                <dd>{booking.pallets}</dd>

                <dt className="text-slate-500">Order number</dt>
                <dd>{booking.order_number}</dd>

                <dt className="text-slate-500">License plate</dt>
                <dd className="font-mono">{booking.license_plate}</dd>

                <dt className="text-slate-500">Driver phone</dt>
                <dd>{booking.driver_phone}</dd>
            </dl>

            <DockStatus booking={booking} />

            {booking.status === 'booked' && action === 'none' && (
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setAction('move')}
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                    >
                        Change this booking
                    </button>

                    <button
                        type="button"
                        onClick={() => setAction('cancel')}
                        className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700"
                    >
                        Cancel this booking
                    </button>
                </div>
            )}

            {action === 'cancel' && (
                <section className="space-y-3 rounded-md border border-slate-200 p-4">
                    <h2 className="text-base font-semibold">Cancel this booking</h2>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="reason" className="text-sm font-medium text-slate-700">
                            Reason (optional)
                        </label>
                        <textarea
                            id="reason"
                            rows="2"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            disabled={working}
                            onClick={cancelBooking}
                            className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Cancel the booking
                        </button>

                        <button
                            type="button"
                            onClick={() => setAction('none')}
                            className="text-sm font-medium text-slate-600 hover:underline"
                        >
                            Keep it
                        </button>
                    </div>
                </section>
            )}

            {action === 'move' && (
                <section className="space-y-3 rounded-md border border-slate-200 p-4">
                    <h2 className="text-base font-semibold">Change this booking</h2>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="move_pallets" className="text-sm font-medium text-slate-700">
                            Pallets
                        </label>
                        <input
                            id="move_pallets"
                            type="number"
                            min="1"
                            max="70"
                            value={pallets}
                            onChange={(event) => setPallets(Number(event.target.value))}
                            className="w-24 rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <SlotPicker type={booking.type} pallets={pallets} onPick={moveBooking} />

                    <button
                        type="button"
                        onClick={() => setAction('none')}
                        className="text-sm font-medium text-slate-600 hover:underline"
                    >
                        Keep the current slot
                    </button>
                </section>
            )}

            <section>
                <h2 className="text-base font-semibold">History</h2>

                <ul className="mt-2 space-y-1 text-sm text-slate-600">
                    {(booking.history ?? []).map((entry, index) => (
                        <li key={index}>
                            {formatDateTime(entry.created_at)} —{' '}
                            {entry.from_status ? `${entry.from_status} → ` : ''}
                            {entry.to_status}
                            {entry.by ? ` by ${entry.by}` : ''}
                            {entry.note ? ` (${entry.note})` : ''}
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}
