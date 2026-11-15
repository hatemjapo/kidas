import { Link } from 'react-router';

export default function NotFound() {
    return (
        <div className="space-y-4">
            <h1 className="text-2xl font-semibold tracking-tight">Page not found</h1>

            <Link to="/" className="text-sm font-medium text-slate-900 underline">
                Back to the dashboard
            </Link>
        </div>
    );
}
