export default function InfoBox({ children, className = '' }) {
    return (
        <div className={`rounded-tile border border-brand-500 bg-brand-50 p-3 text-sm text-brand-800 ${className}`}>
            {children}
        </div>
    );
}
