import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';
import NotificationsMenu from './NotificationsMenu.jsx';

const navigation = [
    { to: '/app', label: 'Overview', icon: '◫' },
    { to: '/app/requests', label: 'Purchase requests', icon: '↗' },
    { to: '/app/purchase-orders', label: 'Purchase orders', icon: '▤', permission: 'purchase_orders.view' },
    { to: '/app/assignments', label: 'Check-out', icon: '⇥', permission: 'asset_requests.create' },
    { to: '/app/transfers', label: 'Transfers', icon: '⇄', permission: 'transfers.create' },
    { to: '/app/maintenance', label: 'Maintenance', icon: '⌁', permission: 'maintenance.create' },
    { to: '/app/licenses', label: 'Software licenses', icon: '◈', permission: 'licenses.request' },
    { to: '/app/lifecycle', label: 'Lifecycle & disposal', icon: '⟳', permission: 'disposals.create' },
    { to: '/app/assets', label: 'Asset register', icon: '▣', permission: 'assets.view' },
];

export default function AppLayout() {
    const { user, logout, hasPermission, error } = useAuth();
    const navigate = useNavigate();

    async function signOut() {
        await logout();
        navigate('/login');
    }

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
            <aside className="flex flex-col border-b border-slate-200 bg-white px-5 py-5 lg:min-h-screen lg:border-b-0 lg:border-r">
                <Link to="/app" className="flex items-center gap-3">
                    <span className="grid size-10 place-items-center rounded-xl bg-blue-700 text-lg font-bold text-white">A</span>
                    <span>
                        <span className="block text-sm font-bold tracking-tight">AeroTech</span>
                        <span className="block text-xs text-slate-500">Asset operations</span>
                    </span>
                </Link>

                <p className="mb-3 mt-9 px-3 text-[11px] font-semibold uppercase tracking-[0.15em] text-slate-400">
                    Workspace
                </p>
                <nav className="flex gap-2 overflow-x-auto lg:flex-col">
                    {navigation.filter((item) => !item.permission || hasPermission(item.permission)).map((item) => (
                        <NavLink
                            key={item.to}
                            end={item.to === '/app'}
                            to={item.to}
                            className={({ isActive }) => [
                                'flex shrink-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                                isActive ? 'bg-blue-50 text-blue-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            ].join(' ')}
                        >
                            <span aria-hidden="true" className="text-base">{item.icon}</span>
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <div className="mt-auto hidden rounded-xl bg-slate-50 p-4 lg:block">
                    <p className="text-xs font-semibold text-slate-700">Three-site inventory</p>
                    <p className="mt-1 text-xs leading-5 text-slate-500">HQ · Cebu · Davao<br />Remote and field teams</p>
                </div>
            </aside>

            <div className="min-w-0">
                <header className="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
                    <div>
                        <p className="text-xs text-slate-500">AeroTech Solutions Inc.</p>
                        <p className="text-sm font-semibold text-slate-800">IT asset management</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <NotificationsMenu />
                        <div className="hidden text-right sm:block">
                            <p className="text-sm font-semibold">{user.name}</p>
                            <p className="text-xs text-slate-500">{user.roles.join(' · ')}</p>
                        </div>
                        <button
                            type="button"
                            onClick={signOut}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Sign out
                        </button>
                    </div>
                </header>
                {error && <div role="alert" className="mx-5 mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:mx-8">{error}</div>}
                <main className="mx-auto max-w-7xl px-5 py-7 sm:px-8 sm:py-9">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
