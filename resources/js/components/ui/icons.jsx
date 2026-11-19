const base = {
    width: 24,
    height: 24,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': 'true',
    focusable: 'false',
};

export function CloseIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <path d="M6 6l12 12M18 6L6 18" />
        </svg>
    );
}

export function ChevronLeftIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <path d="M15 5l-7 7 7 7" />
        </svg>
    );
}

export function ChevronRightIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <path d="M9 5l7 7-7 7" />
        </svg>
    );
}

export function SpinnerIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={`animate-spin ${className}`}>
            <path d="M12 3a9 9 0 1 0 9 9" />
        </svg>
    );
}
