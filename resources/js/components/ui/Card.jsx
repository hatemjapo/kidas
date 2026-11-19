export default function Card({ title, action, children, className = '' }) {
    return (
        <section className={`rounded-card border border-line bg-white p-5 ${className}`}>
            {(title || action) && (
                <header className="mb-4 flex items-start justify-between gap-4">
                    {title && <h2 className="text-lg font-bold text-ink">{title}</h2>}
                    {action}
                </header>
            )}

            {children}
        </section>
    );
}
