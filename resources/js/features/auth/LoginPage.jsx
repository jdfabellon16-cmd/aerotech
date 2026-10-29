import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Link, Navigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';

const loginSchema = z.object({
    email: z.email(),
    password: z.string().min(1, 'Enter your password.'),
});

export default function LoginPage() {
    const { user, login } = useAuth();
    const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(loginSchema),
        defaultValues: { email: '', password: '' },
    });

    if (user) {
        return <Navigate to="/app" replace />;
    }

    async function submit(values) {
        try {
            await login(values);
        } catch (error) {
            const message = error.response?.data?.errors?.email?.[0] ?? 'Sign-in failed. Try again.';
            setError('root', { message });
        }
    }

    return (
        <main className="grid min-h-screen bg-white lg:grid-cols-[1.05fr_0.95fr]">
            <section className="relative hidden overflow-hidden bg-slate-950 px-14 py-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div className="absolute -right-24 top-20 size-96 rounded-full border border-blue-400/15" />
                <div className="absolute -right-8 top-36 size-64 rounded-full border border-blue-400/20" />
                <div className="relative flex items-center gap-3">
                    <span className="grid size-10 place-items-center rounded-xl bg-blue-500 text-lg font-bold">A</span>
                    <span className="text-sm font-semibold">AeroTech Solutions Inc.</span>
                </div>
                <div className="relative max-w-lg">
                    <p className="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-blue-300">One system. Every asset.</p>
                    <h1 className="text-4xl font-semibold leading-tight tracking-tight">Manage the lifecycle of every device with confidence.</h1>
                    <p className="mt-5 max-w-md text-sm leading-6 text-slate-300">Procurement, accountability, maintenance and compliance for teams across HQ, Cebu, Davao and the field.</p>
                </div>
                <p className="relative text-xs text-slate-400">Internal IT operations · v1</p>
            </section>
            <section className="flex items-center justify-center px-6 py-12">
                <div className="w-full max-w-sm">
                    <p className="text-sm font-semibold text-blue-700 lg:hidden">AeroTech Asset Operations</p>
                    <h2 className="mt-8 text-2xl font-semibold tracking-tight text-slate-900 lg:mt-0">Welcome back</h2>
                    <p className="mt-2 text-sm text-slate-500">Sign in with your AeroTech account.</p>
                    <form onSubmit={handleSubmit(submit)} className="mt-8 grid gap-5">
                        <label className="grid gap-2 text-sm font-medium text-slate-700">
                            Work email
                            <input
                                {...register('email')}
                                type="email"
                                autoComplete="username"
                                className="rounded-lg border border-slate-300 px-3.5 py-3 outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                                placeholder="name@aerotech.example"
                            />
                            {errors.email && <span className="text-xs text-red-600">{errors.email.message}</span>}
                        </label>
                        <label className="grid gap-2 text-sm font-medium text-slate-700">
                            Password
                            <input
                                {...register('password')}
                                type="password"
                                autoComplete="current-password"
                                className="rounded-lg border border-slate-300 px-3.5 py-3 outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                            />
                            {errors.password && <span className="text-xs text-red-600">{errors.password.message}</span>}
                        </label>
                        {errors.root && <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{errors.root.message}</p>}
                        <button
                            disabled={isSubmitting}
                            className="rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {isSubmitting ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>
                    <p className="mt-6 text-center text-sm text-slate-500">
                        New to AeroTech?{' '}
                        <Link to="/register" className="font-semibold text-blue-700 hover:text-blue-800">
                            Create an account
                        </Link>
                    </p>
                </div>
            </section>
        </main>
    );
}
