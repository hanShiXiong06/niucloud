import { nextTick, onBeforeUnmount, ref, type Ref } from 'vue'

export interface AutoFollowScrollOptions {
    /** 距离底部多少像素内仍视为正在跟随。 */
    threshold?: number
    /** 合并流式更新，避免每个字符都触发布局和滚动。 */
    interval?: number
}

export function useAutoFollowScroll(target: Ref<HTMLElement | null>, options: AutoFollowScrollOptions = {}) {
    const threshold = Math.max(0, options.threshold ?? 72)
    const interval = Math.max(16, options.interval ?? 80)
    const isFollowing = ref(true)
    let timer: number | null = null
    let frame: number | null = null
    let forcePending = false
    let programmatic = false

    const distanceToBottom = () => {
        const element = target.value
        return element ? Math.max(0, element.scrollHeight - element.clientHeight - element.scrollTop) : 0
    }

    function handleScroll() {
        if (programmatic) return
        isFollowing.value = distanceToBottom() <= threshold
    }

    async function scrollToBottom(force = false) {
        await nextTick()
        const element = target.value
        if (!element || (!force && !isFollowing.value)) return
        programmatic = true
        element.scrollTop = Math.max(0, element.scrollHeight - element.clientHeight)
        window.requestAnimationFrame(() => {
            programmatic = false
            isFollowing.value = true
        })
    }

    function scheduleScrollToBottom(force = false) {
        if (!force && !isFollowing.value) return
        forcePending = forcePending || force
        if (timer !== null || frame !== null) return
        timer = window.setTimeout(() => {
            timer = null
            frame = window.requestAnimationFrame(() => {
                frame = null
                const shouldForce = forcePending
                forcePending = false
                void scrollToBottom(shouldForce)
            })
        }, interval)
    }

    function resumeFollowing() {
        isFollowing.value = true
        scheduleScrollToBottom(true)
    }

    function cancelScheduledScroll() {
        if (timer !== null) window.clearTimeout(timer)
        if (frame !== null) window.cancelAnimationFrame(frame)
        timer = null
        frame = null
        forcePending = false
    }

    onBeforeUnmount(cancelScheduledScroll)

    return {
        isFollowing,
        handleScroll,
        scrollToBottom,
        scheduleScrollToBottom,
        resumeFollowing,
        cancelScheduledScroll
    }
}
