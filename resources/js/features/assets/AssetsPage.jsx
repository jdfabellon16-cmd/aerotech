import { useQuery } from '@tanstack/react-query';
import api from '../../bootstrap.js';
import { formatAssetStatus } from '../../lib/assetStatus.js';

export default function AssetsPage() {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['assets'],
        queryFn: async () => (await api.get('/assets')).data,
    });

    if (isLoading) {
        return <p className="text-sm text-slate-500">Loading asset register…</p>;
    }

    if (isError) {
        return <p role="alert" className="text-sm text-red-700">Asset data could not be loaded. Refresh to try again.</p>;
    }

    return (
        <div className="grid gap-6">
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Inventory</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Asset register</h1>
                <p className="mt-2 text-sm text-slate-500">Read-only asset inventory with procurement-created records and current ownership.</p>
            </div>
            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Asset</th>
                            <th className="px-4 py-3 font-semibold">Category / model</th>
                            <th className="px-4 py-3 font-semibold">Location</th>
                            <th className="px-4 py-3 font-semibold">Assignee</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 font-semibold">Warranty</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {data.data.map((asset) => (
                            <tr key={asset.id} className="hover:bg-slate-50/70">
                                <td className="px-4 py-4">
                                    <p className="font-semibold text-slate-900">{asset.asset_tag}</p>
                                    <p className="mt-1 text-xs text-slate-500">S/N {asset.serial_number ?? '—'}</p>
                                </td>
                                <td className="px-4 py-4">
                                    <p className="text-slate-800">{asset.category?.name}</p>
                                    <p className="mt-1 text-xs text-slate-500">{asset.model}</p>
                                </td>
                                <td className="px-4 py-4 text-slate-600">{asset.location?.name ?? 'Unassigned'}</td>
                                <td className="px-4 py-4 text-slate-600">{asset.assignee?.name ?? '—'}</td>
                                <td className="px-4 py-4">
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{formatAssetStatus(asset.status)}</span>
                                </td>
                                <td className="px-4 py-4 text-slate-600">{asset.warranty_expires_at ?? '—'}</td>
                            </tr>
                        ))}
                        {!data.data.length && <tr><td colSpan="6" className="px-4 py-12 text-center text-sm text-slate-500">No assets registered yet.</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
