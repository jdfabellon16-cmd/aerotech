import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const requestSchema = z.object({
    software_name: z.string().trim().min(2).max(255),
    business_justification: z.string().trim().min(10).max(5000),
});

export default function LicensesPage() {
    const { hasPermission } = useAuth();
    const cache = useQueryClient();
    const licenses = useQuery({ queryKey: ['licenses'], queryFn: async () => (await api.get('/licenses')).data.data });
    const requests = useQuery({ queryKey: ['license-requests'], queryFn: async () => (await api.get('/license-requests')).data.data });
    const reviews = useQuery({
        queryKey: ['license-renewals'],
        queryFn: async () => (await api.get('/licenses/renewal-reviews')).data.data,
        enabled: hasPermission('licenses.manage') || hasPermission('licenses.review.renewals'),
    });
    const users = useQuery({
        queryKey: ['directory', 'users'],
        queryFn: async () => (await api.get('/directory/users')).data.data,
        enabled: hasPermission('licenses.assign'),
    });
    const mutation = useMutation({
        mutationFn: (values) => api.post('/license-requests', values),
        onSuccess: () => cache.invalidateQueries({ queryKey: ['license-requests'] }),
    });
    const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(requestSchema),
        defaultValues: { software_name: '', business_justification: '' },
    });

    if (licenses.isLoading || requests.isLoading) return <p className="text-sm text-slate-500">Loading software portfolio…</p>;
    if (licenses.isError || requests.isError) return <p role="alert" className="text-sm text-red-700">License data could not be loaded.</p>;

    async function approveRequest(request, decision) {
        await api.post(`/license-requests/${request.id}/decision`, {
            decision,
            reason: decision === 'rejected' ? 'Please provide more detail about the software need.' : undefined,
        });
        cache.invalidateQueries({ queryKey: ['license-requests'] });
    }

    async function recordLicense(event, request) {
        event.preventDefault();
        const values = new FormData(event.currentTarget);
        await api.post(`/license-requests/${request.id}/record`, {
            vendor: values.get('vendor'),
            total_seats: Number(values.get('total_seats')),
            cost: Number(values.get('cost')),
            expires_at: values.get('expires_at') || null,
        });
        cache.invalidateQueries({ queryKey: ['license-requests'] });
        cache.invalidateQueries({ queryKey: ['licenses'] });
    }

    async function assignSeat(event, licenseId) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        await api.post(`/licenses/${licenseId}/assignments`, { user_id: Number(data.get('user_id')) });
        cache.invalidateQueries({ queryKey: ['licenses'] });
    }

    async function runCompliance() {
        await api.post('/licenses/compliance-checks');
        cache.invalidateQueries({ queryKey: ['compliance'] });
    }

    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Software license management</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">License portfolio &amp; compliance</h1>
                <p className="mt-2 text-sm text-slate-500">Manager approval precedes Purchasing record-keeping. Active assignments are blocked when purchased seats are exhausted.</p>
            </header>

            {hasPermission('licenses.request') && (
                <form onSubmit={handleSubmit(async (values) => { await mutation.mutateAsync(values); reset(); })} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Software requested<input {...register('software_name')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" />{errors.software_name && <span className="font-normal text-red-700">{errors.software_name.message}</span>}</label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700 sm:col-span-2">Business justification<textarea rows="2" {...register('business_justification')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" />{errors.business_justification && <span className="font-normal text-red-700">{errors.business_justification.message}</span>}</label>
                    <div className="sm:col-span-2"><button disabled={isSubmitting || mutation.isPending} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Request a license</button></div>
                </form>
            )}

            <section className="grid gap-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 className="font-semibold text-slate-900">License inventory</h2><p className="mt-1 text-xs text-slate-500">Seat counts show currently active allocations.</p></div>
                    {hasPermission('licenses.manage') && <button onClick={runCompliance} className="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-50">Run compliance check</button>}
                </div>
                {licenses.data.map((license) => (
                    <article key={license.id} className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><h3 className="font-semibold text-slate-900">{license.name}</h3><p className="mt-1 text-xs text-slate-500">{license.vendor} · {license.expires_at ? `Expires ${license.expires_at}` : 'No expiry recorded'}</p></div>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${license.assigned_seats >= license.total_seats ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'}`}>{license.assigned_seats} / {license.total_seats} seats</span>
                        </div>
                        {hasPermission('licenses.assign') && users.data && license.assigned_seats < license.total_seats && (
                            <form onSubmit={(event) => assignSeat(event, license.id)} className="mt-3 flex flex-wrap gap-2">
                                <select name="user_id" required className="min-w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Assign to employee</option>{users.data.map((person) => <option value={person.id} key={person.id}>{person.name}</option>)}</select>
                                <button className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Assign seat</button>
                            </form>
                        )}
                    </article>
                ))}
            </section>

            <section className="grid gap-3">
                <h2 className="font-semibold text-slate-900">License requests</h2>
                {requests.data.map((request) => (
                    <article key={request.id} className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex flex-wrap justify-between gap-2"><div><h3 className="font-semibold text-slate-900">{request.software_name} <span className="font-normal text-slate-400">· {request.request_number}</span></h3><p className="mt-1 text-sm text-slate-600">{request.business_justification}</p><p className="mt-1 text-xs text-slate-500">{request.requester?.name}</p></div><span className="h-fit rounded-full bg-slate-100 px-3 py-1 text-xs">{request.status.replaceAll('_', ' ')}</span></div>
                        {hasPermission('licenses.approve.manager') && request.status === 'pending_manager_approval' && <div className="mt-3 flex gap-2"><button onClick={() => approveRequest(request, 'approved')} className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Approve for purchasing</button><button onClick={() => approveRequest(request, 'rejected')} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700">Reject with reason</button></div>}
                        {hasPermission('licenses.manage') && request.status === 'pending_purchasing' && (
                            <form onSubmit={(event) => recordLicense(event, request)} className="mt-4 grid gap-2 sm:grid-cols-4">
                                <input name="vendor" required placeholder="Vendor" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                <input name="total_seats" type="number" required min="1" placeholder="Seats" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                <input name="cost" type="number" required min="0" step="0.01" placeholder="Total cost" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                <input name="expires_at" type="date" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                <button className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white sm:col-span-4">Record purchased license</button>
                            </form>
                        )}
                    </article>
                ))}
            </section>

            {reviews.isSuccess && (
                <section className="grid gap-3">
                    <h2 className="font-semibold text-slate-900">Renewal reviews</h2>
                    {reviews.data.data.map((review) => (
                        <article key={review.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                            <div><h3 className="font-semibold text-slate-900">{review.license?.name} · expires {review.expires_at}</h3><p className="mt-1 text-xs text-slate-600">{review.days_before_expiry}-day notice · {review.status.replaceAll('_', ' ')}</p></div>
                            {((review.status === 'pending_it_review' && hasPermission('licenses.manage')) || (review.status === 'pending_management_review' && hasPermission('licenses.review.renewals'))) && <button onClick={async () => { await api.post(`/licenses/renewal-reviews/${review.id}`, { notes: 'Reviewed renewal needs and budget impact.' }); cache.invalidateQueries({ queryKey: ['license-renewals'] }); }} className="rounded-lg bg-amber-700 px-3 py-2 text-xs font-semibold text-white">Complete review step</button>}
                        </article>
                    ))}
                    {!reviews.data.data.length && <p className="text-sm text-slate-500">No renewal reviews are due.</p>}
                </section>
            )}
        </div>
    );
}
