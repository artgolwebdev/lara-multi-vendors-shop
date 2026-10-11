import { useCallback, useEffect, useState } from 'react';

export default function useApi(url) {
    const [payload, setPayload] = useState(null);
    const [loading, setLoading] = useState(Boolean(url));
    const [error, setError] = useState(null);
    const [nonce, setNonce] = useState(0);

    useEffect(() => {
        if (!url) {
            return undefined;
        }

        let active = true;
        setLoading(true);
        setError(null);

        window.axios.get(url)
            .then(({ data }) => {
                if (active) {
                    setPayload(data);
                }
            })
            .catch((requestError) => {
                if (active) {
                    setError(requestError);
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [url, nonce]);

    const reload = useCallback(() => setNonce((value) => value + 1), []);

    return {
        data: payload?.data ?? null,
        meta: payload?.meta ?? null,
        links: payload?.links ?? null,
        loading,
        error,
        reload,
    };
}
