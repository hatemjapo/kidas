import { useEffect, useState } from 'react';
import BookingDetail from '../components/BookingDetail';
import StatusBadge from '../components/StatusBadge';
import api from '../lib/api';
import { formatDate, formatTime } from '../lib/datetime';

export default function Bookings() {
    const [bookings, setBookings] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [reload, setReload] = useState(0);
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
    }, [page, reload]);

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
            <BookingDetail
                booking={selected}
                onBack={() => setSelected(null)}
                onChanged={(changed) => {
                    setSelected({ ...selected, ...changed });
                    setReload((count) => count + 1);
                }}
            />
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
