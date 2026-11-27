import { computed, ref } from 'vue'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'

export function parseErpVoucherUrls(value: unknown): string[] {
    if (Array.isArray(value)) return value.map(String).map(item => item.trim()).filter(Boolean)
    const text = String(value || '').trim()
    if (!text) return []
    if (text.startsWith('[')) {
        try {
            const parsed = JSON.parse(text)
            if (Array.isArray(parsed)) return parsed.map(String).map(item => item.trim()).filter(Boolean)
        } catch (_) {}
    }
    return text.split(',').map(item => item.trim()).filter(Boolean)
}

export function serializeErpVoucherUrls(value: unknown): string {
    return Array.from(new Set(parseErpVoucherUrls(value))).join(',')
}

export function useErpVoucher(initialValue: unknown = '', maxCount = 3) {
    const paths = ref<string[]>(parseErpVoucherUrls(initialValue).slice(0, maxCount))
    const uploading = ref(false)
    const urls = computed(() => paths.value.map(path => img(path)))
    const value = computed(() => serializeErpVoucherUrls(paths.value))

    function reset(next: unknown) {
        paths.value = parseErpVoucherUrls(next).slice(0, maxCount)
    }

    function preview(index = 0) {
        if (!urls.value.length) return
        uni.previewImage({ current: urls.value[index] || urls.value[0], urls: urls.value })
    }

    function remove(index: number) {
        paths.value.splice(index, 1)
    }

    async function chooseAndUpload() {
        if (uploading.value || paths.value.length >= maxCount) return
        const result: any = await new Promise((resolve, reject) => {
            uni.chooseImage({
                count: Math.min(9, maxCount - paths.value.length),
                sourceType: ['album', 'camera'],
                sizeType: ['compressed'],
                success: resolve,
                fail: reject,
            })
        }).catch((error: any) => {
            if (!String(error?.errMsg || '').includes('cancel')) {
                uni.showToast({ title: error?.errMsg || '选择图片失败', icon: 'none' })
            }
            return null
        })
        if (!result) return

        const files = Array.isArray(result.tempFiles) && result.tempFiles.length
            ? result.tempFiles
            : (Array.isArray(result.tempFilePaths) ? result.tempFilePaths.map((path: string) => ({ path })) : [])
        uploading.value = true
        try {
            for (const file of files) {
                if (paths.value.length >= maxCount) break
                const filePath = String(file?.path || '')
                if (!filePath) continue
                const response: any = await uploadImage({ filePath, name: 'file' })
                const path = String(response?.data?.url || response?.data?.path || '')
                if (!path) throw new Error('上传结果缺少图片地址')
                paths.value.push(path)
            }
        } catch (error: any) {
            uni.showToast({ title: error?.message || error?.msg || '凭证上传失败', icon: 'none' })
        } finally {
            uploading.value = false
        }
    }

    return { paths, urls, value, uploading, reset, preview, remove, chooseAndUpload }
}
