import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { useAuth } from '../auth/AuthContext';

export default function Login() {
    const { user, loading, login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    if (loading === false && user !== null) {
        return <Navigate to="/" replace />;
    }

    async function handleSubmit(event) {
        event.preventDefault();

        setErrors({});
        setSubmitting(true);

        try {
            await login({ email, password });
            navigate('/', { replace: true });
        } catch (error) {
            const status = error.response?.status;

            if (status === 422) {
                setErrors(error.response.data.errors ?? {});
            } else if (status === 429) {
                setErrors({ email: ['Too many attempts. Please try again in a minute.'] });
            } else {
                setErrors({ email: ['Sign in failed. Please try again.'] });
            }

            setSubmitting(false);
        }
    }

    return (
        <div className="flex min-h-full items-center justify-center px-6 py-16">
            <div className="w-full max-w-sm">
                <h1 className="text-lg font-semibold tracking-tight">Sign in to KiDas</h1>

                <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-4" noValidate>
                    <div className="flex flex-col gap-1">
                        <label htmlFor="email" className="text-sm font-medium text-slate-700">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        />
                        {errors.email && (
                            <p className="text-sm text-red-600">{errors.email[0]}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1">
                        <label htmlFor="password" className="text-sm font-medium text-slate-700">
                            Password
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="current-password"
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
            </div>
        </div>
    );
}
