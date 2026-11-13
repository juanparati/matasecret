const storageKey = 'ephesecret:pending-link';

type PendingLink = {
    path: string;
    hash: string;
};

/**
 * Remember the key fragment of a secret link while the user signs in, since
 * the fragment is lost during the OAuth redirects.
 */
export function rememberPendingLink(path: string, hash: string): void {
    try {
        sessionStorage.setItem(
            storageKey,
            JSON.stringify({ path, hash } satisfies PendingLink),
        );
    } catch {
        // Storage may be unavailable (private mode); the user can reopen the link.
    }
}

/**
 * Take the remembered fragment for the given path, removing it from storage.
 */
export function takePendingLinkHash(path: string): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey);

        if (stored === null) {
            return null;
        }

        const pending = JSON.parse(stored) as PendingLink;

        if (pending.path !== path) {
            return null;
        }

        sessionStorage.removeItem(storageKey);

        return pending.hash;
    } catch {
        return null;
    }
}
