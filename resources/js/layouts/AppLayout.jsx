import { NavLink, Outlet } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { navigationFor } from '../navigation';

export default function AppLayout() {
    const { role, loading } = useAuth();
    const items = navigationFor(role);

    return (
        <div className="min-h-full">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
                    <span className="text-lg font-semibold tracking-tight">KiDas</span>

                    <nav className="flex flex-1 items-center gap-1">
                        {items.map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={item.to === '/'}
                                className={({ isActive }) =>
                                    `rounded-md px-3 py-2 text-sm font-medium ${
                                        isActive
                                            ? 'bg-slate-100 text-slate-900'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                    }`
                                }
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>

                    <span className="text-sm text-slate-500">
                        {loading ? 'Loading…' : (role ?? 'Not signed in')}
                    </span>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-10">
                <Outlet />
            </main>
        </div>
    );
}
