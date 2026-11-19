import { getStatusStyle, plateMismatchStyle } from '../../lib/statusStyles';

export default function StatusChip({ status, type = 'inbound', className = '' }) {
    const style = getStatusStyle(status, type);

    return (
        <span
            className={`inline-flex items-center rounded-nav px-2 py-1 text-xs font-semibold ${style.chip} ${className}`}
        >
            {style.label}
        </span>
    );
}

export function PlateMismatchChip({ className = '' }) {
    return (
        <span
            className={`inline-flex items-center rounded-nav px-2 py-1 text-xs font-semibold ${plateMismatchStyle.chip} ${className}`}
        >
            {plateMismatchStyle.label}
        </span>
    );
}
