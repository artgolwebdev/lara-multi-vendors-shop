import { Provider } from 'react-redux';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { store } from '../../store/store';
import StoreLayout from './StoreLayout';
import CartPage from './pages/CartPage';
import CategoriesPage from './pages/CategoriesPage';
import CategoryPage from './pages/CategoryPage';
import HomePage from './pages/HomePage';
import ProductPage from './pages/ProductPage';
import ShopPage from './pages/ShopPage';

export default function StoreApp() {
    return (
        <Provider store={store}>
            <BrowserRouter>
                <Routes>
                    <Route element={<StoreLayout />}>
                        <Route index element={<HomePage />} />
                        <Route path="shop" element={<ShopPage />} />
                        <Route path="categories" element={<CategoriesPage />} />
                        <Route path="categories/:categoryId" element={<CategoryPage />} />
                        <Route path="products/:productId" element={<ProductPage />} />
                        <Route path="cart" element={<CartPage />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </Provider>
    );
}
