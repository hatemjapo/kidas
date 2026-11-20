export default function ChoiceTile({ chosen = false, disabled = false, onClick, children, className = '', ...rest }) {
    const look = chosen
        ? 'border-brand-500 bg-brand-50 text-brand-800'
        : 'border-field bg-white text-ink hover:bg-brand-50';

    return (
        <button
            type="button"
            aria-pressed={chosen}
            disabled={disabled}
            onClick={onClick}
            className={`min-h-11 rounded-tile border px-3 py-2 text-left text-sm font-semibold disabled:opacity-50 ${look} ${className}`}
            {...rest}
        >
            {children}
        </button>
    );
}
