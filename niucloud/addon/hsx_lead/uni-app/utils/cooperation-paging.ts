/** A footer retry must not append page two until the current page one succeeded. */
export function createCooperationPagingGuard() {
    let firstPageReady = false
    return {
        reset() {
            firstPageReady = false
        },
        begin(page: number) {
            if (page > 1 && !firstPageReady) return false
            if (page === 1) firstPageReady = false
            return true
        },
        succeed(page: number) {
            if (page === 1) firstPageReady = true
        }
    }
}
