import { useEffect, useState } from 'react';
import api from '../lib/api';
import { formatTime } from '../lib/datetime';

const waiting = {
    arrived: {
        headline: 'The warehouse has registered your arrival',
        detail: 'Please wait at the yard until the dock is ready for you.',
    },
    in_progress: {
        headline: 'Your lorry is being handled',
        detail: 'The warehouse is loading or unloading right now.',
    },
};

function getMinutes(booking) {
    return Math.round((new Date(booking.ends_at) - new Date(booking.starts_at)) / 60000);
}

function describe(booking, readiness) {
    if (waiting[booking.status] !== undefined) {
        return { ...waiting[booking.status], tone: 'neutral' };
    }

    if (readiness === null || ! readiness.window_open) {
        return {
            headline: `Your slot at dock ${booking.dock_number}`,
            detail: 'A quarter of an hour before your slot you will see here whether the dock is clear.',
            tone: 'neutral',
        };
    }

    if (readiness.dock_free) {
        return {
            headline: 'Your dock is free – you may arrive now',
            detail: `The lorry before you has been handled. Please drive straight to dock ${booking.dock_number}.`,
            tone: 'free',
        };
    }

    return {
        headline: 'Another lorry is still at your dock',
        detail:
            readiness.busy_until === null
                ? 'Please wait at the yard; the dock will be free shortly.'
                : `It should be free at about ${formatTime(readiness.busy_until)}.`,
        tone: 'busy',
    };
}

const tones = {
    free: { card: 'border-brand-500 bg-brand-50', tile: 'bg-brand-700 text-white', text: 'text-brand-800' },
    busy: { card: 'border-warn-700 bg-warn-50', tile: 'bg-warn-700 text-white', text: 'text-warn-700' },
    neutral: { card: 'border-line bg-white', tile: 'bg-brand-700 text-white', text: 'text-ink' },
};

export default function DockStatus({ booking }) {
    const [readiness, setReadiness] = useState(null);

    useEffect(() => {
        let active = true;
        let timer = null;

        function ask() {
            api.get(`/bookings/${booking.id}/status`)
                .then((response) => {
                    if (! active) {
                        return;
                    }

                    const answer = response.data.data ?? response.data;

                    setReadiness(answer);

                    if (answer.window_open) {
                        timer = setTimeout(ask, answer.poll_seconds * 1000);
                    }
                })
                .catch(() => {
                    if (active) {
                        setReadiness(null);
                    }
                });
        }

        ask();

        return () => {
            active = false;

            if (timer !== null) {
                clearTimeout(timer);
            }
        };
    }, [booking.id]);

    const state = describe(booking, readiness);
    const tone = tones[state.tone];

    return (
        <section
            aria-live="polite"
            className={`flex flex-col gap-6 rounded-panel border p-6 sm:flex-row sm:items-center ${tone.card}`}
        >
            <div
                className={`flex size-28 shrink-0 flex-col items-center justify-center rounded-card ${tone.tile}`}
            >
                <span className="text-xs font-semibold tracking-widest uppercase">Dock</span>
                <span className="text-4xl font-bold" data-numeric>
                    {booking.dock_number}
                </span>
            </div>

            <div className="flex-1">
                <h2 className={`text-xl font-bold ${tone.text}`}>{state.headline}</h2>
                <p className="mt-1 text-[15px] text-ink">{state.detail}</p>

                {readiness !== null && readiness.window_open && (
                    <p className="mt-3 flex items-center gap-2 text-sm text-muted">
                        <span aria-hidden="true" className="inline-block size-2 rounded-full bg-brand-500" />
                        Updated every {readiness.poll_seconds} seconds
                    </p>
                )}
            </div>

            <div className="sm:text-right">
                <p className="text-sm text-muted">Time window</p>
                <p className="text-2xl font-bold text-ink" data-numeric>
                    {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)}
                </p>
                <p className="text-sm text-muted">{getMinutes(booking)} minutes</p>
            </div>
        </section>
    );
}
