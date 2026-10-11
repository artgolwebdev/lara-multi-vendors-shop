import { configureStore } from '@reduxjs/toolkit';
import authReducer from './authSlice';
import cartReducer, { loadPersistedLines, persistLines } from './cartSlice';

const preloadedLines = loadPersistedLines();

export const store = configureStore({
    reducer: {
        auth: authReducer,
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
