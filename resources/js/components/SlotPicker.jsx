import { useEffect, useState } from 'react';
import api from '../lib/api';
import { formatDate, formatTime } from '../lib/datetime';

export default function SlotPicker({ type, pallets, onPick }) {
    const [days, setDays] = useState([]);
    const [duration, setDuration] = useState(null);
    const [cursor, setCursor] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let active = true;

        setLoading(true);

        const query = new URLSearchParams({ type, pallets: String(pallets) });

        if (cursor !== null) {
            query.set(cursor.direction, cursor.date);
        }

        api.get(`/slots?${query.toString()}`)
            .then((response) => {
                if (! active) {
                    return;
                }

                const loaded = response.data.data ?? [];

                if (loaded.length === 0 && cursor !== null) {
                    setCursor(null);

                    return;
                }

                setDays(loaded);
                setDuration(response.data.meta?.duration_minutes ?? null);
            })
            .catch(() => {
                if (active) {
                    setError('The free slots could not be loaded.');
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
    }, [type, pallets, cursor]);

    if (loading) {
        return <p className="text-sm text-slate-500">Looking for free slots…</p>;
    }

    if (error !== null) {
        return (
            <p role="alert" className="text-sm text-red-600">
                {error}
            </p>
        );
    }

    return (
        <div className="space-y-4">
            {duration !== null && (
                <p className="text-sm text-slate-500">
                    {pallets} pallets block a dock for {duration} minutes.
                </p>
            )}

            {days.length === 0 ? (
                <p className="text-sm text-slate-500">No free slots for this load at the moment.</p>
            ) : (
                <>
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setCursor({ direction: 'before', date: days[0].date })}
                            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                        >
                            Earlier days
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                setCursor({ direction: 'after', date: days[days.length - 1].date })
                            }
                            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                        >
                            Later days
                        </button>
                    </div>

                    <div className="grid gap-4 md:grid-cols-5">
                        {days.map((day) => (
                            <section key={day.date}>
                                <h3 className="text-sm font-semibold">{formatDate(day.slots[0].starts_at)}</h3>

                                <ul className="mt-2 space-y-1">
                                    {day.slots.map((slot) => (
                                        <li key={slot.starts_at}>
                                            <button
                                                type="button"
                                                onClick={() => onPick(slot)}
                                                className="w-full rounded-md border border-slate-300 px-2 py-1 text-sm hover:border-slate-900"
                                            >
                                                {formatTime(slot.starts_at)}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
