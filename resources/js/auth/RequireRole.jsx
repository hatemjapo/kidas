import { Outlet } from 'react-router';
import { useAuth } from './AuthContext';
import NotAllowed from '../pages/NotAllowed';

export default function RequireRole({ allow }) {
    const { role } = useAuth();

    if (! allow.includes(role)) {
        return <NotAllowed />;
    }

    return <Outlet />;
}
