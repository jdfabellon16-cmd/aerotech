import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const schema = z.object({
    asset_id: z.coerce.number().int().positive(),
    issue_description: z.string().trim().min(10).max(5000),
    priority: z.enum(['low', 'normal', 'high', 'critical']),
});

export default function MaintenancePage() {
    const { hasPermission } = useAuth();
    const cache = useQueryClient();
    const tickets = useQuery({ queryKey: ['maintenance-tickets'], queryFn: async () => (await api.get('/maintenance-tickets')).data.data });
    const assets = useQuery({
        queryKey: ['my-assets', 'maintenance'],
        queryFn: async () => (await api.get(hasPermission('assets.view') ? '/assets' : '/my-assets')).data.data,
    });
    const mutation = useMutation({
        mutationFn: (values) => api.post('/maintenance-tickets', values),
        onSuccess: () => {
            cache.invalidateQueries({ queryKey: ['maintenance-tickets'] });
            cache.invalidateQueries({ queryKey: ['assets'] });
        },
    });
    const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(schema),
        defaultValues: { asset_id: '', issue_description: '', priority: 'normal' },
    });

    if (tickets.isLoading || assets.isLoading) return <p className="text-sm text-slate-500">Loading maintenance workspace…</p>;
    if (tickets.isError || assets.isError) return <p role="alert" className="text-sm text-red-700">Maintenance data could not be loaded.</p>;

    async function recordRepair(event, ticket) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        await api.post(`/maintenance-tickets/${ticket.id}/repairs`, {
            cost: Number(data.get('cost')),
            paid: data.get('paid') === 'true',
            hr_cost_deduction_flag: false,
            repair_summary: data.get('repair_summary'),
        });
        cache.invalidateQueries({ queryKey: ['maintenance-tickets'] });
    }

    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Maintenance &amp; repair</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">Service desk</h1>
                <p className="mt-2 text-sm text-slate-500">Incident reports start an SLA timer and automatically check the registered warranty expiry.</p>
            </header>
            <form onSubmit={handleSubmit(async (values) => { await mutation.mutateAsync(values); reset(); })} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Asset
                    <select {...register('asset_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                        <option value="">Select asset</option>
                        {assets.data.map((asset) => <option key={asset.id} value={asset.id}>{asset.asset_tag} · {asset.model}</option>)}
                    </select>
                    {errors.asset_id && <span className="font-normal text-red-700">{errors.asset_id.message}</span>}
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-slate-700">Priority
                    <select {...register('priority')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal">
                        <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="critical">Critical</option>
                    </select>
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-slate-700 sm:col-span-2">Incident details
                    <textarea rows="3" {...register('issue_description')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" />
                    {errors.issue_description && <span className="font-normal text-red-700">{errors.issue_description.message}</span>}
                </label>
                <div className="sm:col-span-2"><button disabled={isSubmitting || mutation.isPending} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Report incident</button></div>
            </form>

            <section className="grid gap-3">
                {tickets.data.map((ticket) => (
                    <article key={ticket.id} className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div><h2 className="font-semibold text-slate-900">{ticket.ticket_number} · {ticket.asset?.asset_tag}</h2><p className="mt-1 text-sm text-slate-600">{ticket.issue_description}</p></div>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">{ticket.status.replaceAll('_', ' ')}</span>
                        </div>
                        <div className="mt-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                            <p>Priority: <strong className="text-slate-900">{ticket.priority}</strong></p>
                            <p>SLA due: <strong className="text-slate-900">{new Date(ticket.sla_due_at).toLocaleString()}</strong></p>
                            <p>Warranty: <strong className={ticket.warranty_covered ? 'text-emerald-700' : 'text-amber-700'}>{ticket.warranty_covered ? 'Covered' : 'Not covered'}</strong></p>
                        </div>
                        {hasPermission('maintenance.manage') && ticket.status !== 'closed' && (
                            <form onSubmit={(event) => recordRepair(event, ticket)} className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[140px_180px_1fr_auto] sm:items-end">
                                <label className="grid gap-1 text-xs text-slate-600">Repair cost<input name="cost" type="number" min="0" step="0.01" defaultValue="0" required className="rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
                                <label className="grid gap-1 text-xs text-slate-600">Cost type<select name="paid" className="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="false">Warranty / no cost</option><option value="true">Paid repair</option></select></label>
                                <label className="grid gap-1 text-xs text-slate-600">Repair summary<input name="repair_summary" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" /></label>
                                <button className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Record repair</button>
                            </form>
                        )}
                        {ticket.repairs?.map((repair) => (
                            <div key={repair.id} className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-xs">
                                <span>Repair cost {repair.cost} · {repair.cost_approved ? 'Approved' : 'Approval required'}</span>
                                <div className="flex gap-2">
                                    {hasPermission('repairs.approve') && repair.paid && !repair.cost_approved && <button onClick={async () => { await api.post(`/repairs/${repair.id}/cost-approval`, { hr_cost_deduction_flag: false }); cache.invalidateQueries({ queryKey: ['maintenance-tickets'] }); }} className="rounded-md bg-amber-700 px-3 py-1.5 font-semibold text-white">Approve cost</button>}
                                    {hasPermission('maintenance.manage') && repair.cost_approved && !repair.completed_at && <button onClick={async () => { await api.post(`/repairs/${repair.id}/completion`); cache.invalidateQueries({ queryKey: ['maintenance-tickets'] }); cache.invalidateQueries({ queryKey: ['assets'] }); }} className="rounded-md bg-emerald-700 px-3 py-1.5 font-semibold text-white">Complete repair</button>}
                                </div>
                            </div>
                        ))}
                    </article>
                ))}
                {!tickets.data.length && <p className="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No maintenance tickets found.</p>}
            </section>
        </div>
    );
}
