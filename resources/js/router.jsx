import { Route, Routes } from 'react-router';
import RequireAuth from './auth/RequireAuth';
import AppLayout from './layouts/AppLayout';
import Bookings from './pages/Bookings';
import Home from './pages/Home';
import Login from './pages/Login';
import NotFound from './pages/NotFound';
import Schedule from './pages/Schedule';

export default function Router() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />

            <Route element={<RequireAuth />}>
                <Route element={<AppLayout />}>
                    <Route path="/" element={<Home />} />
                    <Route path="/bookings" element={<Bookings />} />
                    <Route path="/schedule" element={<Schedule />} />
                    <Route path="*" element={<NotFound />} />
                </Route>
            </Route>
        </Routes>
    );
}
