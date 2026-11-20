import { useEffect, useState } from 'react';
import VehicleList from '../components/VehicleList';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import Card from '../components/ui/Card';
import TextField from '../components/ui/TextField';
import api, { csrf } from '../lib/api';

const fields = [
    { name: 'company_name', label: 'Company', autoComplete: 'organization' },
    { name: 'company_phone', label: 'Company phone', autoComplete: 'tel' },
    { name: 'name', label: 'Contact name', autoComplete: 'name' },
    {
        name: 'driver_phone',
        label: 'Default driver phone',
        autoComplete: 'tel',
        optional: true,
        hint: 'A booking is prefilled with this number and can carry another one.',
    },
];

export default function Profile() {
    const [form, setForm] = useState({ company_name: '', company_phone: '', name: '', driver_phone: '' });
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
                    driver_phone: profile.driver_phone ?? '',
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
            await api.patch('/profile', {
                ...form,
                driver_phone: form.driver_phone === '' ? null : form.driver_phone,
            });
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
        return <p className="text-sm text-muted">Loading…</p>;
    }

    return (
        <div className="max-w-2xl space-y-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight text-ink">Your profile</h1>
                <p className="mt-2 text-[15px] text-muted">
                    {email} · this is what the warehouse sees when you book.
                </p>
            </div>

            {message && <Alert>{message}</Alert>}

            {saved && (
                <p role="status" className="text-sm font-semibold text-brand-700">
                    Your profile has been saved.
                </p>
            )}

            <Card title="Company and contact">
                <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
                    {fields.map((field) => (
                        <TextField
                            key={field.name}
                            id={field.name}
                            name={field.name}
                            label={field.label}
                            hint={field.hint}
                            autoComplete={field.autoComplete}
                            required={! field.optional}
                            error={errors[field.name]?.[0]}
                            value={form[field.name]}
                            onChange={(event) => setForm({ ...form, [field.name]: event.target.value })}
                        />
                    ))}

                    <Button type="submit" loading={submitting} className="self-start">
                        {submitting ? 'Saving…' : 'Save'}
                    </Button>
                </form>
            </Card>

            <VehicleList />
        </div>
    );
}
