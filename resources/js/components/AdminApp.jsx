import { useEffect, useState } from 'react';
import AdminDashboard from './admin/AdminDashboard';
import AdminLayout from './admin/AdminLayout';
import CategoryPages from './admin/CategoryPages';
import CustomerPages from './admin/CustomerPages';
import DiscountRulePages from './admin/DiscountRulePages';
import ProductPages from './admin/ProductPages';
import VendorPages from './admin/VendorPages';

export default function AdminApp() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        window.axios.get('/api/user')
            .then(({ data }) => setUser(data.data))
            .catch(() => window.location.assign('/login'))
            .finally(() => setLoading(false));
    }, []);

    if (loading || !user) {
        return <main className="admin-loading" role="status">Loading admin workspace...</main>;
    }

    const path = window.location.pathname;
    let page;

    if (path.startsWith('/admin/categories')) {
        page = <CategoryPages path={path} />;
    } else if (path.startsWith('/admin/discount-rules')) {
        page = <DiscountRulePages path={path} />;
    } else if (path.startsWith('/admin/vendors')) {
        page = <VendorPages path={path} />;
    } else if (path.startsWith('/admin/products')) {
        page = <ProductPages path={path} />;
    } else if (path.startsWith('/admin/customers')) {
        page = <CustomerPages path={path} />;
    } else {
        page = <AdminDashboard />;
    }

    return <AdminLayout user={user}>{page}</AdminLayout>;
}