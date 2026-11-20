export default function Logo({ className = '' }) {
    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <span
                aria-hidden="true"
                className="inline-flex size-8 flex-col justify-center gap-1 rounded-nav bg-brand-500 px-1.5"
            >
                <span className="h-0.5 rounded-full bg-white" />
                <span className="h-0.5 w-3/4 rounded-full bg-white" />
                <span className="h-0.5 rounded-full bg-white" />
            </span>

            <span className="text-lg font-bold tracking-tight text-ink">KiDas</span>
        </span>
    );
}
