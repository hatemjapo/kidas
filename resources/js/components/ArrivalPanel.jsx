import { useEffect, useState } from 'react';
import api, { csrf } from '../lib/api';
import { formatTime } from '../lib/datetime';
import StatusBadge from './StatusBadge';

export default function ArrivalPanel({ booking, onDone, onClose }) {
    const [plate, setPlate] = useState(booking.arrived_plate ?? '');
    const [note, setNote] = useState('');
    const [releasing, setReleasing] = useState(false);
    const [marking, setMarking] = useState(false);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [working, setWorking] = useState(false);

    useEffect(() => {
        setPlate(booking.arrived_plate ?? '');
    }, [booking.arrived_plate]);

    async function send(path, payload, request = api.post) {
        setErrors({});
        setMessage(null);
        setWorking(true);

        try {
            await csrf();

            const response = await request(`/bookings/${booking.id}/${path}`, payload);

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

    return (
        <section className="space-y-3 rounded-md border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-semibold">
                        {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)} · dock{' '}
                        {booking.dock_number ?? ''} · {booking.company}
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        {booking.type} · {booking.pallets} pallets · {booking.order_number} · expected{' '}
                        <span className="font-mono">{booking.license_plate}</span>
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <StatusBadge status={booking.status} />

                    <button
                        type="button"
                        onClick={onClose}
                        className="text-sm font-medium text-slate-600 hover:underline"
                    >
                        Close
                    </button>
                </div>
            </div>

            {message && (
                <p role="alert" className="text-sm text-red-600">
                    {message}
                </p>
            )}

            {(booking.status === 'booked' || booking.status === 'arrived') && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (booking.status === 'booked') {
                            send('arrival', { license_plate: plate });
                        } else {
                            send('arrival', { license_plate: plate }, api.patch);
                        }
                    }}
                    className="flex flex-wrap items-end gap-3"
                    noValidate
                >
                    <div className="flex flex-col gap-1">
                        <label htmlFor="arrived_plate" className="text-sm font-medium text-slate-700">
                            License plate that arrived
                        </label>
                        <input
                            id="arrived_plate"
                            value={plate}
                            onChange={(event) => setPlate(event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.license_plate && (
                            <p className="text-sm text-red-600">{errors.license_plate[0]}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={working}
                        className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {booking.status === 'booked' ? 'Register the arrival' : 'Correct the plate'}
                    </button>
                </form>
            )}

            {booking.arrived_at !== null && booking.arrived_at !== undefined && (
                <p className="text-sm text-slate-600">
                    Arrived at {formatTime(booking.arrived_at)} with{' '}
                    <span className="font-mono">{booking.arrived_plate}</span>.
                </p>
            )}

            {booking.plate_mismatch && ! booking.plate_verified && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        send('plate-verification', { note: note === '' ? null : note });
                    }}
                    className="space-y-3 rounded-md border border-amber-300 bg-amber-50 p-3"
                    noValidate
                >
                    <p role="status" className="text-sm text-amber-900">
                        The plate does not match the booking. Please check with the company before anything is
                        unloaded.
                    </p>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="verify_note" className="text-sm font-medium text-amber-900">
                            What did they say? (optional)
                        </label>
                        <input
                            id="verify_note"
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            className="rounded-md border border-amber-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={working}
                        className="rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        Confirm the plate
                    </button>
                </form>
            )}

            {booking.status === 'booked' && new Date(booking.ends_at) <= new Date() && ! marking && (
                <button
                    type="button"
                    disabled={working}
                    onClick={() => setMarking(true)}
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 disabled:opacity-50"
                >
                    Nobody turned up
                </button>
            )}

            {booking.status === 'booked' && marking && (
                <div className="space-y-3 rounded-md border border-slate-300 bg-slate-50 p-3">
                    <p className="text-sm text-slate-700">
                        Mark this booking as a no-show? The slot is then free for somebody else.
                    </p>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            disabled={working}
                            onClick={() => {
                                setMarking(false);
                                send('no-show', {});
                            }}
                            className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Yes, nobody turned up
                        </button>

                        <button
                            type="button"
                            onClick={() => setMarking(false)}
                            className="text-sm font-medium text-slate-600 hover:underline"
                        >
                            Not yet
                        </button>
                    </div>
                </div>
            )}

            {booking.status === 'arrived' && (
                <button
                    type="button"
                    disabled={working || (booking.plate_mismatch && ! booking.plate_verified)}
                    onClick={() => send('handling', {})}
                    className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Start the handling
                </button>
            )}

            {booking.started_at !== null && booking.started_at !== undefined && (
                <p className="text-sm text-slate-600">
                    Handling started at {formatTime(booking.started_at)}.
                </p>
            )}

            {booking.status === 'in_progress' && ! releasing && (
                <button
                    type="button"
                    disabled={working}
                    onClick={() => setReleasing(true)}
                    className="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    The dock is free
                </button>
            )}

            {booking.status === 'in_progress' && releasing && (
                <div className="space-y-3 rounded-md border border-emerald-300 bg-emerald-50 p-3">
                    <p className="text-sm text-emerald-900">
                        Release dock {booking.dock_number} and finish this booking? This cannot be undone.
                    </p>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            disabled={working}
                            onClick={() => {
                                setReleasing(false);
                                send('completion', {});
                            }}
                            className="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Yes, the dock is free
                        </button>

                        <button
                            type="button"
                            onClick={() => setReleasing(false)}
                            className="text-sm font-medium text-slate-600 hover:underline"
                        >
                            Not yet
                        </button>
                    </div>
                </div>
            )}

            {booking.completed_at !== null && booking.completed_at !== undefined && (
                <p className="text-sm text-slate-600">
                    Dock released at {formatTime(booking.completed_at)}.
                </p>
            )}

            {booking.plate_mismatch && booking.plate_verified && (
                <p className="text-sm text-slate-600">
                    The plate was different and has been confirmed with the company.
                </p>
            )}
        </section>
    );
}
