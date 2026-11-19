export default function IconButton({ label, children, onClick, disabled = false, className = '', ...rest }) {
    if (typeof label !== 'string' || label.trim() === '') {
        throw new Error('IconButton needs a label, so that it has an accessible name.');
    }

    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onClick={onClick}
            disabled={disabled}
            className={`inline-flex size-11 items-center justify-center rounded-nav border border-field bg-white text-ink hover:bg-brand-50 disabled:opacity-50 ${className}`}
            {...rest}
        >
            {children}
        </button>
    );
}
