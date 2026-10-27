import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useAuth } from '../../auth/AuthContext.jsx';
import api from '../../bootstrap.js';

const orderSchema = z.object({
    purchase_request_id: z.coerce.number().int().positive(),
    supplier_id: z.coerce.number().int().positive(),
    purchase_order_number: z.string().trim().min(2).max(100),
    ordered_at: z.string().min(1),
    expected_at: z.string().optional(),
    model: z.string().trim().min(2).max(255),
    quantity: z.coerce.number().int().min(1).max(100),
    unit_cost: z.coerce.number().min(0),
});

export default function PurchaseOrdersPage() {
    const { hasPermission } = useAuth();
    const cache = useQueryClient();
    const orders = useQuery({ queryKey: ['purchase-orders'], queryFn: async () => (await api.get('/purchase-orders')).data.data });
    const requests = useQuery({
        queryKey: ['purchase-requests', 'approved'],
        queryFn: async () => (await api.get('/purchase-requests')).data.data.filter((request) => request.status === 'approved'),
        enabled: hasPermission('purchase_orders.manage'),
    });
    const suppliers = useQuery({
        queryKey: ['directory', 'suppliers'],
        queryFn: async () => (await api.get('/directory/suppliers')).data.data,
        enabled: hasPermission('purchase_orders.manage'),
    });
    const locations = useQuery({ queryKey: ['directory', 'locations'], queryFn: async () => (await api.get('/directory/locations')).data.data });
    const mutation = useMutation({
        mutationFn: (values) => api.post('/purchase-orders', values),
        onSuccess: () => {
            cache.invalidateQueries({ queryKey: ['purchase-orders'] });
            cache.invalidateQueries({ queryKey: ['purchase-requests'] });
        },
    });
    const { register, handleSubmit, watch, reset, formState: { errors, isSubmitting } } = useForm({
        resolver: zodResolver(orderSchema),
        defaultValues: { purchase_request_id: '', supplier_id: '', purchase_order_number: '', ordered_at: new Date().toISOString().slice(0, 10), expected_at: '', model: '', quantity: 1, unit_cost: 0 },
    });
    const selectedRequest = requests.data?.find((request) => String(request.id) === String(watch('purchase_request_id')));

    if (orders.isLoading || locations.isLoading || (hasPermission('purchase_orders.manage') && (requests.isLoading || suppliers.isLoading))) return <p className="text-sm text-slate-500">Loading purchasing workspace…</p>;
    if (orders.isError || locations.isError || (hasPermission('purchase_orders.manage') && (requests.isError || suppliers.isError))) return <p role="alert" className="text-sm text-red-700">Purchase order data could not be loaded.</p>;

    async function receiveUnit(event, order, item) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        await api.post(`/purchase-orders/${order.id}/receiving`, {
            units: [{
                purchase_order_item_id: item.id,
                asset_tag: form.get('asset_tag'),
                serial_number: form.get('serial_number') || null,
                model: form.get('model') || item.model,
                purchase_date: form.get('purchase_date'),
                warranty_expires_at: form.get('warranty_expires_at') || null,
                location_id: Number(form.get('location_id')),
                notes: form.get('notes') || null,
            }],
        });
        cache.invalidateQueries({ queryKey: ['purchase-orders'] });
        cache.invalidateQueries({ queryKey: ['assets'] });
    }

    return (
        <div className="grid gap-7">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Procurement &amp; warehouse intake</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">Purchase orders</h1>
                <p className="mt-2 text-sm text-slate-500">Purchasing tracks suppliers and approved orders. Warehouse receipt creates a unique asset record from each verified unit.</p>
            </header>

            {hasPermission('purchase_orders.manage') && (
                <form onSubmit={handleSubmit(async (values) => {
                    if (!selectedRequest) return;
                    await mutation.mutateAsync({
                        purchase_order_number: values.purchase_order_number,
                        purchase_request_id: Number(values.purchase_request_id),
                        supplier_id: Number(values.supplier_id),
                        ordered_at: values.ordered_at,
                        expected_at: values.expected_at || null,
                        items: [{
                            asset_category_id: selectedRequest.category.id,
                            model: values.model,
                            quantity: values.quantity,
                            unit_cost: values.unit_cost,
                        }],
                    });
                    reset();
                })} className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                    <h2 className="font-semibold text-slate-900 sm:col-span-2">Create PO from an approved request</h2>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Approved request<select {...register('purchase_request_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal"><option value="">Select approved request</option>{requests.data.map((request) => <option key={request.id} value={request.id}>{request.request_number} · {request.category.name} · Qty {request.quantity}</option>)}</select>{errors.purchase_request_id && <span className="font-normal text-red-700">{errors.purchase_request_id.message}</span>}</label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Supplier<select {...register('supplier_id')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal"><option value="">Select supplier</option>{suppliers.data.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}</select></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">PO number<input {...register('purchase_order_number')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Model<input {...register('model')} className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Quantity<input {...register('quantity')} type="number" min="1" className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Unit cost<input {...register('unit_cost')} type="number" min="0" step="0.01" className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Order date<input {...register('ordered_at')} type="date" className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <label className="grid gap-1 text-xs font-semibold text-slate-700">Expected delivery<input {...register('expected_at')} type="date" className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal" /></label>
                    <button disabled={isSubmitting || mutation.isPending || !selectedRequest} className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white sm:col-span-2">Create purchase order</button>
                </form>
            )}

            <section className="grid gap-3">
                {orders.data.map((order) => (
                    <article key={order.id} className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex flex-wrap justify-between gap-2"><div><h2 className="font-semibold text-slate-900">{order.purchase_order_number}</h2><p className="mt-1 text-xs text-slate-500">{order.supplier?.name} · {order.purchase_request?.request_number}</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs">{order.status.replaceAll('_', ' ')}</span></div>
                        <div className="mt-4 grid gap-3">
                            {order.items.map((item) => (
                                <div key={item.id} className="rounded-lg bg-slate-50 p-3">
                                    <p className="text-sm font-medium text-slate-800">{item.model} · {item.received_quantity}/{item.quantity} received</p>
                                    {hasPermission('receiving.manage') && item.received_quantity < item.quantity && (
                                        <form onSubmit={(event) => receiveUnit(event, order, item)} className="mt-3 grid gap-2 sm:grid-cols-4">
                                            <input name="asset_tag" required placeholder="Asset tag" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                            <input name="serial_number" placeholder="Serial number" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                            <input name="purchase_date" type="date" required className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                            <input name="warranty_expires_at" type="date" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                            <select name="location_id" required className="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Storage location</option>{locations.data.map((location) => <option key={location.id} value={location.id}>{location.name}</option>)}</select>
                                            <input name="notes" placeholder="Receiving notes" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                            <button className="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">Verify delivery &amp; register asset</button>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </div>
                    </article>
                ))}
                {!orders.data.length && <p className="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No purchase orders found.</p>}
            </section>
        </div>
    );
}
