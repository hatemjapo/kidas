import { SpinnerIcon } from './icons';

const variants = {
    primary: 'bg-brand-700 text-white border border-brand-700 hover:bg-brand-800',
    secondary: 'bg-white text-ink border border-field hover:bg-brand-50',
    danger: 'bg-white text-danger-700 border border-danger-700 hover:bg-danger-50',
};

const sizes = {
    40: 'h-10 px-3 text-sm',
    48: 'h-12 px-4 text-[15px]',
};

export default function Button({
    children,
    variant = 'primary',
    size = 48,
    loading = false,
    disabled = false,
    type = 'button',
    className = '',
    ...rest
}) {
    const shape = 'inline-flex items-center justify-center gap-2 rounded-control font-semibold disabled:opacity-50';

    return (
        <button
            type={type}
            disabled={disabled || loading}
            aria-busy={loading ? 'true' : undefined}
            className={`${shape} ${variants[variant] ?? variants.primary} ${sizes[size] ?? sizes[48]} ${className}`}
            {...rest}
        >
            {loading && <SpinnerIcon className="size-4" />}
            {children}
        </button>
    );
}
