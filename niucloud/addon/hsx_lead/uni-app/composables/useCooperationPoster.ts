import { onBeforeUnmount, ref } from 'vue'
import { getSiteId, getToken, img } from '@/utils/common'
import { createCooperationPoster, type CooperationPosterScene } from '../api/poster'
import { coopError } from '../api/cooperation'

/** One cancellable presentation session, bound to its login and tenant. */
export function useCooperationPoster() {
    const url = ref(''),
        loading = ref(false),
        ready = ref(false),
        saving = ref(false)
    const error = ref(''),
        saveError = ref(''),
        needsSettings = ref(false)
    let epoch = 0,
        active = false,
        disposed = false,
        owner = ''
    const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
    const current = (turn: number) => active && !disposed && turn === epoch && !!getToken() && owner === identity()
    function close() {
        active = false
        epoch++
        url.value = ''
        loading.value = false
        ready.value = false
        saving.value = false
        error.value = ''
        saveError.value = ''
        needsSettings.value = false
    }
    async function generate(scene: CooperationPosterScene) {
        if (loading.value || disposed) return
        close()
        active = true
        owner = identity()
        const turn = ++epoch
        if (!getToken()) {
            error.value = '请先登录，再生成邀请海报'
            return
        }
        loading.value = true
        try {
            const response: any = await createCooperationPoster(scene)
            if (!current(turn)) return
            const path = response?.data?.url
            if (typeof path !== 'string' || !path.trim() || response.data.scene !== scene)
                throw new Error('海报未完整返回，请重新生成')
            const source = img(path)
            if (typeof source !== 'string' || !/^https?:\/\//i.test(source))
                throw new Error('海报地址不可用，请联系平台核对附件域名')
            url.value = source
            // Keep the visible loading state until the actual image has loaded.
        } catch (cause) {
            if (current(turn)) {
                error.value = coopError(cause, '海报生成未完成，请重试')
                loading.value = false
            }
        } finally {
            if (!current(turn) && turn === epoch) close()
        }
    }
    function loaded(source: string) {
        if (!active || disposed || owner !== identity()) {
            close()
            return
        }
        if (source && source === url.value) {
            ready.value = true
            loading.value = false
            error.value = ''
        }
    }
    function imageFailed(source: string) {
        if (!current(epoch)) {
            close()
            return
        }
        if (source && source === url.value) {
            ready.value = false
            loading.value = false
            error.value = '海报图片未加载成功，请重新生成'
            url.value = ''
        }
    }
    function preview() {
        if (!ready.value || !current(epoch)) {
            if (owner !== identity()) close()
            return
        }
        const turn = epoch
        uni.previewImage({
            urls: [url.value],
            current: url.value,
            fail: () => {
                if (current(turn)) saveError.value = '预览未打开，请重试'
            }
        })
    }
    async function save() {
        if (saving.value || !ready.value || !current(epoch)) {
            if (owner !== identity()) close()
            return
        }
        const turn = epoch,
            source = url.value
        saving.value = true
        saveError.value = ''
        needsSettings.value = false
        try {
            const file: any = await new Promise((resolve, reject) =>
                uni.downloadFile({ url: source, success: resolve, fail: reject })
            )
            if (!current(turn)) return
            if (
                !Number.isInteger(file.statusCode) ||
                file.statusCode < 200 ||
                file.statusCode >= 300 ||
                typeof file.tempFilePath !== 'string' ||
                !file.tempFilePath
            )
                throw new Error('海报下载未完成，请重试')
            await new Promise((resolve, reject) =>
                uni.saveImageToPhotosAlbum({ filePath: file.tempFilePath, success: resolve, fail: reject })
            )
            if (current(turn)) uni.showToast({ title: '海报已保存到相册', icon: 'none' })
        } catch (cause: any) {
            if (!current(turn)) return
            const message = String(cause?.errMsg || '')
            if (/auth den(?:y|ied)|authorize.*den(?:y|ied)/i.test(message)) {
                needsSettings.value = true
                saveError.value = '需要相册权限，开启后可再次保存'
            } else if (/cancel/i.test(message)) saveError.value = '已取消保存，可再次操作'
            else if (Number(cause?.errno) === 104) saveError.value = '请先同意小程序隐私授权，再保存到相册'
            else saveError.value = coopError(cause, '保存未完成，请检查网络或相册权限后重试')
        } finally {
            if (current(turn)) saving.value = false
        }
    }
    onBeforeUnmount(() => {
        close()
        disposed = true
    })
    return {
        url,
        loading,
        ready,
        saving,
        error,
        saveError,
        needsSettings,
        generate,
        close,
        loaded,
        imageFailed,
        preview,
        save
    }
}
