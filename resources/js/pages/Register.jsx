import { useState } from 'react';
import { Link } from 'react-router';
import api, { csrf } from '../lib/api';
import AuthLayout from '../layouts/AuthLayout';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import InfoBox from '../components/ui/InfoBox';
import TextField from '../components/ui/TextField';

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
            <AuthLayout title="Registration received">
                <p role="status" className="mt-4 text-[15px] text-ink">
                    The warehouse is checking your registration. As soon as it is approved you will receive an email
                    with a link to sign in.
                </p>

                <p className="mt-6 text-sm text-muted">
                    <Link to="/login" className="font-semibold text-brand-700 underline hover:text-brand-800">
                        Back to sign in
                    </Link>
                </p>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Register as a supplier"
            lead="Tell us about your company; the warehouse approves it by hand."
            aside={
                <>
                    <hr className="mt-8 border-line" />

                    <p className="mt-6 text-sm text-muted">
                        Already approved?{' '}
                        <Link to="/login" className="font-semibold text-brand-700 hover:text-brand-800">
                            Sign in
                        </Link>
                    </p>

                    <InfoBox className="mt-4">
                        The warehouse approves every registration by hand. You will get an email with a link to sign in
                        once your company has been approved.
                    </InfoBox>
                </>
            }
        >
            {message && <Alert className="mt-4">{message}</Alert>}

            <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4" noValidate>
                {fields.map((field) => (
                    <TextField
                        key={field.name}
                        id={field.name}
                        name={field.name}
                        type={field.type}
                        label={field.label}
                        autoComplete={field.autoComplete}
                        required
                        error={errors[field.name]?.[0]}
                        value={form[field.name]}
                        onChange={(event) => setForm({ ...form, [field.name]: event.target.value })}
                    />
                ))}

                <Button type="submit" loading={submitting} className="mt-2 w-full">
                    {submitting ? 'Sending…' : 'Register'}
                </Button>
            </form>
        </AuthLayout>
    );
}
