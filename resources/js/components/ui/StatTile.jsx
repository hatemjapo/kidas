export default function StatTile({ label, value, tone = 'neutral' }) {
    const tones = {
        neutral: 'border-line bg-white text-ink',
        brand: 'border-brand-500 bg-brand-50 text-brand-800',
        warn: 'border-warn-700 bg-warn-50 text-warn-700',
        danger: 'border-danger-700 bg-danger-50 text-danger-700',
    };

    return (
        <div className={`rounded-tile border p-4 ${tones[tone] ?? tones.neutral}`}>
            <p className="text-sm font-semibold text-muted">{label}</p>
            <p className="mt-1 text-2xl font-bold" data-numeric>
                {value}
            </p>
        </div>
    );
}
