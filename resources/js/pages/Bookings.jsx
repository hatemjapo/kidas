import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import BookingDetail from '../components/BookingDetail';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import Card from '../components/ui/Card';
import StatusChip from '../components/ui/StatusChip';
import api from '../lib/api';
import { formatDate, formatTime } from '../lib/datetime';

const typeNames = {
    inbound: 'Delivery',
    outbound: 'Pickup',
};

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
        return <p className="text-sm text-muted">Loading bookings…</p>;
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
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight text-ink">My bookings</h1>
                <p className="mt-2 text-[15px] text-muted">Newest first. Open one to see its history and live status.</p>
            </div>

            {error && <Alert>{error}</Alert>}

            {bookings.length === 0 ? (
                <Card>
                    <p className="text-[15px] text-ink">You have not booked a slot yet.</p>

                    <Link
                        to="/book"
                        className="mt-3 inline-block text-sm font-semibold text-brand-700 hover:text-brand-800"
                    >
                        Book a slot
                    </Link>
                </Card>
            ) : (
                <ul className="space-y-3">
                    {bookings.map((booking) => (
                        <li key={booking.id}>
                            <button
                                type="button"
                                onClick={() => openBooking(booking)}
                                className="flex w-full flex-wrap items-center justify-between gap-4 rounded-card border border-line bg-white px-5 py-4 text-left hover:border-brand-500"
                            >
                                <span>
                                    <span className="block font-semibold text-ink" data-numeric>
                                        {formatDate(booking.starts_at)}, {formatTime(booking.starts_at)} –{' '}
                                        {formatTime(booking.ends_at)}
                                    </span>
                                    <span className="block text-sm text-muted">
                                        {typeNames[booking.type] ?? booking.type} · {booking.pallets} pallets · dock{' '}
                                        {booking.dock_number} · {booking.order_number}
                                    </span>
                                </span>

                                <StatusChip status={booking.status} type={booking.type} />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {meta !== null && meta.last_page > 1 && (
                <div className="flex items-center gap-4">
                    <Button
                        variant="secondary"
                        size={40}
                        disabled={meta.current_page === 1}
                        onClick={() => setPage(meta.current_page - 1)}
                    >
                        Previous
                    </Button>

                    <span className="text-sm text-muted" data-numeric>
                        Page {meta.current_page} of {meta.last_page}
                    </span>

                    <Button
                        variant="secondary"
                        size={40}
                        disabled={meta.current_page === meta.last_page}
                        onClick={() => setPage(meta.current_page + 1)}
                    >
                        Next
                    </Button>
                </div>
            )}
        </div>
    );
}
