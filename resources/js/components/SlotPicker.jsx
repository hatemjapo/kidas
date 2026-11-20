import { useEffect, useState } from 'react';
import api from '../lib/api';
import Card from './ui/Card';
import ChoiceTile from './ui/ChoiceTile';
import IconButton from './ui/IconButton';
import { ChevronLeftIcon, ChevronRightIcon } from './ui/icons';
import { formatTime } from '../lib/datetime';

const shifts = [
    { value: 'early', label: 'Early shift' },
    { value: 'late', label: 'Late shift' },
];

const weekdayFormatter = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', weekday: 'long' });
const dayFormatter = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit' });

function berlinToday() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Berlin' }).format(new Date());
}

function nameOfDay(day) {
    return day.date === berlinToday() ? 'Today' : weekdayFormatter.format(new Date(day.slots[0].starts_at));
}

function slotsOfShift(day, shift) {
    return day.slots.filter((slot) => slot.shift === shift);
}

export default function SlotPicker({ type, pallets, selected = null, onPick, onDuration }) {
    const [days, setDays] = useState([]);
    const [duration, setDuration] = useState(null);
    const [cursor, setCursor] = useState(null);
    const [chosenDate, setChosenDate] = useState(null);
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
                setChosenDate((date) => (loaded.some((day) => day.date === date) ? date : (loaded[0]?.date ?? null)));

                const minutes = response.data.meta?.duration_minutes ?? null;

                setDuration(minutes);
                onDuration?.(minutes);
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
        return <Card title="Choose a day">Looking for free slots…</Card>;
    }

    if (error !== null) {
        return (
            <Card title="Choose a day">
                <p role="alert" className="text-sm text-danger-700">
                    {error}
                </p>
            </Card>
        );
    }

    if (days.length === 0) {
        return (
            <Card title="Choose a day">
                <p className="text-sm text-muted">
                    No free start times for this load at the moment. Try another pallet count, or look at earlier days.
                </p>

                <button
                    type="button"
                    onClick={() => setCursor(null)}
                    className="mt-3 text-sm font-semibold text-brand-700 hover:text-brand-800"
                >
                    Back to the next days
                </button>
            </Card>
        );
    }

    const day = days.find((candidate) => candidate.date === chosenDate) ?? days[0];

    return (
        <Card
            title="Choose a day"
            action={
                <div className="flex items-center gap-2">
                    <IconButton
                        label="Earlier days"
                        onClick={() => setCursor({ direction: 'before', date: days[0].date })}
                    >
                        <ChevronLeftIcon />
                    </IconButton>

                    <IconButton
                        label="Later days"
                        onClick={() => setCursor({ direction: 'after', date: days[days.length - 1].date })}
                    >
                        <ChevronRightIcon />
                    </IconButton>
                </div>
            }
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                {days.map((candidate) => (
                    <ChoiceTile
                        key={candidate.date}
                        chosen={candidate.date === day.date}
                        onClick={() => setChosenDate(candidate.date)}
                        aria-label={`${nameOfDay(candidate)} ${dayFormatter.format(new Date(candidate.slots[0].starts_at))}, ${candidate.slots.length} free ${candidate.slots.length === 1 ? 'time' : 'times'}`}
                        className="text-center"
                    >
                        <span className="block text-xs font-semibold text-muted">{nameOfDay(candidate)}</span>
                        <span className="block text-lg font-bold" data-numeric>
                            {dayFormatter.format(new Date(candidate.slots[0].starts_at))}
                        </span>
                        <span className="block text-xs font-normal text-muted">
                            {candidate.slots.length} free {candidate.slots.length === 1 ? 'time' : 'times'}
                        </span>
                    </ChoiceTile>
                ))}
            </div>

            <hr className="my-5 border-line" />

            {shifts.map((shift) => {
                const slots = slotsOfShift(day, shift.value);

                return (
                    <section key={shift.value} className="mt-5 first:mt-0">
                        <h3 className="text-sm font-bold text-ink">
                            {shift.label}{' '}
                            {slots.length > 0 && (
                                <span className="font-normal text-muted" data-numeric>
                                    {formatTime(slots[0].starts_at)}–{formatTime(slots[slots.length - 1].ends_at)}
                                </span>
                            )}
                        </h3>

                        {slots.length === 0 ? (
                            <p className="mt-2 text-sm text-muted">No free start time left in this shift.</p>
                        ) : (
                            <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7">
                                {slots.map((slot) => (
                                    <ChoiceTile
                                        key={slot.starts_at}
                                        chosen={selected?.starts_at === slot.starts_at}
                                        onClick={() => onPick(slot)}
                                        className={`text-center ${
                                            selected?.starts_at === slot.starts_at
                                                ? 'bg-brand-700 text-white border-brand-700'
                                                : ''
                                        }`}
                                    >
                                        <span data-numeric>{formatTime(slot.starts_at)}</span>
                                    </ChoiceTile>
                                ))}
                            </div>
                        )}
                    </section>
                );
            })}

            {duration !== null && (
                <p className="mt-5 text-xs text-muted">
                    {pallets} pallets block a dock for {duration} minutes.
                </p>
            )}
        </Card>
    );
}
