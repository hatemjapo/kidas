import { getStatusStyle } from '../lib/statusStyles';

const entries = [
    { label: 'Delivery', status: 'booked', type: 'inbound' },
    { label: 'Pickup', status: 'booked', type: 'outbound' },
    { label: 'Arrived', status: 'arrived', type: 'inbound' },
    { label: 'In progress', status: 'in_progress', type: 'inbound' },
    { label: 'Completed', status: 'completed', type: 'inbound' },
    { label: 'No show', status: 'no_show', type: 'inbound' },
];

export default function ScheduleLegend() {
    return (
        <ul className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted">
            {entries.map((entry) => (
                <li key={entry.label} className="flex items-center gap-2">
                    <span
                        aria-hidden="true"
                        className={`inline-block size-4 rounded-[5px] ${getStatusStyle(entry.status, entry.type).block}`}
                    />
                    {entry.label}
                </li>
            ))}
        </ul>
    );
}
