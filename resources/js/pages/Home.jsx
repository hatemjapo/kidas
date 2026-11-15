import { useAuth } from '../auth/AuthContext';

export default function Home() {
    const { role, loading } = useAuth();

    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-semibold tracking-tight">Dock Appointment System</h1>

            <p className="max-w-2xl text-slate-600">
                Suppliers book a time slot, the warehouse sees a daily overview per dock, and the status of
                every handling stays visible to everyone involved.
            </p>

            <p className="text-sm text-slate-500">
                {loading ? 'Checking session…' : role ? `Signed in as ${role}.` : 'No session yet. Sign-in arrives with E1.'}
            </p>
        </div>
    );
}
