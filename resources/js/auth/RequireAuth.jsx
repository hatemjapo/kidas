import { Navigate, Outlet } from 'react-router';
import { useAuth } from './AuthContext';

export default function RequireAuth() {
    const { user, loading } = useAuth();

    if (loading) {
        return <p className="px-6 py-10 text-sm text-slate-500">Loading…</p>;
    }

    if (user === null) {
        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
}
