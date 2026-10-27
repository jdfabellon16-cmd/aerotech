import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../bootstrap.js';

const statusLabels = {
    available: 'Available',
    in_use: 'In use',
    in_repair: 'In repair',
    retired: 'Retired',
    lost_stolen: 'Lost or stolen',
};

function Metric({ label, value, detail, tone = 'blue' }) {
    const tones = {
        blue: 'bg-blue-50 text-blue-700',
        amber: 'bg-amber-50 text-amber-700',
        violet: 'bg-violet-50 text-violet-700',
        green: 'bg-emerald-50 text-emerald-700',
    };

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/[0.02]">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-slate-500">{label}</p>
                <span className={`rounded-lg px-2.5 py-1 text-[11px] font-semibold ${tones[tone]}`}>{detail}</span>
            </div>
            <p className="mt-5 text-3xl font-semibold tracking-tight text-slate-900">{value ?? '—'}</p>
        </article>
    );
}

export default function DashboardPage() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['dashboard'],
        queryFn: async () => (await api.get('/dashboard')).data.data,
    });

    if (isLoading) {
        return <p className="text-sm text-slate-500">Loading operations overview…</p>;
    }

    if (isError) {
        return <p role="alert" className="text-sm text-red-700">The overview could not be loaded. Refresh to try again.</p>;
    }

    const byStatus = data.assets_by_status ?? {};
    return (
        <div className="grid gap-8">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Operations overview</p>
                    <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Good day. Here’s your fleet.</h1>
                    <p className="mt-2 text-sm text-slate-500">A single view of purchasing and inventory activity.</p>
                </div>
                <Link to="/app/requests" className="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Create purchase request <span className="ml-2" aria-hidden="true">↗</span>
                </Link>
            </div>

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
                <Metric label="Tracked assets" value={data.asset_total} detail="All sites" tone="blue" />
                <Metric label="Available" value={byStatus.available ?? 0} detail="Ready to assign" tone="green" />
                <Metric label="In use" value={byStatus.in_use ?? 0} detail="Assigned" tone="violet" />
                <Metric label="Requests awaiting action" value={data.purchase_requests.awaiting_action} detail="Approval queue" tone="amber" />
            </section>

            <section className="grid gap-5 lg:grid-cols-[1.3fr_0.7fr]">
                <article className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">Inventory health</h2>
                            <p className="mt-1 text-xs text-slate-500">Assets by current status</p>
                        </div>
                        <Link to="/app/assets" className="text-xs font-semibold text-blue-700 hover:text-blue-900">View register</Link>
                    </div>
                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        {Object.entries(statusLabels).map(([key, label]) => (
                            <div key={key} className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                                <span className="text-sm text-slate-600">{label}</span>
                                <span className="text-sm font-semibold text-slate-900">{byStatus[key] ?? 0}</span>
                            </div>
                        ))}
                    </div>
                </article>
                <article className="rounded-xl bg-slate-900 p-6 text-white">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Procurement workflow</p>
                    <h2 className="mt-3 text-xl font-semibold">A clear path from request to ready-to-use.</h2>
                    <ol className="mt-5 grid gap-3 text-sm text-slate-300">
                        <li className="flex gap-3"><span className="font-semibold text-blue-300">01</span>Department, Finance and General Manager approvals</li>
                        <li className="flex gap-3"><span className="font-semibold text-blue-300">02</span>Purchasing tracks supplier and order</li>
                        <li className="flex gap-3"><span className="font-semibold text-blue-300">03</span>Warehouse intake creates the asset record</li>
                    </ol>
                    <Link to="/app/requests" className="mt-6 inline-flex rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15">
                        Open request queue
                    </Link>
                </article>
            </section>

            <section className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Assets by category</h2>
                        <p className="mt-1 text-xs text-slate-500">Current registered inventory</p>
                    </div>
                </div>
                {!data.categories.length && <p className="mt-5 text-sm text-slate-500">No category inventory is available for your account.</p>}
                <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {data.categories.map((category) => (
                        <div key={category.id} className="rounded-lg border border-slate-100 p-4">
                            <p className="text-xs text-slate-500">{category.name}</p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">{category.assets_count}</p>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
