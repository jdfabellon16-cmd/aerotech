import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const requestSchema = z.object({
    asset_category_id: z.coerce.number().int().positive('Choose an asset type.'),
    justification: z.string().trim().min(10, 'Explain the business need (at least 10 characters).').max(5000),
    urgency: z.enum(['low', 'normal', 'high', 'critical']),
    quantity: z.coerce.number().int().min(1).max(100),
});

const stages = [
    ['department', 'Department manager'],
    ['finance', 'Finance'],
    ['general', 'General manager'],
];

const statusLabel = {
    pending_department_approval: 'Department review',
    pending_finance_approval: 'Finance review',
    pending_general_approval: 'General manager review',
    approved: 'Approved',
    rejected: 'Changes requested',
};

function RequestForm({ categories, onSubmit, submitting, initialValues }) {
    const { register, handleSubmit, reset, formState: { errors } } = useForm({
        resolver: zodResolver(requestSchema),
        defaultValues: initialValues,
    });

    async function submit(values) {
        await onSubmit(values);
        reset(initialValues);
    }

    return (
        <form onSubmit={handleSubmit(submit)} className="grid gap-4 rounded-xl border border-blue-100 bg-blue-50/50 p-5 sm:grid-cols-2">
            <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                Asset type
                <select {...register('asset_category_id')} className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal">
                    <option value="">Select category</option>
                    {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
                {errors.asset_category_id && <span className="font-normal text-red-700">{errors.asset_category_id.message}</span>}
            </label>
            <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                Quantity
                <input {...register('quantity')} type="number" min="1" max="100" className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal" />
                {errors.quantity && <span className="font-normal text-red-700">{errors.quantity.message}</span>}
            </label>
            <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                Urgency
                <select {...register('urgency')} className="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal">
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </label>
            <label className="grid gap-1.5 text-xs font-semibold text-slate-700 sm:col-span-2">
                Business justification
                <textarea {...register('justification')} rows="3" placeholder="Describe the business need, employee impact, and requested timing." className="resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-normal" />
                {errors.justification && <span className="font-normal text-red-700">{errors.justification.message}</span>}
            </label>
            <div className="sm:col-span-2">
                <button disabled={submitting} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60">
                    {submitting ? 'Submitting…' : 'Submit for approval'}
                </button>
            </div>
        </form>
    );
}

function ApprovalTrail({ approvals }) {
    const currentRound = Math.max(...approvals.map((approval) => approval.submission_round), 1);
    const currentApprovals = approvals.filter((approval) => approval.submission_round === currentRound);

    return (
        <div className="mt-4 grid gap-2 sm:grid-cols-3">
            {stages.map(([stage, label]) => {
                const approval = currentApprovals.find((entry) => entry.stage === stage);
                return (
                    <div key={stage} className="rounded-lg bg-slate-50 px-3 py-2.5">
                        <p className="text-[11px] text-slate-500">{label}</p>
                        <p className={`mt-1 text-xs font-semibold ${approval?.decision === 'rejected' ? 'text-red-700' : approval?.decision === 'approved' ? 'text-emerald-700' : 'text-slate-700'}`}>
                            {approval?.decision === 'approved' ? `Approved${approval.approver ? ` · ${approval.approver}` : ''}` : approval?.decision === 'rejected' ? 'Changes requested' : approval?.decision === 'pending' ? 'Awaiting review' : 'Not started'}
                        </p>
                        {approval?.reason && <p className="mt-1 text-[11px] leading-4 text-red-700">{approval.reason}</p>}
                    </div>
                );
            })}
        </div>
    );
}

export default function PurchaseRequestsPage() {
    const { user, hasPermission } = useAuth();
    const queryClient = useQueryClient();
    const [error, setError] = useState('');
    const [rejectionReasons, setRejectionReasons] = useState({});
    const [resubmittingId, setResubmittingId] = useState(null);
    const canSubmit = hasPermission('purchase_requests.create');

    const requestsQuery = useQuery({
        queryKey: ['purchase-requests'],
        queryFn: async () => (await api.get('/purchase-requests')).data,
    });
    const categoriesQuery = useQuery({
        queryKey: ['asset-categories'],
        queryFn: async () => (await api.get('/asset-categories')).data.data,
    });
    const createRequest = useMutation({
        mutationFn: (values) => api.post('/purchase-requests', values),
        onSuccess: () => {
            setError('');
            queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        },
        onError: (requestError) => setError(requestError.response?.data?.message ?? 'Request could not be submitted.'),
    });
    const decide = useMutation({
        mutationFn: ({ id, decision, reason }) => api.post(`/purchase-requests/${id}/decision`, { decision, reason }),
        onSuccess: () => {
            setError('');
            queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        },
        onError: (requestError) => setError(requestError.response?.data?.message ?? 'Decision could not be recorded.'),
    });
    const resubmit = useMutation({
        mutationFn: ({ id, values }) => api.post(`/purchase-requests/${id}/resubmissions`, values),
        onSuccess: () => {
            setError('');
            setResubmittingId(null);
            queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        },
        onError: (requestError) => setError(requestError.response?.data?.message ?? 'Revision could not be resubmitted.'),
    });

    if (requestsQuery.isLoading || categoriesQuery.isLoading) {
        return <p className="text-sm text-slate-500">Loading procurement workspace…</p>;
    }

    if (requestsQuery.isError || categoriesQuery.isError) {
        return <p role="alert" className="text-sm text-red-700">Procurement data could not be loaded. Refresh to try again.</p>;
    }

    const requests = requestsQuery.data.data ?? [];
    const canReview = ['department', 'finance', 'general'].filter((stage) => hasPermission(`purchase_requests.review.${stage}`));
    return (
        <div className="grid gap-7">
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Asset procurement & intake</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Purchase requests</h1>
                <p className="mt-2 text-sm text-slate-500">Requests move through department, finance, and general management before a purchase order can be placed.</p>
            </div>

            {canSubmit && (
                <RequestForm
                    categories={categoriesQuery.data}
                    submitting={createRequest.isPending}
                    initialValues={{ asset_category_id: '', justification: '', urgency: 'normal', quantity: 1 }}
                    onSubmit={(values) => createRequest.mutateAsync(values)}
                />
            )}
            {error && <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}

            <section className="grid gap-4">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-slate-900">Request history</h2>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{requestsQuery.data.meta?.total ?? requests.length} total</span>
                </div>
                {!requests.length && <div className="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No purchase requests yet. Submit a request to start the approval workflow.</div>}
                {requests.map((request) => {
                    const currentStage = request.status.match(/^pending_(department|finance|general)_approval$/)?.[1];
                    const canApprove = currentStage && canReview.includes(currentStage);
                    const rejectionReason = rejectionReasons[request.id] ?? '';

                    return (
                        <article key={request.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/[0.02]">
                            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-semibold text-slate-900">{request.category?.name} <span className="font-normal text-slate-400">· {request.request_number}</span></h3>
                                        <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-800">{statusLabel[request.status] ?? request.status}</span>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-600">{request.justification}</p>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {request.requester?.name} · {request.department?.name} · Qty {request.quantity} · {request.urgency} urgency
                                    </p>
                                </div>
                                <p className="shrink-0 text-xs text-slate-400">{new Date(request.created_at).toLocaleDateString()}</p>
                            </div>

                            <ApprovalTrail approvals={request.approvals ?? []} />

                            {canApprove && (
                                <div className="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-end">
                                    <label className="grid flex-1 gap-1 text-xs font-medium text-slate-600">
                                        Rejection reason (required to reject)
                                        <input
                                            value={rejectionReason}
                                            onChange={(event) => setRejectionReasons((existing) => ({ ...existing, [request.id]: event.target.value }))}
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                            placeholder="Explain what needs to change"
                                        />
                                    </label>
                                    <button
                                        onClick={() => decide.mutate({ id: request.id, decision: 'approved' })}
                                        disabled={decide.isPending}
                                        className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        onClick={() => decide.mutate({ id: request.id, decision: 'rejected', reason: rejectionReason })}
                                        disabled={decide.isPending || rejectionReason.trim().length < 3}
                                        className="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Request changes
                                    </button>
                                </div>
                            )}

                            {request.status === 'rejected' && request.requester?.id === user.id && (
                                <div className="mt-4">
                                    {resubmittingId === request.id ? (
                                        <RequestForm
                                            categories={categoriesQuery.data}
                                            submitting={resubmit.isPending}
                                            initialValues={{
                                                asset_category_id: request.category.id,
                                                justification: request.justification,
                                                urgency: request.urgency,
                                                quantity: request.quantity,
                                            }}
                                            onSubmit={(values) => resubmit.mutateAsync({ id: request.id, values })}
                                        />
                                    ) : (
                                        <button
                                            onClick={() => setResubmittingId(request.id)}
                                            className="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-50"
                                        >
                                            Edit and resubmit
                                        </button>
                                    )}
                                </div>
                            )}
                        </article>
                    );
                })}
            </section>
        </div>
    );
}
