import { useEffect, useState } from 'react';
import api from '../lib/api';
import { formatDate, formatDateTime, formatTime } from '../lib/datetime';

const statusClasses = {
    booked: 'bg-slate-100 text-slate-700',
    arrived: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    no_show: 'bg-red-100 text-red-800',
};

function StatusBadge({ status }) {
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusClasses[status] ?? 'bg-slate-100 text-slate-700'}`}
        >
            {status.replace('_', ' ')}
        </span>
    );
}

export default function Bookings() {
    const [bookings, setBookings] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let active = true;

        setLoading(true);

        api.get(`/bookings?page=${page}`)
            .then((response) => {
                if (active) {
                    setBookings(response.data.data ?? []);
                    setMeta(response.data.meta ?? null);
                }
            })
            .catch(() => {
                if (active) {
                    setError('Your bookings could not be loaded.');
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [page]);

    async function openBooking(booking) {
        setError(null);

        try {
            const response = await api.get(`/bookings/${booking.id}`);

            setSelected(response.data.data ?? response.data);
        } catch {
            setError('This booking could not be loaded.');
        }
    }

    if (loading) {
        return <p className="text-sm text-slate-500">Loading bookings…</p>;
    }

    if (selected !== null) {
        return (
            <div className="max-w-2xl space-y-4">
                <button
                    type="button"
                    onClick={() => setSelected(null)}
                    className="text-sm font-medium text-slate-600 hover:underline"
                >
                    ← Back to the list
                </button>

                <h1 className="text-2xl font-semibold tracking-tight">
                    {formatDate(selected.starts_at)}
                </h1>

                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt className="text-slate-500">Time</dt>
                    <dd>
                        {formatTime(selected.starts_at)} – {formatTime(selected.ends_at)}
                    </dd>

                    <dt className="text-slate-500">Status</dt>
                    <dd>
                        <StatusBadge status={selected.status} />
                    </dd>

                    <dt className="text-slate-500">Dock</dt>
                    <dd>{selected.dock_number}</dd>

                    <dt className="text-slate-500">Type</dt>
                    <dd>{selected.type}</dd>

                    <dt className="text-slate-500">Pallets</dt>
                    <dd>{selected.pallets}</dd>

                    <dt className="text-slate-500">Order number</dt>
                    <dd>{selected.order_number}</dd>

                    <dt className="text-slate-500">License plate</dt>
                    <dd className="font-mono">{selected.license_plate}</dd>

                    <dt className="text-slate-500">Driver phone</dt>
                    <dd>{selected.driver_phone}</dd>
                </dl>

                <section>
                    <h2 className="text-base font-semibold">History</h2>

                    <ul className="mt-2 space-y-1 text-sm text-slate-600">
                        {(selected.history ?? []).map((entry, index) => (
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

    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-semibold tracking-tight">My bookings</h1>

            {error && (
                <p role="alert" className="text-sm text-red-600">
                    {error}
                </p>
            )}

            {bookings.length === 0 ? (
                <p className="text-sm text-slate-500">You have not booked a slot yet.</p>
            ) : (
                <ul className="divide-y divide-slate-200 border-y border-slate-200">
                    {bookings.map((booking) => (
                        <li key={booking.id}>
                            <button
                                type="button"
                                onClick={() => openBooking(booking)}
                                className="flex w-full items-center justify-between gap-4 py-3 text-left text-sm hover:bg-slate-50"
                            >
                                <span className="font-medium">
                                    {formatDate(booking.starts_at)}, {formatTime(booking.starts_at)}
                                </span>

                                <span className="text-slate-500">
                                    {booking.type} · {booking.pallets} pallets · dock {booking.dock_number}
                                </span>

                                <StatusBadge status={booking.status} />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {meta !== null && meta.last_page > 1 && (
                <div className="flex items-center gap-3 text-sm">
                    <button
                        type="button"
                        disabled={meta.current_page === 1}
                        onClick={() => setPage(meta.current_page - 1)}
                        className="rounded-md border border-slate-300 px-3 py-1.5 font-medium disabled:opacity-40"
                    >
                        Previous
                    </button>

                    <span className="text-slate-500">
                        Page {meta.current_page} of {meta.last_page}
                    </span>

                    <button
                        type="button"
                        disabled={meta.current_page === meta.last_page}
                        onClick={() => setPage(meta.current_page + 1)}
                        className="rounded-md border border-slate-300 px-3 py-1.5 font-medium disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
}
