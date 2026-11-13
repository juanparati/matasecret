import type { HttpResponse } from '@inertiajs/core';

function failedResponse(error: unknown): HttpResponse | null {
    if (typeof error !== 'object' || error === null || !('response' in error)) {
        return null;
    }

    const response = (error as { response?: HttpResponse }).response;

    return typeof response?.status === 'number' ? response : null;
}

/**
 * Extract a user-facing message from a failed standalone HTTP request.
 */
export function httpErrorMessage(error: unknown, fallback: string): string {
    const response = failedResponse(error);

    if (response === null) {
        return fallback;
    }

    if (response.status === 429) {
        return 'Too many attempts. Please wait a moment and try again.';
    }

    try {
        const data = JSON.parse(response.data) as { message?: unknown };

        return typeof data.message === 'string' ? data.message : fallback;
    } catch {
        return fallback;
    }
}

/**
 * Whether the error is a validation failure already exposed through `errors`.
 */
export function isValidationError(error: unknown): boolean {
    return failedResponse(error)?.status === 422;
}
