import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';

const STORAGE_KEY = 'goodgoods.cart.v1';

let quoteAbortController = null;
let latestQuoteRequest = 0;

export function loadPersistedLines() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.filter(isValidLine) : [];
    } catch {
        return [];
    }
}

export function persistLines(lines) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    } catch {
        // Storage can be unavailable (private mode); the cart still works in memory.
    }
}

function isValidLine(line) {
    return line
        && Number.isInteger(line.product_id)
        && Number.isInteger(line.quantity)
        && line.quantity > 0
        && (line.vendor_id === null || line.vendor_id === undefined || Number.isInteger(line.vendor_id));
}

function lineKey(line) {
    return `${line.product_id}:${line.vendor_id ?? 'lowest'}`;
}

const initialState = {
    lines: [],
    quote: null,
    quoteStatus: 'idle',
    quoteError: null,
    fieldErrors: {},
};

export const fetchQuote = createAsyncThunk('cart/fetchQuote', async (_, { getState, rejectWithValue }) => {
    const { lines } = getState().cart;

    if (lines.length === 0) {
        return null;
    }

    if (quoteAbortController) {
        quoteAbortController.abort();
    }
    quoteAbortController = new AbortController();
    const requestId = ++latestQuoteRequest;

    try {
        const { data } = await window.axios.post('/api/cart/quote', {
            lines: lines.map((line) => ({
                product_id: line.product_id,
                quantity: line.quantity,
                ...(line.vendor_id ? { vendor_id: line.vendor_id } : {}),
            })),
        }, { signal: quoteAbortController.signal });

        if (requestId !== latestQuoteRequest) {
            return rejectWithValue({ stale: true });
        }

        return data.data;
    } catch (error) {
        if (requestId !== latestQuoteRequest || error.code === 'ERR_CANCELED' || error.name === 'CanceledError') {
            return rejectWithValue({ stale: true });
        }

        return rejectWithValue(error.response?.data ?? { message: 'We could not price your cart. Please retry.' });
    }
});

const cartSlice = createSlice({
    name: 'cart',
    initialState,
    reducers: {
        addLine(state, action) {
            const incoming = action.payload;
            const existing = state.lines.find((line) => lineKey(line) === lineKey(incoming));

            if (existing) {
                existing.quantity += incoming.quantity;
            } else {
                state.lines.push({
                    product_id: incoming.product_id,
                    vendor_id: incoming.vendor_id ?? null,
                    quantity: incoming.quantity,
                });
            }
        },
        removeLine(state, action) {
            state.lines.splice(action.payload, 1);
        },
        setQuantity(state, action) {
            const { index, quantity } = action.payload;
            const line = state.lines[index];

            if (line) {
                line.quantity = Math.max(1, Math.trunc(quantity) || 1);
            }
        },
        setVendor(state, action) {
            const { index, vendor_id: vendorId } = action.payload;
            const line = state.lines[index];

            if (line) {
                line.vendor_id = vendorId ?? null;
            }
        },
        clearCart(state) {
            state.lines = [];
            state.quote = null;
            state.quoteStatus = 'idle';
            state.quoteError = null;
            state.fieldErrors = {};
        },
    },
    extraReducers: (builder) => {
        builder
            .addCase(fetchQuote.pending, (state) => {
                state.quoteStatus = 'loading';
                state.quoteError = null;
                state.fieldErrors = {};
            })
            .addCase(fetchQuote.fulfilled, (state, action) => {
                state.quoteStatus = 'succeeded';
                state.quote = action.payload;
                state.fieldErrors = {};
            })
            .addCase(fetchQuote.rejected, (state, action) => {
                if (action.payload?.stale) {
                    return;
                }

                state.quoteStatus = 'failed';
                state.quoteError = action.payload;
                state.fieldErrors = action.payload?.errors ?? {};
            });
    },
});

export const { addLine, removeLine, setQuantity, setVendor, clearCart } = cartSlice.actions;

export const selectCartLines = (state) => state.cart.lines;
export const selectQuote = (state) => state.cart.quote;
export const selectQuoteStatus = (state) => state.cart.quoteStatus;
export const selectQuoteError = (state) => state.cart.quoteError;
export const selectFieldErrors = (state) => state.cart.fieldErrors;
export const selectCartCount = (state) => state.cart.lines.reduce((total, line) => total + line.quantity, 0);

export default cartSlice.reducer;
