import { useEffect, useState } from 'react';
import api from '../lib/api';
import { formatDate, formatTime } from '../lib/datetime';
import { getBlockPosition, getHourMarks, getQuarterCount } from '../lib/timeline';

const typeClasses = {
    inbound: 'bg-sky-100 text-sky-900 border-sky-300',
    outbound: 'bg-amber-100 text-amber-900 border-amber-300',
};

const statusClasses = {
    booked: '',
    arrived: 'ring-2 ring-amber-500',
    in_progress: 'ring-2 ring-blue-500',
    completed: 'opacity-60',
    no_show: 'line-through opacity-70',
};

function berlinToday() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Berlin' }).format(new Date());
}

function shiftDate(date, days) {
    const moved = new Date(`${date}T12:00:00Z`);

    moved.setUTCDate(moved.getUTCDate() + days);

    return moved.toISOString().slice(0, 10);
}

export default function Schedule() {
    const [date, setDate] = useState(berlinToday());
    const [schedule, setSchedule] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let active = true;

        setLoading(true);
        setError(null);

        api.get(`/schedule?date=${date}`)
            .then((response) => {
                if (active) {
                    setSchedule(response.data.data ?? response.data);
                }
            })
            .catch(() => {
                if (active) {
                    setError('The schedule could not be loaded.');
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
    }, [date]);

    const quarters = schedule === null ? 0 : getQuarterCount(schedule.opens_at, schedule.closes_at);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">Dock schedule</h1>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setDate(shiftDate(date, -1))}
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                    >
                        Previous day
                    </button>

                    <button
                        type="button"
                        onClick={() => setDate(berlinToday())}
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                    >
                        Today
                    </button>

                    <button
                        type="button"
                        onClick={() => setDate(shiftDate(date, 1))}
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium"
                    >
                        Next day
                    </button>
                </div>
            </div>

            {loading && <p className="text-sm text-slate-500">Loading the schedule…</p>}

            {error !== null && (
                <p role="alert" className="text-sm text-red-600">
                    {error}
                </p>
            )}

            {! loading && error === null && schedule !== null && (
                <>
                    <p className="text-sm text-slate-600">
                        {formatDate(schedule.opens_at)} · {formatTime(schedule.opens_at)} –{' '}
                        {formatTime(schedule.closes_at)}
                        {! schedule.is_released && ' · not released for booking'}
                        {schedule.is_closed && ' · closed'}
                    </p>

                    <div className="overflow-x-auto">
                        <div className="min-w-[48rem] space-y-1">
                            <div
                                className="grid gap-px text-xs text-slate-500"
                                style={{ gridTemplateColumns: `6rem repeat(${quarters}, minmax(0.75rem, 1fr))` }}
                            >
                                <div />
                                {getHourMarks(schedule.opens_at, schedule.closes_at).map((mark) => (
                                    <div
                                        key={mark.iso}
                                        style={{ gridColumn: `${mark.position.column + 1} / span 4` }}
                                    >
                                        {formatTime(mark.iso)}
                                    </div>
                                ))}
                            </div>

                            {schedule.docks.map((dock) => (
                                <div
                                    key={dock.number}
                                    className="grid items-center gap-px"
                                    style={{ gridTemplateColumns: `6rem repeat(${quarters}, minmax(0.75rem, 1fr))` }}
                                >
                                    <div
                                        className={`pr-2 text-sm font-medium ${dock.is_active ? '' : 'text-slate-400'}`}
                                    >
                                        Dock {dock.number}
                                        {! dock.is_active && <span className="ml-1 text-xs">(off)</span>}
                                    </div>

                                    <div
                                        className="h-10 rounded bg-slate-50"
                                        style={{ gridColumn: `2 / span ${Math.max(1, quarters)}` }}
                                    />

                                    {dock.bookings.map((booking) => {
                                        const position = getBlockPosition(
                                            schedule.opens_at,
                                            schedule.closes_at,
                                            booking.starts_at,
                                            booking.ends_at
                                        );

                                        return (
                                            <div
                                                key={booking.id}
                                                title={`${formatTime(booking.starts_at)} ${booking.company ?? ''} ${booking.license_plate}`}
                                                className={`overflow-hidden rounded border px-1 py-1 text-xs whitespace-nowrap ${typeClasses[booking.type]} ${statusClasses[booking.status] ?? ''}`}
                                                style={{
                                                    gridColumn: `${position.column + 1} / span ${position.span}`,
                                                    gridRow: 1,
                                                }}
                                            >
                                                {formatTime(booking.starts_at)} {booking.company}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
