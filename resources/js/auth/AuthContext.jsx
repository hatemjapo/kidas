import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import api, { csrf } from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;

        api.get('/me')
            .then((response) => {
                if (active) {
                    setUser(response.data.data ?? response.data);
                }
            })
            .catch(() => {
                if (active) {
                    setUser(null);
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const login = useCallback(async (credentials) => {
        await csrf();

        const response = await api.post('/login', credentials);
        const signedIn = response.data.data ?? response.data;

        setUser(signedIn);

        return signedIn;
    }, []);

    const logout = useCallback(async () => {
        await api.post('/logout');

        setUser(null);
    }, []);

    return (
        <AuthContext.Provider
            value={{ user, role: user?.role ?? null, loading, setUser, login, logout }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (context === null) {
        throw new Error('useAuth must be used inside an AuthProvider');
    }

    return context;
}
