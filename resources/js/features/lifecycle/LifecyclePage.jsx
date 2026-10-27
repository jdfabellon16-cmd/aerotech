import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

export default function LifecyclePage() {
    const { hasPermission } = useAuth();
    const cache = useQueryClient();
    const [error, setError] = useState('');
    const reviews = useQuery({
        queryKey: ['lifecycle-reviews'],
        queryFn: async () => (await api.get('/lifecycle/reviews')).data.data,
        enabled: hasPermission('lifecycle.manage'),
    });
    const disposals = useQuery({ queryKey: ['disposals'], queryFn: async () => (await api.get('/disposals')).data.data });
    const assets = useQuery({
        queryKey: ['my-assets', 'disposals'],
        queryFn: async () => (await api.get(hasPermission('assets.view') ? '/assets' : '/my-assets')).data.data,
    });
    const scan = useMutation({
        mutationFn: () => api.post('/lifecycle/reviews/scan'),
        onSuccess: () => cache.invalidateQueries({ queryKey: ['lifecycle-reviews'] }),
        onError: (requestError) => setError(requestError.response?.data?.message ?? 'Lifecycle scan failed.'),
    });

    if (disposals.isLoading || assets.isLoading || (reviews.isLoading && hasPermission('lifecycle.manage'))) return <p className="text-sm text-slate-500">Loading lifecycle workspace…</p>;
    if (disposals.isError || assets.isError || (reviews.isError && hasPermission('lifecycle.manage'))) return <p role="alert" className="text-sm text-red-700">Lifecycle data could not be loaded.</p>;

    async function requestDisposal(assetId, disposalType) {
        try {
            await api.post('/disposals', { asset_id: assetId, disposal_type: disposalType });
            setError('');
            cache.invalidateQueries({ queryKey: ['disposals'] });
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Disposal review could not be requested.');
        }
    }

    async function decide(disposal, stage) {
        try {
            await api.post(`/disposals/${disposal.id}/${stage}-approval`);
            cache.invalidateQueries({ queryKey: ['disposals'] });
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Approval could not be recorded.');
        }
    }

    async function complete(event, disposal) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        try {
            await api.post(`/disposals/${disposal.id}/completion`, form);
            setError('');
            cache.invalidateQueries({ queryKey: ['disposals'] });
            cache.invalidateQueries({ queryKey: ['assets'] });
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Disposal could not be completed.');
        }
    }

    const dueReviews = reviews.data?.data ?? [];
    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Asset retirement &amp; disposal</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">Lifecycle planning</h1>
                <p className="mt-2 text-sm text-slate-500">Age and incident-frequency rules identify assets for review. Finance and management approvals precede a documented, secure disposal.</p>
            </header>
            {error && <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}

            {hasPermission('lifecycle.manage') && (
                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 className="font-semibold text-slate-900">Due for lifecycle review</h2><p className="mt-1 text-xs text-slate-500">Retirement age or repeated incidents trigger a review.</p></div>
                        <button onClick={() => scan.mutate()} disabled={scan.isPending} className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">{scan.isPending ? 'Scanning…' : 'Run lifecycle scan'}</button>
                    </div>
                    <div className="mt-4 grid gap-2">
                        {dueReviews.map((review) => (
                            <div key={review.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3">
                                <p className="text-sm font-medium text-slate-800">{review.asset?.asset_tag} · {review.asset?.category?.name} · {review.incident_count} incidents</p>
                                <button onClick={() => requestDisposal(review.asset_id, 'retirement')} className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">Start retirement approval</button>
                            </div>
                        ))}
                        {!dueReviews.length && <p className="text-sm text-slate-500">No assets are currently flagged.</p>}
                    </div>
                </section>
            )}

            {hasPermission('disposals.create') && (
                <section className="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="font-semibold text-slate-900">Request retirement or disposal</h2>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <select id="disposal-asset" className="min-w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select asset</option>
                            {assets.data.map((asset) => <option value={asset.id} key={asset.id}>{asset.asset_tag} · {asset.model}</option>)}
                        </select>
                        {['retirement', 'disposal', 'donation'].map((type) => (
                            <button key={type} onClick={() => {
                                const assetId = document.getElementById('disposal-asset').value;
                                if (assetId) requestDisposal(Number(assetId), type);
                            }} className="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold capitalize text-blue-800 hover:bg-blue-50">Request {type}</button>
                        ))}
                    </div>
                </section>
            )}

            <section className="grid gap-3">
                <h2 className="font-semibold text-slate-900">Approval and disposal queue</h2>
                {disposals.data.map((disposal) => (
                    <article key={disposal.id} className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div><h3 className="font-semibold text-slate-900">{disposal.asset?.asset_tag} · {disposal.disposal_type}</h3><p className="mt-1 text-xs text-slate-500">{disposal.asset?.model}</p></div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs">{disposal.status.replaceAll('_', ' ')}</span>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {hasPermission('disposals.approve.finance') && disposal.status === 'pending_finance_approval' && <button onClick={() => decide(disposal, 'finance')} className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Finance approval</button>}
                            {hasPermission('disposals.approve.management') && disposal.status === 'pending_management_approval' && <button onClick={() => decide(disposal, 'management')} className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Management approval</button>}
                        </div>
                        {hasPermission('disposals.complete') && disposal.status === 'pending_completion' && (
                            <form onSubmit={(event) => complete(event, disposal)} className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                                <label className="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="data_wipe_done" value="1" required /> Data wipe completed</label>
                                <label className="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="company_files_removed" value="1" required /> Company files removed</label>
                                <label className="grid gap-1 text-xs text-slate-600">Recipient / vendor<input name="recipient_or_vendor" required className="rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
                                <label className="grid gap-1 text-xs text-slate-600">Certificate of data destruction<input name="certificate" type="file" required accept=".pdf,.png,.jpg,.jpeg" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
                                <button className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">Complete and update asset status</button>
                            </form>
                        )}
                    </article>
                ))}
                {!disposals.data.length && <p className="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No disposal requests yet.</p>}
            </section>
        </div>
    );
}
