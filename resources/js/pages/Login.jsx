import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { useAuth } from '../auth/AuthContext';

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
        <div className="flex min-h-full items-center justify-center px-6 py-16">
            <div className="w-full max-w-sm">
                <h1 className="text-lg font-semibold tracking-tight">Sign in to KiDas</h1>

                {message && (
                    <p
                        role="alert"
                        className="mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    >
                        {message}
                    </p>
                )}

                <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4" noValidate>
                    <div className="flex flex-col gap-1">
                        <label htmlFor="email" className="text-sm font-medium text-slate-700 after:ml-0.5 after:text-red-600 after:content-['*']">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            required
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.email && (
                            <p className="text-sm text-red-600">{errors.email[0]}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="password" className="text-sm font-medium text-slate-700 after:ml-0.5 after:text-red-600 after:content-['*']">
                            Password
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.password && (
                            <p className="text-sm text-red-600">{errors.password[0]}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={submitting}
                        className="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {submitting ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>

                <p className="mt-6 text-sm text-slate-500">
                    New supplier?{' '}
                    <Link to="/register" className="font-medium text-slate-900 underline">
                        Register your company
                    </Link>
                </p>
            </div>
        </div>
    );
}
