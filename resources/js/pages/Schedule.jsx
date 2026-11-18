import { useEffect, useState } from 'react';
import ArrivalPanel from '../components/ArrivalPanel';
import Modal from '../components/Modal';
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
    const [reload, setReload] = useState(0);
    const [selected, setSelected] = useState(null);
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
    }, [date, reload]);

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

            {selected !== null && (
                <Modal onClose={() => setSelected(null)}>
                    <ArrivalPanel
                        booking={selected}
                        onClose={() => setSelected(null)}
                        onDone={(changed) => {
                            setSelected({ ...selected, ...changed });
                            setReload((count) => count + 1);
                        }}
                    />
                </Modal>
            )}

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
                        <div
                            className="grid min-w-[48rem] gap-px"
                            style={{
                                gridTemplateColumns: `4rem repeat(${schedule.docks.length}, minmax(4.5rem, 1fr))`,
                                gridTemplateRows: `2rem repeat(${quarters}, 1.1rem)`,
                            }}
                        >
                            {schedule.docks.map((dock, index) => (
                                <div
                                    key={`head-${dock.number}`}
                                    style={{ gridColumn: index + 2, gridRow: 1 }}
                                    className={`text-center text-sm font-medium ${dock.is_active ? '' : 'text-slate-400'}`}
                                >
                                    Dock {dock.number}
                                    {! dock.is_active && <span className="ml-1 text-xs">(off)</span>}
                                </div>
                            ))}

                            {getHourMarks(schedule.opens_at, schedule.closes_at).map((mark) => (
                                <div
                                    key={mark.iso}
                                    style={{ gridColumn: 1, gridRow: `${mark.position.start + 2} / span 4` }}
                                    className="pr-2 text-right text-xs text-slate-500"
                                >
                                    {formatTime(mark.iso)}
                                </div>
                            ))}

                            {schedule.docks.map((dock, index) => (
                                <div
                                    key={`lane-${dock.number}`}
                                    style={{
                                        gridColumn: index + 2,
                                        gridRow: `2 / span ${Math.max(1, quarters)}`,
                                    }}
                                    className="rounded bg-slate-50"
                                />
                            ))}

                            {schedule.docks.flatMap((dock, index) =>
                                dock.bookings.map((booking) => {
                                    const position = getBlockPosition(
                                        schedule.opens_at,
                                        schedule.closes_at,
                                        booking.starts_at,
                                        booking.ends_at
                                    );

                                    return (
                                        <button
                                            type="button"
                                            key={booking.id}
                                            onClick={() => setSelected({ ...booking, dock_number: dock.number })}
                                            title={`${formatTime(booking.starts_at)} ${booking.company ?? ''} ${booking.license_plate}`}
                                            className={`overflow-hidden rounded border px-1 text-left text-xs leading-tight ${typeClasses[booking.type]} ${statusClasses[booking.status] ?? ''}`}
                                            style={{
                                                gridColumn: index + 2,
                                                gridRow: `${position.start + 2} / span ${position.span}`,
                                            }}
                                        >
                                            {formatTime(booking.starts_at)} {booking.company}
                                            {booking.plate_mismatch && ! booking.plate_verified && ' ⚠'}
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
