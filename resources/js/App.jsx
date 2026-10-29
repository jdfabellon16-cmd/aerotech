import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth/AuthContext.jsx';
import AppLayout from './components/AppLayout.jsx';
import LoginPage from './features/auth/LoginPage.jsx';
import RegisterPage from './features/auth/RegisterPage.jsx';
import AssetsPage from './features/assets/AssetsPage.jsx';
import AssetRequestsPage from './features/assignments/AssetRequestsPage.jsx';
import DashboardPage from './features/dashboard/DashboardPage.jsx';
import LifecyclePage from './features/lifecycle/LifecyclePage.jsx';
import LicensesPage from './features/licenses/LicensesPage.jsx';
import MaintenancePage from './features/maintenance/MaintenancePage.jsx';
import PurchaseOrdersPage from './features/procurement/PurchaseOrdersPage.jsx';
import PurchaseRequestsPage from './features/procurement/PurchaseRequestsPage.jsx';
import TransfersPage from './features/transfers/TransfersPage.jsx';

function Protected({ permission, children }) {
    const { user, loading } = useAuth();

    if (loading) {
        return <div className="grid min-h-screen place-items-center text-sm text-slate-500">Restoring session…</div>;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    if (permission && !user.permissions.includes(permission)) {
        return <Navigate to="/app" replace />;
    }

    return children;
}

export default function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route
                path="/app"
                element={<Protected><AppLayout /></Protected>}
            >
                <Route index element={<DashboardPage />} />
                <Route
                    path="requests"
                    element={<PurchaseRequestsPage />}
                />
                <Route path="purchase-orders" element={<PurchaseOrdersPage />} />
                <Route path="assignments" element={<AssetRequestsPage />} />
                <Route path="transfers" element={<TransfersPage />} />
                <Route path="maintenance" element={<MaintenancePage />} />
                <Route path="licenses" element={<LicensesPage />} />
                <Route path="lifecycle" element={<LifecyclePage />} />
                <Route
                    path="assets"
                    element={<Protected permission="assets.view"><AssetsPage /></Protected>}
                />
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
