import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import SlotPicker from '../components/SlotPicker';
import api, { csrf } from '../lib/api';
import { formatDate, formatTime } from '../lib/datetime';

export default function Book() {
    const [step, setStep] = useState('load');
    const [type, setType] = useState('inbound');
    const [pallets, setPallets] = useState(10);
    const [slot, setSlot] = useState(null);
    const [pickerKey, setPickerKey] = useState(0);
    const [vehicles, setVehicles] = useState([]);
    const [details, setDetails] = useState({ order_number: '', driver_phone: '', vehicle_id: '', license_plate: '' });
    const [booking, setBooking] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        let active = true;

        api.get('/vehicles')
            .then((response) => {
                if (active) {
                    setVehicles(response.data.data ?? []);
                }
            })
            .catch(() => {
                if (active) {
                    setVehicles([]);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    function pickSlot(picked) {
        setSlot(picked);
        setMessage(null);
        setStep('details');
    }

    async function submitBooking(event) {
        event.preventDefault();

        setErrors({});
        setMessage(null);
        setSubmitting(true);

        try {
            await csrf();

            const response = await api.post('/bookings', {
                type,
                pallets,
                starts_at: slot.starts_at,
                order_number: details.order_number,
                driver_phone: details.driver_phone,
                vehicle_id: details.vehicle_id === '' ? null : Number(details.vehicle_id),
                license_plate: details.vehicle_id === '' ? details.license_plate : null,
            });

            setBooking(response.data.data ?? response.data);
            setStep('done');
        } catch (failure) {
            const status = failure.response?.status;

            if (status === 422) {
                setErrors(failure.response.data.errors ?? {});
            } else if (status === 409) {
                setMessage(failure.response.data.message ?? 'This slot has just been taken.');
                setPickerKey((key) => key + 1);
                setStep('slot');
            } else {
                setMessage('The booking could not be saved. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (step === 'done') {
        return (
            <div className="max-w-md space-y-4">
                <h1 className="text-2xl font-semibold tracking-tight">Slot booked</h1>

                <p role="status" className="text-sm text-slate-600">
                    Please arrive at dock {booking.dock_number} on {formatDate(booking.starts_at)} at{' '}
                    {formatTime(booking.starts_at)}.
                </p>

                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt className="text-slate-500">Dock</dt>
                    <dd className="text-lg font-semibold">{booking.dock_number}</dd>

                    <dt className="text-slate-500">Time</dt>
                    <dd>
                        {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)}
                    </dd>

                    <dt className="text-slate-500">Pallets</dt>
                    <dd>{booking.pallets}</dd>

                    <dt className="text-slate-500">License plate</dt>
                    <dd className="font-mono">{booking.license_plate}</dd>
                </dl>

                <Link to="/bookings" className="inline-block text-sm font-medium text-slate-900 underline">
                    All my bookings
                </Link>
            </div>
        );
    }

    return (
        <div className="max-w-3xl space-y-6">
            <h1 className="text-2xl font-semibold tracking-tight">Book a slot</h1>

            {message && (
                <p
                    role="alert"
                    className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                >
                    {message}
                </p>
            )}

            <section className="flex flex-wrap items-end gap-4">
                <div className="flex flex-col gap-1">
                    <label htmlFor="type" className="text-sm font-medium text-slate-700">
                        I want to
                    </label>
                    <select
                        id="type"
                        value={type}
                        onChange={(event) => {
                            setType(event.target.value);
                            setStep('load');
                        }}
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="inbound">deliver</option>
                        <option value="outbound">pick up</option>
                    </select>
                </div>

                <div className="flex flex-col gap-1">
                    <label htmlFor="pallets" className="text-sm font-medium text-slate-700">
                        Pallets
                    </label>
                    <input
                        id="pallets"
                        type="number"
                        min="1"
                        max="70"
                        value={pallets}
                        onChange={(event) => {
                            setPallets(Number(event.target.value));
                            setStep('load');
                        }}
                        className="w-24 rounded-md border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>

                <button
                    type="button"
                    onClick={() => {
                        setPickerKey((key) => key + 1);
                        setStep('slot');
                    }}
                    className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white"
                >
                    Show free slots
                </button>
            </section>

            {step === 'slot' && (
                <SlotPicker key={pickerKey} type={type} pallets={pallets} onPick={pickSlot} />
            )}

            {step === 'details' && (
                <form onSubmit={submitBooking} className="space-y-4" noValidate>
                    <p className="text-sm text-slate-600">
                        {formatDate(slot.starts_at)}, {formatTime(slot.starts_at)} –{' '}
                        {formatTime(slot.ends_at)}
                    </p>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="order_number" className="text-sm font-medium text-slate-700">
                            Order number
                        </label>
                        <input
                            id="order_number"
                            value={details.order_number}
                            onChange={(event) =>
                                setDetails({ ...details, order_number: event.target.value })
                            }
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.order_number && (
                            <p className="text-sm text-red-600">{errors.order_number[0]}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="driver_phone" className="text-sm font-medium text-slate-700">
                            Driver phone
                        </label>
                        <input
                            id="driver_phone"
                            value={details.driver_phone}
                            onChange={(event) =>
                                setDetails({ ...details, driver_phone: event.target.value })
                            }
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.driver_phone && (
                            <p className="text-sm text-red-600">{errors.driver_phone[0]}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="vehicle_id" className="text-sm font-medium text-slate-700">
                            Vehicle
                        </label>
                        <select
                            id="vehicle_id"
                            value={details.vehicle_id}
                            onChange={(event) =>
                                setDetails({ ...details, vehicle_id: event.target.value })
                            }
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Another license plate</option>
                            {vehicles.map((vehicle) => (
                                <option key={vehicle.id} value={vehicle.id}>
                                    {vehicle.license_plate}
                                </option>
                            ))}
                        </select>
                    </div>

                    {details.vehicle_id === '' && (
                        <div className="flex flex-col gap-1">
                            <label htmlFor="license_plate" className="text-sm font-medium text-slate-700">
                                License plate
                            </label>
                            <input
                                id="license_plate"
                                value={details.license_plate}
                                onChange={(event) =>
                                    setDetails({ ...details, license_plate: event.target.value })
                                }
                                className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                            {errors.license_plate && (
                                <p className="text-sm text-red-600">{errors.license_plate[0]}</p>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={submitting}
                            className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            {submitting ? 'Booking…' : 'Book this slot'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setStep('slot')}
                            className="text-sm font-medium text-slate-600 hover:underline"
                        >
                            Pick another slot
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
