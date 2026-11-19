export default function Alert({ children, tone = 'danger', className = '' }) {
    const tones = {
        danger: 'border-danger-700 bg-danger-50 text-danger-700',
        warn: 'border-warn-700 bg-warn-50 text-warn-700',
    };

    return (
        <p role="alert" className={`rounded-tile border p-3 text-sm ${tones[tone] ?? tones.danger} ${className}`}>
            {children}
        </p>
    );
}
