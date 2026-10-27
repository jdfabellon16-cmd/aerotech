import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import api from '../bootstrap.js';

export default function NotificationsMenu() {
    const [open, setOpen] = useState(false);
    const cache = useQueryClient();
    const { data } = useQuery({
        queryKey: ['notifications'],
        queryFn: async () => (await api.get('/notifications')).data,
        refetchInterval: 60000,
    });

    async function markRead(notification) {
        if (notification.read_at) return;
        await api.post(`/notifications/${notification.id}/read`);
        cache.invalidateQueries({ queryKey: ['notifications'] });
    }

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-label={`Notifications${data?.unread_count ? `, ${data.unread_count} unread` : ''}`}
                className="relative grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
            >
                <span aria-hidden="true">♧</span>
                {data?.unread_count > 0 && <span className="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-600 px-1 text-[10px] font-bold leading-4 text-white">{data.unread_count}</span>}
            </button>
            {open && (
                <div className="absolute right-0 z-20 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                    <p className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Notifications</p>
                    {!data?.data.length && <p className="px-3 py-5 text-center text-sm text-slate-500">You’re all caught up.</p>}
                    <div className="grid max-h-96 gap-1 overflow-y-auto">
                        {data?.data.map((notification) => (
                            <button
                                key={notification.id}
                                type="button"
                                onClick={() => markRead(notification)}
                                className={`rounded-lg px-3 py-2.5 text-left hover:bg-slate-50 ${notification.read_at ? 'opacity-60' : 'bg-blue-50/60'}`}
                            >
                                <span className="block text-sm font-semibold text-slate-800">{notification.data.title}</span>
                                <span className="mt-1 block text-xs leading-5 text-slate-600">{notification.data.message}</span>
                                <span className="mt-1 block text-[10px] text-slate-400">{new Date(notification.created_at).toLocaleString()}</span>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
