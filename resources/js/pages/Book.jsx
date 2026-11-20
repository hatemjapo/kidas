import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import SlotPicker from '../components/SlotPicker';
import { useAuth } from '../auth/AuthContext';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import Card from '../components/ui/Card';
import InfoBox from '../components/ui/InfoBox';
import NumberField from '../components/ui/NumberField';
import SegmentedControl from '../components/ui/SegmentedControl';
import Select from '../components/ui/Select';
import TextField from '../components/ui/TextField';
import api, { csrf } from '../lib/api';
import { formatLongDate, formatTime } from '../lib/datetime';

const types = [
    { value: 'inbound', label: 'Delivery' },
    { value: 'outbound', label: 'Pickup' },
];

const newPlate = 'new';

export default function Book() {
    const { role } = useAuth();
    const booksForSomebodyElse = role === 'admin';

    const [suppliers, setSuppliers] = useState([]);
    const [supplierId, setSupplierId] = useState('');
    const [type, setType] = useState('inbound');
    const [pallets, setPallets] = useState(10);
    const [duration, setDuration] = useState(null);
    const [slot, setSlot] = useState(null);
    const [searching, setSearching] = useState(false);
    const [pickerKey, setPickerKey] = useState(0);
    const [vehicles, setVehicles] = useState([]);
    const [details, setDetails] = useState({ order_number: '', driver_phone: '', vehicle_id: '', license_plate: '' });
    const [booking, setBooking] = useState(null);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (booksForSomebodyElse) {
            return undefined;
        }

        let active = true;

        api.get('/profile')
            .then((response) => {
                const profile = response.data.data ?? response.data;

                if (active && typeof profile.driver_phone === 'string') {
                    setDetails((details) => ({ ...details, driver_phone: profile.driver_phone }));
                }
            })
            .catch(() => undefined);

        return () => {
            active = false;
        };
    }, [booksForSomebodyElse]);

    useEffect(() => {
        if (! booksForSomebodyElse) {
            return undefined;
        }

        let active = true;

        api.get('/suppliers')
            .then((response) => {
                if (active) {
                    setSuppliers(response.data.data ?? []);
                }
            })
            .catch(() => {
                if (active) {
                    setSuppliers([]);
                }
            });

        return () => {
            active = false;
        };
    }, [booksForSomebodyElse]);

    useEffect(() => {
        if (booksForSomebodyElse && supplierId === '') {
            setVehicles([]);

            return undefined;
        }

        let active = true;

        const path = booksForSomebodyElse ? `/suppliers/${supplierId}/vehicles` : '/vehicles';

        api.get(path)
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
    }, [booksForSomebodyElse, supplierId]);

    async function submitBooking(event) {
        event.preventDefault();

        setErrors({});
        setMessage(null);
        setSubmitting(true);

        try {
            await csrf();

            const response = await api.post(booksForSomebodyElse ? '/admin/bookings' : '/bookings', {
                ...(booksForSomebodyElse ? { supplier_id: Number(supplierId) } : {}),
                type,
                pallets,
                starts_at: slot.starts_at,
                order_number: details.order_number,
                driver_phone: details.driver_phone,
                vehicle_id: details.vehicle_id === newPlate || details.vehicle_id === '' ? null : Number(details.vehicle_id),
                license_plate: details.vehicle_id === newPlate || details.vehicle_id === '' ? details.license_plate : null,
            });

            setBooking(response.data.data ?? response.data);
        } catch (failure) {
            const status = failure.response?.status;

            if (status === 422) {
                setErrors(failure.response.data.errors ?? {});
            } else if (status === 409) {
                setMessage(failure.response.data.message ?? 'This slot has just been taken.');
                setSlot(null);
                setPickerKey((key) => key + 1);
            } else {
                setMessage('The booking could not be saved. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (booking !== null) {
        return (
            <div className="max-w-xl space-y-6">
                <div>
                    <p className="text-sm font-semibold tracking-wide text-brand-700 uppercase">Slot booked</p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight text-ink">
                        Dock {booking.dock_number}
                    </h1>
                </div>

                <Card>
                    <p role="status" className="text-[15px] text-ink">
                        Please arrive at dock <strong>{booking.dock_number}</strong> on{' '}
                        {formatLongDate(booking.starts_at)} at {formatTime(booking.starts_at)}.
                    </p>

                    <dl className="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <dt className="text-muted">Time</dt>
                        <dd data-numeric>
                            {formatTime(booking.starts_at)} – {formatTime(booking.ends_at)}
                        </dd>

                        <dt className="text-muted">Pallets</dt>
                        <dd data-numeric>{booking.pallets}</dd>

                        <dt className="text-muted">License plate</dt>
                        <dd className="font-mono">{booking.license_plate}</dd>
                    </dl>
                </Card>

                <Link to="/bookings" className="inline-block text-sm font-semibold text-brand-700 hover:text-brand-800">
                    All my bookings
                </Link>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight text-ink">Book a slot</h1>
                <p className="mt-2 text-[15px] text-muted">
                    Choose what you bring and how much of it, then a free start time. The dock is assigned when you
                    book.
                </p>
            </div>

            {message && <Alert>{message}</Alert>}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-6">
                    <Card>
                        <div className="flex flex-wrap items-end gap-6">
                            {booksForSomebodyElse && (
                                <Select
                                    id="supplier_id"
                                    label="Supplier"
                                    value={supplierId}
                                    error={errors.supplier_id?.[0]}
                                    onChange={(event) => {
                                        setSupplierId(event.target.value);
                                        setSlot(null);
                                        setSearching(false);
                                        setDetails({
                                            order_number: '',
                                            driver_phone: '',
                                            vehicle_id: '',
                                            license_plate: '',
                                        });
                                    }}
                                    className="w-56"
                                >
                                    <option value="">Please choose</option>
                                    {suppliers.map((supplier) => (
                                        <option key={supplier.id} value={supplier.id}>
                                            {supplier.company_name}
                                        </option>
                                    ))}
                                </Select>
                            )}

                            <SegmentedControl
                                name="type"
                                label="What for"
                                options={types}
                                value={type}
                                onChange={(chosen) => {
                                    setType(chosen);
                                    setSlot(null);
                                    setSearching(false);
                                }}
                            />

                            <NumberField
                                id="pallets"
                                label="Pallets"
                                min="1"
                                max="70"
                                value={pallets}
                                error={errors.pallets?.[0]}
                                onChange={(event) => {
                                    setPallets(Number(event.target.value));
                                    setSlot(null);
                                    setSearching(false);
                                }}
                            />

                            {searching && duration !== null && (
                                <p className="flex h-12 items-center rounded-control bg-brand-50 px-4 text-sm text-brand-800">
                                    Blocks a dock for&nbsp;<strong>{duration} minutes</strong>
                                </p>
                            )}

                            {! searching && (
                                <Button
                                    onClick={() => {
                                        setSearching(true);
                                        setPickerKey((key) => key + 1);
                                    }}
                                    disabled={booksForSomebodyElse && supplierId === ''}
                                >
                                    Show free slots
                                </Button>
                            )}
                        </div>
                    </Card>

                    {searching ? (
                        <SlotPicker
                            key={pickerKey}
                            type={type}
                            pallets={pallets}
                            selected={slot}
                            onDuration={setDuration}
                            onPick={(picked) => {
                                setSlot(picked);
                                setMessage(null);
                            }}
                        />
                    ) : (
                        <Card title="Choose a day">
                            <p className="text-sm text-muted">
                                {booksForSomebodyElse && supplierId === ''
                                    ? 'Choose the supplier, what they bring and how many pallets, then show the free slots.'
                                    : 'Say what you bring and how many pallets, then show the free slots.'}
                            </p>
                        </Card>
                    )}
                </div>

                <form onSubmit={submitBooking} className="space-y-4" noValidate>
                    <Card title="Your booking">
                        {slot === null ? (
                            <p className="rounded-tile bg-canvas p-4 text-sm text-muted">
                                Pick a start time to see your booking here.
                            </p>
                        ) : (
                            <div className="rounded-tile bg-brand-50 p-4">
                                <p className="text-sm text-brand-800">
                                    {types.find((kind) => kind.value === type).label} · {pallets} pallets
                                </p>
                                <p className="mt-1 font-bold text-ink">{formatLongDate(slot.starts_at)}</p>
                                <p className="font-semibold text-brand-700" data-numeric>
                                    {formatTime(slot.starts_at)} – {formatTime(slot.ends_at)}
                                </p>
                            </div>
                        )}

                        <div className="mt-5 space-y-4">
                            <TextField
                                id="order_number"
                                label="Order number"
                                placeholder="e.g. 4500123456"
                                value={details.order_number}
                                error={errors.order_number?.[0]}
                                onChange={(event) => setDetails({ ...details, order_number: event.target.value })}
                            />

                            <TextField
                                id="driver_phone"
                                label="Driver phone"
                                placeholder="+49 …"
                                value={details.driver_phone}
                                error={errors.driver_phone?.[0]}
                                onChange={(event) => setDetails({ ...details, driver_phone: event.target.value })}
                            />

                            <Select
                                id="vehicle_id"
                                label="Vehicle"
                                hint="A new plate is saved for the next time."
                                value={details.vehicle_id}
                                error={errors.vehicle_id?.[0]}
                                onChange={(event) => setDetails({ ...details, vehicle_id: event.target.value })}
                            >
                                <option value="">Another plate</option>
                                {vehicles.map((vehicle) => (
                                    <option key={vehicle.id} value={vehicle.id}>
                                        {vehicle.license_plate}
                                    </option>
                                ))}
                            </Select>

                            {details.vehicle_id === '' && (
                                <TextField
                                    id="license_plate"
                                    label="License plate"
                                    placeholder="HH-AB 1234"
                                    value={details.license_plate}
                                    error={errors.license_plate?.[0]}
                                    onChange={(event) => setDetails({ ...details, license_plate: event.target.value })}
                                />
                            )}

                            {errors.starts_at && <Alert>{errors.starts_at[0]}</Alert>}

                            <Button
                                type="submit"
                                loading={submitting}
                                disabled={slot === null || (booksForSomebodyElse && supplierId === '')}
                                className="w-full"
                            >
                                Book this slot
                            </Button>

                            <p className="text-xs text-muted">
                                Moving or cancelling is possible up to three hours before the slot.
                            </p>
                        </div>
                    </Card>
                </form>
            </div>
        </div>
    );
}
