import { Navigate, Outlet } from 'react-router';
import { useAuth } from './AuthContext';

export default function RequireRole({ allow }) {
    const { role } = useAuth();

    if (! allow.includes(role)) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
}
