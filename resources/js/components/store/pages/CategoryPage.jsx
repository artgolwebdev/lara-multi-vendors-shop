import { useSearchParams } from 'react-router-dom';
import { useParams } from 'react-router-dom';
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

export default function CategoryPage() {
    const { categoryId } = useParams();
    const [params, setParams] = useSearchParams();
    const sort = params.get('sort') ?? 'name_asc';

    const category = useApi(`/api/categories/${categoryId}`);
    const products = useApi(`/api/products?category=${categoryId}&sort=${sort}&per_page=24`);

    function changeSort(value) {
        const next = new URLSearchParams(params);
        next.set('sort', value);
        setParams(next);
    }

    const name = category.data?.name ?? 'Category';

    return (
        <div className="store-section">
            <div className="store-section-head">
                <div>
                    <p className="store-eyebrow">Category</p>
                    <h1>{name}</h1>
                </div>

                <label className="store-sort">
                    <span>Sort</span>
                    <select value={sort} onChange={(event) => changeSort(event.target.value)}>
                        {SORTS.map((option) => (
                            <option key={option.value} value={option.value}>{option.label}</option>
                        ))}
                    </select>
                </label>
            </div>

            {products.loading && <ProductGridSkeleton />}
            {products.error && <ErrorState message="We could not load this category." onRetry={products.reload} />}
            {!products.loading && !products.error && (products.data?.length ?? 0) === 0 && (
                <EmptyState title="Nothing in this category yet" actionLabel="Browse the shop" actionTo="/shop" />
            )}
            {!products.loading && !products.error && (products.data?.length ?? 0) > 0 && (
                <div className="store-grid">
                    {products.data.map((product) => <ProductCard key={product.id} product={product} />)}
                </div>
            )}
        </div>
    );
}
