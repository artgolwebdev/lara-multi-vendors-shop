import { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { fetchUser, logoutUser, selectUser } from '../../store/authSlice';
import { selectCartCount } from '../../store/cartSlice';
import QuoteSync from './QuoteSync';

function CartIndicator() {
    const count = useSelector(selectCartCount);

    return (
        <Link className="store-cart-link" to="/cart">
            Cart
            <span className="store-cart-count" aria-label={`${count} items in cart`}>{count}</span>
        </Link>
    );
}

function SearchBox() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [term, setTerm] = useState(params.get('search') ?? '');

    useEffect(() => {
        setTerm(params.get('search') ?? '');
    }, [params]);

    function submit(event) {
        event.preventDefault();
        const trimmed = term.trim();
        navigate(trimmed ? `/shop?search=${encodeURIComponent(trimmed)}` : '/shop');
    }

    return (
        <form className="store-search" role="search" onSubmit={submit}>
            <input
                type="search"
                name="search"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder="Search goods"
                aria-label="Search goods"
            />
            <button type="submit" aria-label="Search">Search</button>
        </form>
    );
}

function AccountMenu() {
    const dispatch = useDispatch();
    const user = useSelector(selectUser);
    const navigate = useNavigate();
    const location = useLocation();

    async function logout() {
        await dispatch(logoutUser());
        navigate('/');
    }

    if (!user) {
        const redirect = encodeURIComponent(`${location.pathname}${location.search}`);

        return (
            <div className="store-header-account">
                <a className="store-account-link" href={`/account?redirect=${redirect}`}>Login</a>
                <a className="store-account-link" href={`/account?mode=register&redirect=${redirect}`}>Register</a>
            </div>
        );
    }

    return (
        <div className="store-header-account">
            <a className="store-account-link" href="/account">Account</a>
            {user.is_admin && <a className="store-account-link" href="/admin">Admin</a>}
            <button className="store-account-link store-account-button" type="button" onClick={logout}>Logout</button>
        </div>
    );
}

export default function StoreLayout() {
    const dispatch = useDispatch();

    useEffect(() => {
        dispatch(fetchUser());
    }, [dispatch]);

    return (
        <div className="store-shell">
            <QuoteSync />
            <header className="store-header">
                <Link className="wordmark" to="/">goodgoods<span>.</span></Link>

                <nav className="store-nav" aria-label="Primary">
                    <NavLink to="/" end>Home</NavLink>
                    <NavLink to="/shop">Shop</NavLink>
                    <NavLink to="/categories">Categories</NavLink>
                </nav>

                <div className="store-header-actions">
                    <SearchBox />
                    <CartIndicator />
                    <AccountMenu />
                </div>
            </header>

            <main className="store-main">
                <Outlet />
            </main>

            <footer className="store-footer">
                <span className="wordmark">goodgoods<span>.</span></span>
                <span>Independent by nature. &copy; GOODGOODS 2026</span>
            </footer>
        </div>
    );
}
