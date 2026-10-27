import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const requestSchema = z.object({
    asset_category_id: z.coerce.number().int().positive('Choose an equipment category.'),
    justification: z.string().trim().min(10).max(5000),
    urgency: z.enum(['low', 'normal', 'high', 'critical']),
});

const assignmentSchema = z.object({
    asset_id: z.coerce.number().int().positive('Select an available asset.'),
    handoff_method: z.enum(['physical', 'courier']),
    policy_notes: z.string().trim().min(5, 'Record the equipment policy check.'),
});

export default function AssetRequestsPage() {
    const { user, hasPermission } = useAuth();
    const cache = useQueryClient();
    const requests = useQuery({
        queryKey: ['asset-requests'],
        queryFn: async () => (await api.get('/asset-requests')).data,
    });
    const categories = useQuery({
        queryKey: ['asset-categories'],
        queryFn: async () => (await api.get('/asset-categories')).data.data,
    });
    const assets = useQuery({
        queryKey: ['assets', 'for-assignment'],
        queryFn: async () => (await api.get(hasPermission('assets.view') ? '/assets' : '/my-assets')).data.data,
    });
    const create = useMutation({
        mutationFn: (values) => api.post('/asset-requests', values),
        onSuccess: () => {
            cache.invalidateQueries({ queryKey: ['asset-requests'] });
            cache.invalidateQueries({ queryKey: ['dashboard'] });
        },
    });
    const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(requestSchema),
        defaultValues: { asset_category_id: '', justification: '', urgency: 'normal' },
    });

    if (requests.isLoading || categories.isLoading || assets.isLoading) {
        return <p className="text-sm text-slate-500">Loading check-out workflow…</p>;
    }
    if (requests.isError || categories.isError || assets.isError) {
        return <p role="alert" className="text-sm text-red-700">Check-out data could not be loaded.</p>;
    }

    async function acknowledge(assignmentId, signatureName) {
        await api.post(`/assignments/${assignmentId}/acknowledgement`, { signature_name: signatureName });
        cache.invalidateQueries({ queryKey: ['asset-requests'] });
        cache.invalidateQueries({ queryKey: ['assets'] });
    }

    async function assign(event, assetRequestId) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const values = {
            asset_id: Number(form.get('asset_id')),
            handoff_method: form.get('handoff_method'),
            policy_notes: form.get('policy_notes'),
        };
        const validation = assignmentSchema.safeParse(values);
        if (!validation.success) {
            window.alert(validation.error.issues[0].message);
            return;
        }
        if (values.handoff_method === 'courier' && !form.get('evidence')?.size) {
            window.alert('Upload courier delivery evidence before assigning.');
            return;
        }
        await api.post(`/asset-requests/${assetRequestId}/assignments`, form);
        cache.invalidateQueries({ queryKey: ['asset-requests'] });
        cache.invalidateQueries({ queryKey: ['assets'] });
    }

    const assetRows = assets.data ?? [];
    const requestRows = requests.data.data ?? [];
    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Asset assignment & check-out</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">Equipment requests</h1>
                <p className="mt-2 text-sm text-slate-500">IT verifies standard-equipment policy, matches the requester and asset, records handoff evidence, and waits for acknowledgement.</p>
            </header>

            {hasPermission('asset_requests.create') && (
                <form
                    onSubmit={handleSubmit(async (values) => {
                        await create.mutateAsync(values);
                        reset({ asset_category_id: '', justification: '', urgency: 'normal' });
                    })}
                    className="grid gap-4 rounded-xl border border-blue-100 bg-blue-50/50 p-5 sm:grid-cols-2"
                >
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                        Equipment category
                        <select {...register('asset_category_id')} className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal">
                            <option value="">Choose a category</option>
                            {categories.data.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                        </select>
                        {errors.asset_category_id && <span className="font-normal text-red-700">{errors.asset_category_id.message}</span>}
                    </label>
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                        Urgency
                        <select {...register('urgency')} className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal">
                            {['low', 'normal', 'high', 'critical'].map((urgency) => <option key={urgency} value={urgency}>{urgency}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700 sm:col-span-2">
                        Business need
                        <textarea {...register('justification')} rows="3" className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal" />
                        {errors.justification && <span className="font-normal text-red-700">{errors.justification.message}</span>}
                    </label>
                    <div className="sm:col-span-2">
                        <button disabled={isSubmitting || create.isPending} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60">Submit equipment request</button>
                    </div>
                </form>
            )}

            <section className="grid gap-4">
                {requestRows.map((request) => {
                    const availableAssets = assetRows.filter((asset) => asset.status === 'available' && asset.category?.id === request.asset_category_id);
                    const assignment = request.assignment;
                    return (
                        <article key={request.id} className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold text-slate-900">{request.category?.name} <span className="font-normal text-slate-400">· {request.request_number}</span></h2>
                                    <p className="mt-1 text-sm text-slate-600">{request.requester?.name} · {request.justification}</p>
                                </div>
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{request.status.replaceAll('_', ' ')}</span>
                            </div>

                            {assignment?.status === 'pending_acknowledgement' && assignment.assigned_to_id === user.id && (
                                <form onSubmit={async (event) => {
                                    event.preventDefault();
                                    const signature = new FormData(event.currentTarget).get('signature_name');
                                    await acknowledge(assignment.id, signature);
                                }} className="mt-4 flex flex-col gap-3 rounded-lg bg-amber-50 p-4 sm:flex-row sm:items-end">
                                    <p className="flex-1 text-sm text-amber-900">Asset {assignment.asset?.asset_tag} is ready. Your acknowledgement is required to close check-out.</p>
                                    <label className="grid gap-1 text-xs font-medium text-slate-600">
                                        Type your full name
                                        <input name="signature_name" required minLength="2" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                                    </label>
                                    <button className="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white">Acknowledge</button>
                                    <a href={`/api/v1/assignments/${assignment.id}/accountability-form`} target="_blank" rel="noreferrer" className="text-xs font-semibold text-blue-800 underline">Printable form</a>
                                </form>
                            )}

                            {hasPermission('assets.manage') && request.status === 'open' && (
                                <form onSubmit={(event) => assign(event, request.id)} className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2">
                                    <label className="grid gap-1 text-xs font-medium text-slate-600">
                                        Assign matching asset
                                        <select name="asset_id" required className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                            <option value="">Choose available item</option>
                                            {availableAssets.map((asset) => <option key={asset.id} value={asset.id}>{asset.asset_tag} · {asset.model}</option>)}
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-xs font-medium text-slate-600">
                                        Handoff
                                        <select name="handoff_method" className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                            <option value="physical">Physical handoff</option>
                                            <option value="courier">Courier delivery</option>
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-xs font-medium text-slate-600 sm:col-span-2">
                                        Equipment policy check
                                        <input name="policy_notes" required minLength="5" placeholder="Policy reviewed and approved" className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm" />
                                    </label>
                                    <label className="grid gap-1 text-xs font-medium text-slate-600 sm:col-span-2">
                                        Courier evidence (required for courier handoff)
                                        <input name="evidence" type="file" accept=".pdf,.png,.jpg,.jpeg" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                                    </label>
                                    <div className="sm:col-span-2">
                                        <button className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Assign and create accountability form</button>
                                    </div>
                                </form>
                            )}
                            {assignment?.status === 'active' && <p className="mt-3 text-xs font-medium text-emerald-700">Acknowledged by {assignment.assignee?.name}.</p>}
                            {assignment?.status === 'active' && assignment.assigned_to_id === user.id && <button onClick={async () => { await api.post(`/assignments/${assignment.id}/check-in`); cache.invalidateQueries({ queryKey: ['asset-requests'] }); cache.invalidateQueries({ queryKey: ['assets'] }); }} className="mt-3 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Check asset in</button>}
                            {assignment?.id && <a className="mt-3 inline-block text-xs font-semibold text-blue-800 underline" href={`/api/v1/assignments/${assignment.id}/accountability-form`} target="_blank" rel="noreferrer">Open accountability form</a>}
                        </article>
                    );
                })}
                {!requestRows.length && <p className="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No equipment requests to show.</p>}
            </section>
        </div>
    );
}
