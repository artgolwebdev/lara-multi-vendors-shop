import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';

export const fetchUser = createAsyncThunk('auth/fetchUser', async (_, { rejectWithValue }) => {
    try {
        const { data } = await window.axios.get('/api/user');

        return data.data;
    } catch (error) {
        if (error.response?.status === 401) {
            return null;
        }

        return rejectWithValue(error.response?.data ?? { message: 'Could not load your account.' });
    }
});

export const logoutUser = createAsyncThunk('auth/logoutUser', async () => {
    await window.axios.post('/api/logout');

    return null;
});

const initialState = {
    user: null,
    status: 'idle',
    error: null,
};

const authSlice = createSlice({
    name: 'auth',
    initialState,
    reducers: {
        setUser(state, action) {
            state.user = action.payload;
            state.status = 'succeeded';
        },
    },
    extraReducers: (builder) => {
        builder
            .addCase(fetchUser.pending, (state) => {
                state.status = 'loading';
            })
            .addCase(fetchUser.fulfilled, (state, action) => {
                state.status = 'succeeded';
                state.user = action.payload;
            })
            .addCase(fetchUser.rejected, (state, action) => {
                state.status = 'failed';
                state.error = action.payload;
            })
            .addCase(logoutUser.fulfilled, (state) => {
                state.user = null;
                state.status = 'succeeded';
            });
    },
});

export const { setUser } = authSlice.actions;

export const selectUser = (state) => state.auth.user;
export const selectAuthStatus = (state) => state.auth.status;

export default authSlice.reducer;
