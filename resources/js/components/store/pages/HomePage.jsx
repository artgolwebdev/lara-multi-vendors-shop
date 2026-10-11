import { Link } from 'react-router-dom';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import ProductCard from '../ProductCard';
import { ProductGridSkeleton } from '../Skeletons';
import useApi from '../useApi';

export default function HomePage() {
    const featured = useApi('/api/products?sort=price_asc&per_page=4');
    const categories = useApi('/api/categories');

    return (
        <div className="store-home">
            <section className="store-hero">
                <p className="store-eyebrow">A marketplace for makers</p>
                <h1>Small shops.<br />Good things.</h1>
                <p className="store-hero-text">
                    Thoughtful goods from independent sellers, priced fairly and ready to ship.
                </p>
                <Link className="store-button" to="/shop">Browse the shop</Link>
            </section>

            <section className="store-section">
                <div className="store-section-head">
                    <h2>Featured goods</h2>
                    <Link className="store-link-button" to="/shop">See everything</Link>
                </div>

                {featured.loading && <ProductGridSkeleton count={4} />}
                {featured.error && <ErrorState message="We could not load featured goods." onRetry={featured.reload} />}
                {!featured.loading && !featured.error && (featured.data?.length ?? 0) === 0 && (
                    <EmptyState title="No goods yet" description="Check back soon." actionLabel="Browse shop" actionTo="/shop" />
                )}
                {!featured.loading && !featured.error && (featured.data?.length ?? 0) > 0 && (
                    <div className="store-grid">
                        {featured.data.map((product) => <ProductCard key={product.id} product={product} />)}
                    </div>
                )}
            </section>

            <section className="store-section">
                <div className="store-section-head">
                    <h2>Shop by category</h2>
                    <Link className="store-link-button" to="/categories">All categories</Link>
                </div>

                {categories.loading && <p className="store-state-text" role="status">Loading categories...</p>}
                {categories.error && <ErrorState message="We could not load categories." onRetry={categories.reload} />}
                {!categories.loading && !categories.error && (
                    <div className="store-chip-row">
                        {(categories.data ?? []).map((category) => (
                            <Link className="store-chip" key={category.id} to={`/categories/${category.id}`}>
                                {category.name}
                                <span>{category.products_count}</span>
                            </Link>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
