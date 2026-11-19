export default function SegmentedControl({ label, options, value, onChange, name }) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-sm font-semibold text-ink" id={`${name}-label`}>
                {label}
            </span>

            <div role="group" aria-labelledby={`${name}-label`} className="inline-flex gap-1 rounded-control bg-line p-1">
                {options.map((option) => {
                    const chosen = option.value === value;

                    return (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={chosen}
                            onClick={() => onChange(option.value)}
                            className={`h-10 rounded-nav px-4 text-sm font-semibold ${
                                chosen ? 'bg-brand-700 text-white' : 'bg-white text-ink hover:bg-brand-50'
                            }`}
                        >
                            {option.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
