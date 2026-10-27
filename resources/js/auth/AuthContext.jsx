import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import api from '../bootstrap.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(Boolean(window.sessionStorage.getItem('aerotech_access_token')));
    const [error, setError] = useState('');

    useEffect(() => {
        if (!window.sessionStorage.getItem('aerotech_access_token')) {
            return;
        }

        api.get('/auth/me')
            .then(({ data }) => setUser(data.user))
            .catch((requestError) => {
                if (requestError.response?.status === 401) {
                    window.sessionStorage.removeItem('aerotech_access_token');
                    setUser(null);
                } else {
                    setError('Unable to restore your session. Check your connection and retry.');
                }
            })
            .finally(() => setLoading(false));
    }, []);

    async function login(credentials) {
        setError('');
        const { data } = await api.post('/auth/token', {
            ...credentials,
            device_name: 'AeroTech web app',
        });
        window.sessionStorage.setItem('aerotech_access_token', data.token);
        setUser(data.user);
    }

    async function logout() {
        try {
            await api.delete('/auth/token');
        } finally {
            window.sessionStorage.removeItem('aerotech_access_token');
            setUser(null);
        }
    }

    const value = useMemo(() => ({
        user,
        loading,
        error,
        login,
        logout,
        hasPermission: (permission) => user?.permissions.includes(permission) ?? false,
    }), [user, loading, error]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (context === null) {
        throw new Error('useAuth must be used inside AuthProvider.');
    }

    return context;
}
