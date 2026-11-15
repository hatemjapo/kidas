import { Route, Routes } from 'react-router';
import AppLayout from './layouts/AppLayout';
import Bookings from './pages/Bookings';
import Home from './pages/Home';
import NotFound from './pages/NotFound';
import Schedule from './pages/Schedule';

export default function Router() {
    return (
        <Routes>
            <Route element={<AppLayout />}>
                <Route path="/" element={<Home />} />
                <Route path="/bookings" element={<Bookings />} />
                <Route path="/schedule" element={<Schedule />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}
