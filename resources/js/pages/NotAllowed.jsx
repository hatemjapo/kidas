import { Link } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import Card from '../components/ui/Card';

const roleNames = {
    admin: 'an administrator',
    staff: 'the warehouse',
    supplier: 'a supplier',
};

export default function NotAllowed() {
    const { role } = useAuth();

    return (
        <div className="max-w-xl space-y-6">
            <div>
                <p className="text-sm font-semibold tracking-wide text-brand-700 uppercase">No access</p>
                <h1 className="mt-1 text-3xl font-bold tracking-tight text-ink">This page is not yours to open</h1>
            </div>

            <Card>
                <p className="text-[15px] text-ink">
                    You are signed in as {roleNames[role] ?? 'a guest'}, and this page belongs to another role. Nothing
                    was changed.
                </p>

                <Link to="/" className="mt-3 inline-block text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Back to the dashboard
                </Link>
            </Card>
        </div>
    );
}
