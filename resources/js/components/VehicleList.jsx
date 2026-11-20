import { useEffect, useState } from 'react';
import api, { csrf } from '../lib/api';
import Alert from './ui/Alert';
import Button from './ui/Button';
import Card from './ui/Card';
import IconButton from './ui/IconButton';
import TextField from './ui/TextField';

export default function VehicleList() {
    const [vehicles, setVehicles] = useState([]);
    const [plate, setPlate] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        let active = true;

        api.get('/vehicles')
            .then((response) => {
                if (active) {
                    setVehicles(response.data.data ?? response.data);
                }
            })
            .catch(() => {
                if (active) {
                    setError('Your vehicles could not be loaded.');
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    async function handleAdd(event) {
        event.preventDefault();

        setError(null);
        setSubmitting(true);

        try {
            await csrf();

            const response = await api.post('/vehicles', { license_plate: plate });

            setVehicles([...vehicles, response.data.data ?? response.data]);
            setPlate('');
        } catch (failure) {
            if (failure.response?.status === 422) {
                setError(failure.response.data.errors?.license_plate?.[0] ?? 'This plate was refused.');
            } else {
                setError('The vehicle could not be added.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    async function handleRemove(vehicle) {
        setError(null);

        try {
            await csrf();
            await api.delete(`/vehicles/${vehicle.id}`);

            setVehicles(vehicles.filter((item) => item.id !== vehicle.id));
        } catch {
            setError('The vehicle could not be removed.');
        }
    }

    if (loading) {
        return <p className="text-sm text-muted">Loading vehicles…</p>;
    }

    return (
        <Card title="Your vehicles">
            <p className="text-sm text-muted">
                Saved license plates to pick from when booking. Spaces and hyphens are removed.
            </p>

            {error && <Alert className="mt-4">{error}</Alert>}

            {vehicles.length === 0 ? (
                <p className="mt-4 text-sm text-muted">
                    No plates saved yet. Add the one you use most, and booking will offer it.
                </p>
            ) : (
                <ul className="mt-4 space-y-2">
                    {vehicles.map((vehicle) => (
                        <li
                            key={vehicle.id}
                            className="flex items-center justify-between gap-4 rounded-tile border border-line px-4 py-2"
                        >
                            <span className="font-mono text-sm font-semibold text-ink">{vehicle.license_plate}</span>

                            <IconButton
                                label={`Remove ${vehicle.license_plate}`}
                                onClick={() => handleRemove(vehicle)}
                                className="border-0 hover:bg-danger-50 hover:text-danger-700"
                            >
                                <svg
                                    aria-hidden="true"
                                    focusable="false"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="size-5"
                                >
                                    <path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13M10 11v6M14 11v6" />
                                </svg>
                            </IconButton>
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={handleAdd} className="mt-5 flex flex-col gap-4" noValidate>
                <TextField
                    id="license_plate"
                    name="license_plate"
                    label="License plate"
                    placeholder="HH-AB 1234"
                    value={plate}
                    onChange={(event) => setPlate(event.target.value)}
                />

                <Button type="submit" loading={submitting} className="self-start">
                    Add
                </Button>
            </form>
        </Card>
    );
}
