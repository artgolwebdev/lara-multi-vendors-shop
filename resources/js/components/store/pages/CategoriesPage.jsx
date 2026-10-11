import { Link } from 'react-router-dom';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import useApi from '../useApi';

export default function CategoriesPage() {
    const { data, loading, error, reload } = useApi('/api/categories');

    return (
        <div className="store-section">
            <div className="store-section-head">
                <div>
                    <p className="store-eyebrow">Browse</p>
                    <h1>Categories</h1>
                </div>
            </div>

            {loading && <p className="store-state-text" role="status">Loading categories...</p>}
            {error && <ErrorState message="We could not load categories." onRetry={reload} />}
            {!loading && !error && (data?.length ?? 0) === 0 && (
                <EmptyState title="No categories yet" actionLabel="Browse shop" actionTo="/shop" />
            )}
            {!loading && !error && (data?.length ?? 0) > 0 && (
                <div className="store-category-grid">
                    {data.map((category) => (
                        <Link className="store-category-card" key={category.id} to={`/categories/${category.id}`}>
                            <h2>{category.name}</h2>
                            <span>{category.products_count} {category.products_count === 1 ? 'good' : 'goods'}</span>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
