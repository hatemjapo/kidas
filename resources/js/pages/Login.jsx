import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import AuthLayout from '../layouts/AuthLayout';
import Alert from '../components/ui/Alert';
import Button from '../components/ui/Button';
import InfoBox from '../components/ui/InfoBox';
import TextField from '../components/ui/TextField';

export default function Login() {
    const { user, loading, login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    if (loading === false && user !== null) {
        return <Navigate to="/" replace />;
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setErrors({});
        setMessage(null);
        setSubmitting(true);

        try {
            await login({ email, password });
            navigate('/', { replace: true });
        } catch (error) {
            const status = error.response?.status;

            if (status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else if (status === 403) {
                setMessage(error.response.data.message ?? 'Sign in is not possible for this account.');
            } else if (status === 429) {
                setMessage('Too many attempts. Please try again in a minute.');
            } else {
                setMessage('Sign in failed. Please try again.');
            }

            setSubmitting(false);
        }
    }

    return (
        <AuthLayout
            title="Sign in"
            lead="Sign in with your email address."
            aside={
                <>
                    <hr className="mt-8 border-line" />

                    <p className="mt-6 text-sm text-muted">
                        No account yet?{' '}
                        <Link to="/register" className="font-semibold text-brand-700 hover:text-brand-800">
                            Register as a supplier
                        </Link>
                    </p>

                    <InfoBox className="mt-4">
                        New registrations are checked by the warehouse. Once yours is approved you will get an email
                        with a link to sign in.
                    </InfoBox>
                </>
            }
        >
            {message && <Alert className="mt-4">{message}</Alert>}

            <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4" noValidate>
                <TextField
                    id="email"
                    name="email"
                    type="email"
                    label="Email"
                    placeholder="name@company.test"
                    autoComplete="username"
                    required
                    error={errors.email?.[0]}
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                />

                <TextField
                    id="password"
                    name="password"
                    type="password"
                    label="Password"
                    placeholder="••••••••"
                    autoComplete="current-password"
                    required
                    error={errors.password?.[0]}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                />

                <Button type="submit" loading={submitting} className="mt-2 w-full">
                    {submitting ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>
        </AuthLayout>
    );
}
