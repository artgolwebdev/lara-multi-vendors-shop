import { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { selectUser } from '../../store/authSlice';
import { fetchQuote, selectCartLines } from '../../store/cartSlice';
import useDebouncedValue from './useDebouncedValue';

/**
 * Re-prices the cart through the backend whenever the lines or the signed-in
 * customer change. All mutations funnel through here, debounced so rapid
 * quantity edits make a single request. Stale responses are discarded inside
 * the thunk. Re-running on the customer change is what makes a first-order
 * loyalty discount appear immediately after login or registration.
 */
export default function QuoteSync() {
    const dispatch = useDispatch();
    const lines = useSelector(selectCartLines);
    const user = useSelector(selectUser);
    const debouncedLines = useDebouncedValue(lines, 250);

    useEffect(() => {
        dispatch(fetchQuote());
    }, [debouncedLines, user?.id, dispatch]);

    return null;
}
