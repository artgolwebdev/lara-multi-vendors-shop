import { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchQuote, selectCartLines } from '../../store/cartSlice';
import useDebouncedValue from './useDebouncedValue';

/**
 * Re-prices the cart through the backend whenever the lines change. All
 * mutations funnel through here, debounced so rapid quantity edits make a
 * single request. Stale responses are discarded inside the thunk.
 */
export default function QuoteSync() {
    const dispatch = useDispatch();
    const lines = useSelector(selectCartLines);
    const debouncedLines = useDebouncedValue(lines, 250);

    useEffect(() => {
        dispatch(fetchQuote());
    }, [debouncedLines, dispatch]);

    return null;
}
