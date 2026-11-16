import { Route, Routes } from 'react-router';
import RequireAuth from './auth/RequireAuth';
import RequireRole from './auth/RequireRole';
import AppLayout from './layouts/AppLayout';
import Bookings from './pages/Bookings';
import Home from './pages/Home';
import Login from './pages/Login';
import NotFound from './pages/NotFound';
import Register from './pages/Register';
import Schedule from './pages/Schedule';

export default function Router() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />

            <Route element={<RequireAuth />}>
                <Route element={<AppLayout />}>
                    <Route path="/" element={<Home />} />

                    <Route element={<RequireRole allow={['admin', 'supplier']} />}>
                        <Route path="/bookings" element={<Bookings />} />
                    </Route>

                    <Route element={<RequireRole allow={['admin', 'staff']} />}>
                        <Route path="/schedule" element={<Schedule />} />
                    </Route>

                    <Route path="*" element={<NotFound />} />
                </Route>
            </Route>
        </Routes>
    );
}
