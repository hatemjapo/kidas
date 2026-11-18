const statusClasses = {
    booked: 'bg-slate-100 text-slate-700',
    arrived: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    no_show: 'bg-red-100 text-red-800',
};

export default function StatusBadge({ status }) {
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusClasses[status] ?? 'bg-slate-100 text-slate-700'}`}
        >
            {status.replace('_', ' ')}
        </span>
    );
}
