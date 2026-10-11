import { useSearchParams } from 'react-router-dom';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import ProductCard from '../ProductCard';
import { ProductGridSkeleton } from '../Skeletons';
import useApi from '../useApi';

const SORTS = [
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'name_desc', label: 'Name Z–A' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
];

export default function ShopPage() {
    const [params, setParams] = useSearchParams();

    const search = params.get('search') ?? '';
    const category = params.get('category') ?? '';
    const sort = params.get('sort') ?? 'name_asc';
    const page = params.get('page') ?? '1';

    const query = new URLSearchParams({ sort, page });
    if (search) {
        query.set('search', search);
    }
    if (category) {
        query.set('category', category);
    }

    const { data, meta, loading, error, reload } = useApi(`/api/products?${query.toString()}`);

    function updateParam(key, value) {
        const next = new URLSearchParams(params);
        if (value) {
            next.set(key, value);
        } else {
            next.delete(key);
        }
        if (key !== 'page') {
            next.delete('page');
        }
        setParams(next);
    }

    const products = data ?? [];

    return (
        <div className="store-section">
            <div className="store-section-head">
                <div>
                    <p className="store-eyebrow">The shop</p>
                    <h1>{search ? `Results for “${search}”` : 'All goods'}</h1>
                </div>

                <label className="store-sort">
                    <span>Sort</span>
                    <select value={sort} onChange={(event) => updateParam('sort', event.target.value)}>
                        {SORTS.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                </label>
            </div>

            {search && (
                <button className="store-link-button" type="button" onClick={() => updateParam('search', '')}>
                    Clear search
                </button>
            )}

            {loading && <ProductGridSkeleton />}
            {error && <ErrorState message="We could not load the shop." onRetry={reload} />}
            {!loading && !error && products.length === 0 && (
                <EmptyState title="No goods match" description="Try another search or browse a category." actionLabel="Browse categories" actionTo="/categories" />
            )}
            {!loading && !error && products.length > 0 && (
                <div className="store-grid">
                    {products.map((product) => <ProductCard key={product.id} product={product} />)}
                </div>
            )}

            {meta && meta.last_page > 1 && (
                <nav className="store-pagination" aria-label="Pagination">
                    <button
                        type="button"
                        disabled={meta.current_page <= 1}
                        onClick={() => updateParam('page', String(meta.current_page - 1))}
                    >
                        Previous
                    </button>
                    <span>Page {meta.current_page} of {meta.last_page}</span>
                    <button
                        type="button"
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => updateParam('page', String(meta.current_page + 1))}
                    >
                        Next
                    </button>
                </nav>
            )}
        </div>
    );
}
