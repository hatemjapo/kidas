import { useEffect, useState } from 'react';
import api, { csrf } from '../lib/api';

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
        return <p className="text-sm text-slate-500">Loading vehicles…</p>;
    }

    return (
        <section className="mt-10">
            <h2 className="text-base font-semibold tracking-tight">Your vehicles</h2>

            <p className="mt-1 text-sm text-slate-500">
                Saved license plates to pick from when booking. Spaces and hyphens are removed.
            </p>

            {error && (
                <p role="alert" className="mt-4 text-sm text-red-600">
                    {error}
                </p>
            )}

            {vehicles.length === 0 ? (
                <p className="mt-4 text-sm text-slate-500">No vehicles saved yet.</p>
            ) : (
                <ul className="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                    {vehicles.map((vehicle) => (
                        <li key={vehicle.id} className="flex items-center justify-between py-2">
                            <span className="font-mono text-sm">{vehicle.license_plate}</span>

                            <button
                                type="button"
                                onClick={() => handleRemove(vehicle)}
                                aria-label={`Remove ${vehicle.license_plate}`}
                                title={`Remove ${vehicle.license_plate}`}
                                className="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.5"
                                    className="h-5 w-5"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                    />
                                </svg>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={handleAdd} className="mt-4 flex items-end gap-2" noValidate>
                <div className="flex flex-1 flex-col gap-1">
                    <label htmlFor="license_plate" className="text-sm font-medium text-slate-700">
                        License plate
                    </label>
                    <input
                        id="license_plate"
                        name="license_plate"
                        type="text"
                        value={plate}
                        onChange={(event) => setPlate(event.target.value)}
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>

                <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Add
                </button>
            </form>
        </section>
    );
}
