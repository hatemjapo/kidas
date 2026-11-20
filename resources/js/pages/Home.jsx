import { Link } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import Card from '../components/ui/Card';

const byRole = {
    supplier: {
        title: 'Your slots at the dock',
        lead: 'Book a time window, see where your lorry stands and change a booking while there is still time.',
        links: [
            { to: '/book', label: 'Book a slot', hint: 'Pick a day and a start time for a delivery or a pickup.' },
            { to: '/bookings', label: 'My bookings', hint: 'Everything you booked, newest first, with its history.' },
            { to: '/profile', label: 'Profile', hint: 'Company, contact, the default driver and your license plates.' },
        ],
    },
    staff: {
        title: 'The day at the docks',
        lead: 'See what is coming, register arrivals and move lorries through the handling.',
        links: [
            { to: '/schedule', label: 'Dock schedule', hint: 'All ten docks over the opening hours of one day.' },
        ],
    },
    admin: {
        title: 'KiDas administration',
        lead: 'Book for a supplier, change or cancel any booking, and keep the master data in the panel.',
        links: [
            { to: '/schedule', label: 'Dock schedule', hint: 'The warehouse day, with every booking on it.' },
            { to: '/book', label: 'Book for a supplier', hint: 'A slot for an approved company, without the lead time.' },
            { to: '/admin', label: 'Admin panel', hint: 'Docks, opening hours, shifts, days and pallet rules.', external: true },
        ],
    },
};

export default function Home() {
    const { role, loading } = useAuth();

    if (loading) {
        return <p className="text-sm text-muted">Checking session…</p>;
    }

    const page = byRole[role] ?? {
        title: 'KiDas',
        lead: 'Dock appointment system for a warehouse with ten docks.',
        links: [],
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight text-ink">{page.title}</h1>
                <p className="mt-2 max-w-2xl text-[15px] text-muted">{page.lead}</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {page.links.map((link) => (
                    <Card key={link.to}>
                        {link.external ? (
                            <a href={link.to} className="font-semibold text-brand-700 hover:text-brand-800">
                                {link.label}
                            </a>
                        ) : (
                            <Link to={link.to} className="font-semibold text-brand-700 hover:text-brand-800">
                                {link.label}
                            </Link>
                        )}

                        <p className="mt-1 text-sm text-muted">{link.hint}</p>
                    </Card>
                ))}
            </div>
        </div>
    );
}
