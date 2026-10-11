export function ProductCardSkeleton() {
    return (
        <article className="store-card store-card-skeleton" aria-hidden="true">
            <div className="skeleton-block skeleton-image" />
            <div className="skeleton-block skeleton-line" />
            <div className="skeleton-block skeleton-line short" />
        </article>
    );
}

export function ProductGridSkeleton({ count = 8 }) {
    return (
        <div className="store-grid" role="status" aria-label="Loading products">
            {Array.from({ length: count }).map((_, index) => <ProductCardSkeleton key={index} />)}
        </div>
    );
}

export function DetailSkeleton() {
    return (
        <div className="store-detail-skeleton" role="status" aria-label="Loading product">
            <div className="skeleton-block skeleton-detail-image" />
            <div>
                <div className="skeleton-block skeleton-line" />
                <div className="skeleton-block skeleton-line short" />
                <div className="skeleton-block skeleton-line" />
            </div>
        </div>
    );
}
