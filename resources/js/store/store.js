import { configureStore } from '@reduxjs/toolkit';
import cartReducer, { loadPersistedLines, persistLines } from './cartSlice';

const preloadedLines = loadPersistedLines();

export const store = configureStore({
    reducer: {
        cart: cartReducer,
    },
    preloadedState: {
        cart: {
            lines: preloadedLines,
            quote: null,
            quoteStatus: 'idle',
            quoteError: null,
            fieldErrors: {},
        },
    },
});

store.subscribe(() => {
    persistLines(store.getState().cart.lines);
});
