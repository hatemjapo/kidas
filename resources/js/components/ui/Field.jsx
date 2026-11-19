export function fieldDescription(id, hint, error) {
    const described = [];

    if (hint) {
        described.push(`${id}-hint`);
    }

    if (error) {
        described.push(`${id}-error`);
    }

    return described.length === 0 ? undefined : described.join(' ');
}

export const fieldClasses =
    'h-12 w-full rounded-control border border-field bg-white px-3 text-[15px] text-ink placeholder:text-muted aria-invalid:border-danger-700';

export default function Field({ id, label, hint, error, children }) {
    return (
        <div className="flex flex-col gap-1">
            <label htmlFor={id} className="text-sm font-semibold text-ink">
                {label}
            </label>

            {children}

            {hint && (
                <p id={`${id}-hint`} className="text-xs text-muted">
                    {hint}
                </p>
            )}

            {error && (
                <p id={`${id}-error`} className="text-sm text-danger-700">
                    {error}
                </p>
            )}
        </div>
    );
}
