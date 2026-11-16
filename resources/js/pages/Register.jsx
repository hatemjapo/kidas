import { useState } from 'react';
import { Link } from 'react-router';
import api, { csrf } from '../lib/api';

const emptyForm = {
    company_name: '',
    company_phone: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
};

const fields = [
    { name: 'company_name', label: 'Company', type: 'text', autoComplete: 'organization' },
    { name: 'company_phone', label: 'Company phone', type: 'tel', autoComplete: 'tel' },
    { name: 'name', label: 'Your name', type: 'text', autoComplete: 'name' },
    { name: 'email', label: 'Email', type: 'email', autoComplete: 'username' },
    { name: 'password', label: 'Password', type: 'password', autoComplete: 'new-password' },
    {
        name: 'password_confirmation',
        label: 'Repeat password',
        type: 'password',
        autoComplete: 'new-password',
    },
];

export default function Register() {
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [registered, setRegistered] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();

        setErrors({});
        setMessage(null);
        setSubmitting(true);

        try {
            await csrf();
            await api.post('/register', form);
            setRegistered(true);
        } catch (error) {
            const status = error.response?.status;

            if (status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else if (status === 429) {
                setMessage('Too many attempts. Please try again in a minute.');
            } else {
                setMessage('Registration failed. Please try again.');
            }

            setSubmitting(false);
        }
    }

    if (registered) {
        return (
            <div className="flex min-h-full items-center justify-center px-6 py-16">
                <div className="w-full max-w-sm">
                    <h1 className="text-lg font-semibold tracking-tight">Registration received</h1>

                    <p role="status" className="mt-4 text-sm text-slate-600">
                        We are reviewing your registration. As soon as it is approved you will receive an
                        email with a link to sign in.
                    </p>

                    <p className="mt-6 text-sm text-slate-500">
                        <Link to="/login" className="font-medium text-slate-900 underline">
                            Back to sign in
                        </Link>
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-full items-center justify-center px-6 py-16">
            <div className="w-full max-w-sm">
                <h1 className="text-lg font-semibold tracking-tight">Register your company</h1>

                {message && (
                    <p
                        role="alert"
                        className="mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    >
                        {message}
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
                                type={field.type}
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
                        {submitting ? 'Sending…' : 'Register'}
                    </button>
                </form>

                <p className="mt-6 text-sm text-slate-500">
                    Already approved?{' '}
                    <Link to="/login" className="font-medium text-slate-900 underline">
                        Sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}
