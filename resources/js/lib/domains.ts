/**
 * List email domains the way messages mention them, such as
 * "@example.com or @example.org".
 */
export function describeDomains(domains: string[]): string {
    const mentions = domains.map((domain) => `@${domain}`);

    if (mentions.length <= 1) {
        return mentions.join('');
    }

    return `${mentions.slice(0, -1).join(', ')} or ${mentions.at(-1)}`;
}

/**
 * Whether the email address belongs to one of the domains.
 */
export function belongsToDomains(email: string, domains: string[]): boolean {
    return domains.some((domain) => email.endsWith(`@${domain}`));
}
