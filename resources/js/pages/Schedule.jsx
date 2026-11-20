import { useEffect, useState } from 'react';
import ArrivalPanel from '../components/ArrivalPanel';
import ScheduleLegend from '../components/ScheduleLegend';
import Button from '../components/ui/Button';
import Dialog from '../components/ui/Dialog';
import IconButton from '../components/ui/IconButton';
import StatTile from '../components/ui/StatTile';
import { ChevronLeftIcon, ChevronRightIcon } from '../components/ui/icons';
import api from '../lib/api';
import { formatLongDate, formatTime } from '../lib/datetime';
import { getStatusStyle, getDockColumnStyle, plateMismatchStyle } from '../lib/statusStyles';
import { getBlockPosition, getHourMarks, getQuarterCount } from '../lib/timeline';

const usageNames = {
    inbound: 'Deliveries',
    outbound: 'Pickups',
};

function berlinToday() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Berlin' }).format(new Date());
}

function shiftDate(date, days) {
    const moved = new Date(`${date}T12:00:00Z`);

    moved.setUTCDate(moved.getUTCDate() + days);

    return moved.toISOString().slice(0, 10);
}

function describeUsage(dock) {
    if (! dock.is_active) {
        return 'Out of service';
    }

    const names = [dock.usage?.early, dock.usage?.late].map((usage) => usageNames[usage] ?? null);

    if (names[0] === null && names[1] === null) {
        return 'Not released';
    }

    return names[0] === names[1] ? names[0] : names.map((name) => name ?? '—').join(' / ');
}

function countBookings(schedule) {
    const bookings = schedule.docks.flatMap((dock) => dock.bookings);
    const count = (...states) => bookings.filter((booking) => states.includes(booking.status)).length;

    return {
        expected: count('booked'),
        atTheDock: count('arrived', 'in_progress'),
        completed: count('completed'),
        noShow: count('no_show'),
    };
}

function getNowPosition(schedule) {
    const now = new Date();

    if (new Date(schedule.opens_at) > now || new Date(schedule.closes_at) < now) {
        return null;
    }

    return getBlockPosition(schedule.opens_at, schedule.closes_at, now.toISOString(), now.toISOString()).start;
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
    const counts = schedule === null ? null : countBookings(schedule);
    const nowAt = schedule === null || date !== berlinToday() ? null : getNowPosition(schedule);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold tracking-wide text-brand-700 uppercase">Dock schedule</p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight text-ink">
                        {schedule === null ? '—' : formatLongDate(schedule.opens_at)}
                    </h1>
                </div>

                <div className="flex items-center gap-2">
                    <IconButton label="Previous day" onClick={() => setDate(shiftDate(date, -1))}>
                        <ChevronLeftIcon />
                    </IconButton>

                    <Button variant="secondary" size={40} onClick={() => setDate(berlinToday())}>
                        Today
                    </Button>

                    <IconButton label="Next day" onClick={() => setDate(shiftDate(date, 1))}>
                        <ChevronRightIcon />
                    </IconButton>
                </div>
            </div>

            {loading && <p className="text-sm text-muted">Loading the schedule…</p>}

            {error !== null && (
                <p role="alert" className="text-sm text-danger-700">
                    {error}
                </p>
            )}

            {! loading && error === null && schedule !== null && (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatTile label="Expected" value={counts.expected} />
                        <StatTile label="At the dock or waiting" value={counts.atTheDock} tone="brand" />
                        <StatTile label="Completed" value={counts.completed} />
                        <StatTile label="No show" value={counts.noShow} tone={counts.noShow > 0 ? 'danger' : 'neutral'} />
                    </div>

                    <ScheduleLegend />

                    {(! schedule.is_released || schedule.is_closed) && (
                        <p className="text-sm text-muted">
                            {schedule.is_closed
                                ? 'The warehouse is closed on this day.'
                                : 'This day has not been released for booking.'}
                        </p>
                    )}

                    <div className="overflow-x-auto rounded-card border border-line bg-white">
                        <div
                            className="grid min-w-[56rem]"
                            style={{
                                gridTemplateColumns: `5rem repeat(${schedule.docks.length}, minmax(6.5rem, 1fr))`,
                                gridTemplateRows: `auto repeat(${quarters}, 1.15rem)`,
                            }}
                        >
                            {schedule.docks.map((dock, index) => (
                                <div
                                    key={`head-${dock.number}`}
                                    style={{ gridColumn: index + 2, gridRow: 1 }}
                                    className="border-b border-line px-2 py-3 text-center"
                                >
                                    <p
                                        className={`text-sm font-bold ${
                                            dock.is_active ? 'text-ink' : 'text-muted'
                                        }`}
                                    >
                                        Dock {dock.number}
                                    </p>
                                    <p className="text-xs text-muted">{describeUsage(dock)}</p>
                                </div>
                            ))}

                            <div style={{ gridColumn: 1, gridRow: 1 }} className="border-b border-line" />

                            {schedule.docks.map((dock, index) => (
                                <div
                                    key={`lane-${dock.number}`}
                                    style={{ gridColumn: index + 2, gridRow: `2 / span ${Math.max(1, quarters)}` }}
                                    className={`border-l border-line ${getDockColumnStyle(dock.is_active).column}`}
                                />
                            ))}

                            {getHourMarks(schedule.opens_at, schedule.closes_at).map((mark) => (
                                <div
                                    key={`line-${mark.iso}`}
                                    style={{
                                        gridColumn: `2 / span ${schedule.docks.length}`,
                                        gridRow: mark.position.start + 2,
                                    }}
                                    className="border-t border-line"
                                />
                            ))}

                            {getHourMarks(schedule.opens_at, schedule.closes_at).map((mark) => (
                                <div
                                    key={`hour-${mark.iso}`}
                                    style={{ gridColumn: 1, gridRow: `${mark.position.start + 2} / span 4` }}
                                    className="-mt-2 pr-3 text-right text-xs text-muted"
                                >
                                    <time dateTime={mark.iso}>{formatTime(mark.iso)}</time>
                                </div>
                            ))}

                            {schedule.docks.flatMap((dock, index) =>
                                dock.bookings.map((booking) => {
                                    const position = getBlockPosition(
                                        schedule.opens_at,
                                        schedule.closes_at,
                                        booking.starts_at,
                                        booking.ends_at
                                    );

                                    const style = getStatusStyle(booking.status, booking.type);
                                    const roomy = position.span >= 3;

                                    return (
                                        <button
                                            type="button"
                                            key={booking.id}
                                            onClick={() => setSelected({ ...booking, dock_number: dock.number })}
                                            aria-label={`${formatTime(booking.starts_at)} to ${formatTime(booking.ends_at)}, dock ${dock.number}, ${booking.company ?? 'unknown company'}, ${booking.pallets} pallets, ${style.label}`}
                                            className={`m-0.5 overflow-hidden rounded-[10px] px-2 py-1 text-left text-xs leading-tight ${style.block}`}
                                            style={{
                                                gridColumn: index + 2,
                                                gridRow: `${position.start + 2} / span ${position.span}`,
                                            }}
                                        >
                                            <span className="block" data-numeric>
                                                {formatTime(booking.starts_at)}–{formatTime(booking.ends_at)}
                                            </span>

                                            <span className="block truncate font-semibold">{booking.company}</span>

                                            {roomy && (
                                                <span className="block">
                                                    {booking.pallets} pal. · {style.label}
                                                </span>
                                            )}

                                            {booking.plate_mismatch && ! booking.plate_verified && (
                                                <span
                                                    className={`mt-1 inline-block rounded-nav px-1 font-semibold ${plateMismatchStyle.chip}`}
                                                >
                                                    {plateMismatchStyle.label}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })
                            )}

                            {nowAt !== null && (
                                <div
                                    style={{ gridColumn: `1 / span ${schedule.docks.length + 1}`, gridRow: nowAt + 2 }}
                                    className="pointer-events-none relative"
                                >
                                    <span className="absolute inset-x-0 top-0 border-t-2 border-now" />
                                    <span className="absolute -top-2 left-0 rounded-nav bg-now px-1 text-xs font-semibold text-white">
                                        {formatTime(new Date().toISOString())}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}

            {selected !== null && (
                <Dialog
                    title={`${formatTime(selected.starts_at)} · Dock ${selected.dock_number} · ${selected.company ?? ''}`}
                    onClose={() => setSelected(null)}
                >
                    <ArrivalPanel
                        booking={selected}
                        onDone={(changed) => {
                            setSelected({ ...selected, ...changed });
                            setReload((count) => count + 1);
                        }}
                    />
                </Dialog>
            )}
        </div>
    );
}
