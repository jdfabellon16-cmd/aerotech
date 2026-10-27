import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext.jsx';
import App from './App.jsx';
import './bootstrap.js';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

createRoot(document.getElementById('app')).render(
    React.createElement(
        React.StrictMode,
        null,
        React.createElement(
            QueryClientProvider,
            { client: queryClient },
            React.createElement(
                AuthProvider,
                null,
                React.createElement(BrowserRouter, null, React.createElement(App)),
            ),
        ),
    ),
);
