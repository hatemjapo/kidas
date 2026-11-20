import { useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { navigationFor } from '../navigation';
import Button from '../components/ui/Button';
import IconButton from '../components/ui/IconButton';
import Logo from '../components/ui/Logo';
import { CloseIcon } from '../components/ui/icons';

const roleNames = {
    admin: 'Administration',
    staff: 'Warehouse',
    supplier: 'Supplier',
};

const wide = ['/schedule'];

function MenuIcon() {
    return (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            aria-hidden="true"
            focusable="false"
            className="size-5"
        >
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
    );
}

function itemClasses({ isActive }) {
    return `flex min-h-11 items-center rounded-nav px-3 text-sm font-semibold ${
        isActive ? 'bg-brand-50 text-brand-800' : 'text-muted hover:bg-brand-50 hover:text-brand-800'
    }`;
}

export default function AppLayout() {
    const { user, role, loading, logout } = useAuth();
    const { pathname } = useLocation();
    const [menuOpen, setMenuOpen] = useState(false);

    const items = navigationFor(role);
    const container = wide.some((path) => pathname.startsWith(path)) ? 'max-w-[1360px]' : 'max-w-[1240px]';
    const whoAmI = user?.company_name ?? (role === null ? null : (roleNames[role] ?? role));

    return (
        <div className="min-h-full">
            <header className="border-b border-line bg-white">
                <div className={`mx-auto flex h-16 items-center gap-6 px-4 sm:px-8 ${container}`}>
                    <Logo />

                    <nav aria-label="Main" className="hidden flex-1 items-center gap-1 md:flex">
                        {items.map((item) => (
                            <NavLink key={item.to} to={item.to} end={item.to === '/'} className={itemClasses}>
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>

                    <div className="ms-auto flex items-center gap-3 md:ms-0">
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-semibold text-ink">
                                {loading ? 'Loading…' : (whoAmI ?? 'Not signed in')}
                            </p>
                            {user !== null && <p className="text-xs text-muted">{user.email}</p>}
                        </div>

                        {user !== null && (
                            <Button variant="secondary" size={40} onClick={logout} className="hidden md:inline-flex">
                                Sign out
                            </Button>
                        )}

                        {items.length > 0 && (
                            <span className="md:hidden">
                                <IconButton
                                    label={menuOpen ? 'Close the menu' : 'Open the menu'}
                                    aria-expanded={menuOpen}
                                    onClick={() => setMenuOpen((open) => ! open)}
                                >
                                    {menuOpen ? <CloseIcon /> : <MenuIcon />}
                                </IconButton>
                            </span>
                        )}
                    </div>
                </div>

                {menuOpen && (
                    <div className="border-t border-line bg-white md:hidden">
                        <nav aria-label="Main, small screen" className={`mx-auto flex flex-col gap-1 p-4 ${container}`}>
                            {items.map((item) => (
                                <NavLink
                                    key={item.to}
                                    to={item.to}
                                    end={item.to === '/'}
                                    onClick={() => setMenuOpen(false)}
                                    className={itemClasses}
                                >
                                    {item.label}
                                </NavLink>
                            ))}

                            {user !== null && (
                                <Button variant="secondary" size={40} onClick={logout} className="mt-2 self-start">
                                    Sign out
                                </Button>
                            )}
                        </nav>
                    </div>
                )}
            </header>

            <main className={`mx-auto px-4 py-8 sm:px-8 ${container}`}>
                <Outlet />
            </main>
        </div>
    );
}
