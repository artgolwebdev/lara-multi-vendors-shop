import { useEffect, useMemo, useState } from 'react';
import { useDispatch } from 'react-redux';
import { Link, useParams } from 'react-router-dom';
import { addLine } from '../../../store/cartSlice';
import EmptyState from '../EmptyState';
import ErrorState from '../ErrorState';
import QuantityControl from '../QuantityControl';
import { DetailSkeleton } from '../Skeletons';
import useApi from '../useApi';
import VendorSelect from '../VendorSelect';
import { formatMoney, hasDiscount } from '../formatMoney';

export default function ProductPage() {
    const { productId } = useParams();
    const dispatch = useDispatch();
    const { data: product, loading, error, reload } = useApi(`/api/products/${productId}`);

    const offers = useMemo(() => product?.offers ?? [], [product]);
    const [vendorId, setVendorId] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [activeImage, setActiveImage] = useState(0);
    const [justAdded, setJustAdded] = useState(false);

    useEffect(() => {
        if (product) {
            setVendorId(product.default_vendor?.id ?? product.offers?.[0]?.vendor_id ?? null);
            setActiveImage(0);
            setQuantity(1);
            setJustAdded(false);
        }
    }, [product]);

    const offer = useMemo(
        () => offers.find((candidate) => candidate.vendor_id === vendorId) ?? offers[0] ?? null,
        [offers, vendorId],
    );

    if (loading) {
        return <DetailSkeleton />;
    }

    if (error) {
        return <ErrorState message="We could not load this product." onRetry={reload} />;
    }

    if (!product || !offer) {
        return <EmptyState title="Product not found" actionLabel="Back to shop" actionTo="/shop" />;
    }

    const images = product.images ?? [];
    const currentImage = images[activeImage] ?? images[0] ?? null;
    const discounted = hasDiscount(offer);

    function addToCart() {
        dispatch(addLine({ product_id: product.id, vendor_id: vendorId, quantity }));
        setJustAdded(true);
        window.setTimeout(() => setJustAdded(false), 1500);
    }

    return (
        <div className="store-detail">
            <div className="store-detail-gallery">
                <div className="store-detail-image">
                    {currentImage
                        ? <img src={currentImage.url} alt={product.name} />
                        : <span className="store-card-placeholder" aria-hidden="true">{product.name.charAt(0)}</span>}
                </div>
                {images.length > 1 && (
                    <div className="store-thumbs">
                        {images.map((image, index) => (
                            <button
                                key={image.id}
                                type="button"
                                className={index === activeImage ? 'is-active' : ''}
                                onClick={() => setActiveImage(index)}
                                aria-label={`View image ${index + 1}`}
                            >
                                <img src={image.url} alt="" />
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div className="store-detail-info">
                <p className="store-eyebrow">{product.category?.name ?? 'Goods'}</p>
                <h1>{product.name}</h1>
                <p className="store-card-sku">SKU {product.sku}</p>

                <div className="store-detail-price">
                    <span className="store-price">{formatMoney(offer.discounted_price)}</span>
                    {discounted && <span className="store-price-original">{formatMoney(offer.price)}</span>}
                </div>

                {offer.applied_rule && <p className="store-rule-badge">{offer.applied_rule.name}</p>}
                {offer.discount_hint && <p className="store-hint">{offer.discount_hint.label}</p>}

                {product.description && <p className="store-detail-description">{product.description}</p>}

                <VendorSelect
                    id="detail-vendor"
                    offers={offers}
                    value={vendorId}
                    onChange={setVendorId}
                />

                <div className="store-detail-buy">
                    <QuantityControl value={quantity} max={product.stock} onChange={setQuantity} />
                    <button
                        className="store-button"
                        type="button"
                        onClick={addToCart}
                        disabled={!product.in_stock}
                    >
                        {justAdded ? 'Added to cart' : 'Add to cart'}
                    </button>
                </div>

                <p className={`store-stock ${product.in_stock ? 'is-in' : 'is-out'}`}>
                    {product.in_stock ? `${product.stock} in stock` : 'Out of stock'}
                </p>

                <Link className="store-link-button" to="/cart">View cart</Link>
            </div>
        </div>
    );
}
