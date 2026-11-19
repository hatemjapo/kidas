import { InfoIcon } from './icons';

export default function InfoBox({ children, className = '' }) {
    return (
        <div className={`flex gap-3 rounded-tile bg-brand-50 p-4 text-sm text-brand-800 ${className}`}>
            <InfoIcon className="mt-0.5 size-5 shrink-0 text-brand-700" />

            <div>{children}</div>
        </div>
    );
}
