import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const schema = z.object({
    asset_id: z.coerce.number().int().positive(),
    to_user_id: z.coerce.number().int().positive(),
    to_location_id: z.coerce.number().int().positive(),
    transfer_type: z.enum(['permanent', 'temporary']),
    reason: z.string().trim().min(5).max(2000),
    loan_started_at: z.string().optional(),
    due_at: z.string().optional(),
    replacement_expected_at: z.string().optional(),
}).superRefine((values, context) => {
    if (values.transfer_type === 'temporary') {
        if (!values.loan_started_at) context.addIssue({ code: 'custom', path: ['loan_started_at'], message: 'Loan start date is required.' });
        if (!values.due_at) context.addIssue({ code: 'custom', path: ['due_at'], message: 'Loan due date is required.' });
        if (values.loan_started_at && values.due_at) {
            const start = new Date(`${values.loan_started_at}T00:00:00`);
            const due = new Date(`${values.due_at}T00:00:00`);
            const maximum = new Date(start);
            maximum.setMonth(maximum.getMonth() + 1);
            if (due < start || due > maximum) context.addIssue({ code: 'custom', path: ['due_at'], message: 'Loan due date must be within one month.' });
        }
    }
});

export default function TransfersPage() {
    const { user, hasPermission } = useAuth();
    const cache = useQueryClient();
    const assets = useQuery({
        queryKey: ['my-assets', 'transfer'],
        queryFn: async () => (await api.get(hasPermission('assets.manage') ? '/assets' : '/my-assets')).data.data,
    });
    const people = useQuery({ queryKey: ['directory', 'users'], queryFn: async () => (await api.get('/directory/users')).data.data });
    const locations = useQuery({ queryKey: ['directory', 'locations'], queryFn: async () => (await api.get('/directory/locations')).data.data });
    const transfers = useQuery({ queryKey: ['transfers'], queryFn: async () => (await api.get('/transfers')).data.data });
    const mutation = useMutation({
        mutationFn: (values) => api.post('/transfers', values),
        onSuccess: () => cache.invalidateQueries({ queryKey: ['transfers'] }),
    });
    const { register, handleSubmit, watch, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(schema),
        defaultValues: { transfer_type: 'permanent', asset_id: '', to_user_id: '', to_location_id: '', reason: '' },
    });
    const transferType = watch('transfer_type');

    if ([assets, people, locations, transfers].some((query) => query.isLoading)) return <p className="text-sm text-slate-500">Loading transfer workspace…</p>;
    if ([assets, people, locations, transfers].some((query) => query.isError)) return <p role="alert" className="text-sm text-red-700">Transfer data could not be loaded.</p>;

    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Movement &amp; transfers</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">Asset movement</h1>
                <p className="mt-2 text-sm text-slate-500">Transfers are approved before the receiving employee confirms custody. Temporary loans are limited to one month or the replacement date.</p>
            </header>
            {hasPermission('transfers.create') && (
                <form onSubmit={handleSubmit((values) => mutation.mutateAsync(values))} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Asset
                        <select {...register('asset_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                            <option value="">Select your asset</option>
                            {assets.data.filter((asset) => hasPermission('assets.manage') || asset.assignee_id === user.id).map((asset) => <option key={asset.id} value={asset.id}>{asset.asset_tag} · {asset.model}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Transfer type
                        <select {...register('transfer_type')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                            <option value="permanent">Permanent transfer</option><option value="temporary">Temporary loan</option>
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Receiving employee
                        <select {...register('to_user_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                            <option value="">Select employee</option>
                            {people.data.map((person) => <option key={person.id} value={person.id}>{person.name} · {person.department?.name ?? 'No department'}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Destination location
                        <select {...register('to_location_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                            <option value="">Select location</option>
                            {locations.data.filter((location) => location.type !== 'storage').map((location) => <option key={location.id} value={location.id}>{location.name}</option>)}
                        </select>
                    </label>
                    {transferType === 'temporary' && <>
                        <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Loan start<input type="date" {...register('loan_started_at')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" />{errors.loan_started_at && <span className="font-normal text-red-700">{errors.loan_started_at.message}</span>}</label>
                        <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Due date<input type="date" {...register('due_at')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" />{errors.due_at && <span className="font-normal text-red-700">{errors.due_at.message}</span>}</label>
                        <label className="grid gap-1.5 text-xs font-semibold text-slate-700 sm:col-span-2">Replacement expected (optional)<input type="date" {...register('replacement_expected_at')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    </>}
                    <label className="grid gap-1.5 text-xs font-semibold text-slate-700 sm:col-span-2">Reason<textarea rows="2" {...register('reason')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <div className="sm:col-span-2"><button disabled={isSubmitting || mutation.isPending} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Submit transfer request</button></div>
                </form>
            )}
            <section className="grid gap-3">
                {transfers.data.map((transfer) => (
                    <article key={transfer.id} className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><p className="font-semibold text-slate-900">{transfer.asset?.asset_tag} · {transfer.transfer_type} transfer</p><p className="mt-1 text-xs text-slate-500">{transfer.requester?.name} → {transfer.recipient?.name} · {transfer.destination?.name}</p>{transfer.due_at && <p className="mt-1 text-xs text-amber-700">Due {transfer.due_at}</p>}</div>
                        <div className="flex items-center gap-2"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">{transfer.status.replaceAll('_', ' ')}</span>
                            {hasPermission('transfers.manage') && transfer.status === 'pending_approval' && <button onClick={async () => { await api.post(`/transfers/${transfer.id}/approval`); cache.invalidateQueries({ queryKey: ['transfers'] }); }} className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Approve</button>}
                            {transfer.to_user_id === user.id && transfer.status === 'pending_acceptance' && <button onClick={async () => { await api.post(`/transfers/${transfer.id}/acceptance`); cache.invalidateQueries({ queryKey: ['transfers'] }); }} className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Accept &amp; confirm location</button>}
                        </div>
                    </article>
                ))}
            </section>
        </div>
    );
}
