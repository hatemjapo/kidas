import { useEffect, useState } from 'react';
import api, { csrf } from '../lib/api';

const fields = [
    { name: 'company_name', label: 'Company', autoComplete: 'organization' },
    { name: 'company_phone', label: 'Company phone', autoComplete: 'tel' },
    { name: 'name', label: 'Contact name', autoComplete: 'name' },
];

export default function Profile() {
    const [form, setForm] = useState({ company_name: '', company_phone: '', name: '' });
    const [email, setEmail] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saved, setSaved] = useState(false);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        let active = true;

        api.get('/profile')
            .then((response) => {
                if (! active) {
                    return;
                }

                const profile = response.data.data ?? response.data;

                setForm({
                    company_name: profile.company_name ?? '',
                    company_phone: profile.company_phone ?? '',
                    name: profile.name ?? '',
                });
                setEmail(profile.email ?? '');
            })
            .catch(() => {
                if (active) {
                    setMessage('Your profile could not be loaded.');
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

    async function handleSubmit(event) {
        event.preventDefault();

        setErrors({});
        setMessage(null);
        setSaved(false);
        setSubmitting(true);

        try {
            await csrf();
            await api.patch('/profile', form);
            setSaved(true);
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else {
                setMessage('Saving failed. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) {
        return <p className="text-sm text-slate-500">Loading…</p>;
    }

    return (
        <div className="max-w-sm">
            <h1 className="text-lg font-semibold tracking-tight">Your profile</h1>

            <p className="mt-2 text-sm text-slate-500">
                Signed in as {email}. Please contact the warehouse team to change this address.
            </p>

            {message && (
                <p
                    role="alert"
                    className="mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                >
                    {message}
                </p>
            )}

            {saved && (
                <p role="status" className="mt-4 text-sm text-green-700">
                    Your profile has been saved.
                </p>
            )}

            <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4" noValidate>
                {fields.map((field) => (
                    <div key={field.name} className="flex flex-col gap-1">
                        <label
                            htmlFor={field.name}
                            className="text-sm font-medium text-slate-700 after:ml-0.5 after:text-red-600 after:content-['*']"
                        >
                            {field.label}
                        </label>
                        <input
                            id={field.name}
                            name={field.name}
                            type="text"
                            autoComplete={field.autoComplete}
                            required
                            value={form[field.name]}
                            onChange={(event) =>
                                setForm({ ...form, [field.name]: event.target.value })
                            }
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors[field.name] && (
                            <p className="text-sm text-red-600">{errors[field.name][0]}</p>
                        )}
                    </div>
                ))}

                <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {submitting ? 'Saving…' : 'Save'}
                </button>
            </form>
        </div>
    );
}
