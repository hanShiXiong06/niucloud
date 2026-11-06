/** For trusted local ES image imports only, never user-uploaded image paths. */
export function bundledImage(source: unknown): string {
    const path = typeof source === 'string' ? source : ''
    // uni-image adds the current tenant base to root-relative H5 paths. The
    // bundler already supplied its complete public path, so preserve it by
    // making that path absolute at the current origin, not the image CDN.
    // #ifdef H5
    if (path.startsWith('/') && !path.startsWith('//') && typeof window !== 'undefined') {
        const origin = window.location?.origin
        if (typeof origin === 'string' && /^https?:\/\/[^/?#\s]+$/i.test(origin)) return origin + path
    }
    // #endif
    return path
}
