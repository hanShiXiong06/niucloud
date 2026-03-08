import { ref, Ref } from 'vue'

/**
 * 渐进式图片加载 Hook
 * 优先加载缩略图，然后加载完整图片
 * 仅限 phone_shop 插件使用
 */
export function useProgressiveImage() {
    /**
     * 获取缩略图 URL
     * @param url 原始图片 URL
     * @returns 缩略图 URL
     */
    const getThumbUrl = (url: string): string => {
        if (!url) return ''

        // 如果已经是缩略图，直接返回
        if (url.includes('_thumb_small')) {
            return url
        }

        // 提取文件扩展名
        const lastDotIndex = url.lastIndexOf('.')
        if (lastDotIndex === -1) return url

        const extension = url.substring(lastDotIndex)
        const baseUrl = url.substring(0, lastDotIndex)

        return `${baseUrl}_thumb_small${extension}`
    }

    /**
     * 预加载图片
     * @param url 图片 URL
     * @returns Promise<boolean> 加载成功返回 true
     */
    const preloadImage = (url: string): Promise<boolean> => {
        return new Promise((resolve) => {
            uni.getImageInfo({
                src: url,
                success: () => resolve(true),
                fail: () => resolve(false)
            })
        })
    }

    /**
     * 渐进式加载图片
     * @param originalUrl 原始图片 URL
     * @param imageRef 图片 ref（用于更新显示的 URL）
     */
    const loadProgressively = async (
        originalUrl: string,
        imageRef: Ref<string>
    ): Promise<void> => {
        if (!originalUrl) return

        const thumbUrl = getThumbUrl(originalUrl)

        // 先显示缩略图
        imageRef.value = thumbUrl

        // 后台加载完整图片
        const fullImageLoaded = await preloadImage(originalUrl)

        // 如果完整图片加载成功，切换到完整图片
        if (fullImageLoaded) {
            imageRef.value = originalUrl
        }
    }

    /**
     * 批量渐进式加载图片
     * @param urls 图片 URL 数组
     * @returns 返回初始缩略图数组和加载完成的回调
     */
    const loadMultipleProgressively = (urls: string[]) => {
        const displayUrls = ref<string[]>(urls.map(getThumbUrl))

        // 后台加载所有完整图片
        urls.forEach(async (url, index) => {
            const loaded = await preloadImage(url)
            if (loaded) {
                displayUrls.value[index] = url
            }
        })

        return displayUrls
    }

    return {
        getThumbUrl,
        preloadImage,
        loadProgressively,
        loadMultipleProgressively
    }
}
