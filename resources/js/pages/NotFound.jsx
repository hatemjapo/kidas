import { Link } from 'react-router';
import Card from '../components/ui/Card';

export default function NotFound() {
    return (
        <div className="max-w-xl space-y-6">
            <div>
                <p className="text-sm font-semibold tracking-wide text-brand-700 uppercase">404</p>
                <h1 className="mt-1 text-3xl font-bold tracking-tight text-ink">This page does not exist</h1>
            </div>

            <Card>
                <p className="text-[15px] text-ink">
                    The address you opened leads nowhere. It may have been mistyped, or the page has moved.
                </p>

                <Link to="/" className="mt-3 inline-block text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Back to the dashboard
                </Link>
            </Card>
        </div>
    );
}
