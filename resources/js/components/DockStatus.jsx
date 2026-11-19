import { useEffect, useState } from 'react';
import api from '../lib/api';
import { formatTime } from '../lib/datetime';

const messagesByStatus = {
    arrived: 'The warehouse has registered your arrival. Please wait for the dock.',
    in_progress: 'Your lorry is being handled.',
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

    if (readiness === null || ! readiness.window_open) {
        return null;
    }

    const waiting = messagesByStatus[booking.status];

    return (
        <section role="status" className="rounded-md border border-slate-200 p-4">
            <h2 className="text-base font-semibold">Dock {booking.dock_number}</h2>

            {waiting !== undefined && <p className="mt-1 text-sm text-slate-600">{waiting}</p>}

            {waiting === undefined && readiness.dock_free && (
                <p className="mt-1 text-sm font-medium text-emerald-700">
                    Your dock is free – you may arrive now.
                </p>
            )}

            {waiting === undefined && ! readiness.dock_free && (
                <p className="mt-1 text-sm text-slate-600">
                    Another lorry is still at your dock
                    {readiness.busy_until !== null && ` until about ${formatTime(readiness.busy_until)}`}.
                </p>
            )}
        </section>
    );
}
