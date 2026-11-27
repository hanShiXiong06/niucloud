import { onBeforeUnmount, ref, watch } from 'vue'
import { onHide, onShow } from '@dcloudio/uni-app'
import { getSiteId, getToken } from '@/utils/common'
import { getPaymentHelp, type PaymentHelp } from '../api/paymentHelp'
import { coopError } from '../api/cooperation'

export function usePaymentHelp(enabled: () => boolean) {
    const data = ref<PaymentHelp | null>(null),
        loading = ref(false),
        error = ref(''),
        opened = ref(false)
    let sequence = 0,
        disposed = false,
        active = true,
        owner = ''
    const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
    const current = () => !disposed && active && enabled() && !!getToken() && owner === identity()
    function invalidate(clear = false) {
        sequence++
        loading.value = false
        opened.value = false
        if (clear) {
            data.value = null
            error.value = ''
        }
    }
    function identify() {
        const who = identity()
        if (who !== owner) {
            invalidate(true)
            owner = who
        }
        return who
    }
    async function refresh() {
        const who = identify()
        if (!active || disposed || !enabled() || !getToken()) {
            invalidate(true)
            return
        }
        if (loading.value) return
        const turn = ++sequence
        loading.value = true
        error.value = ''
        opened.value = false
        try {
            const response = await getPaymentHelp()
            if (turn !== sequence || !current() || who !== identity()) return
            const value = response?.data
            if (
                !value ||
                typeof value.enabled !== 'boolean' ||
                (value.enabled &&
                    (['title', 'content', 'qr_image', 'remark'].some((key) => typeof value[key] !== 'string') ||
                        !value.content.trim() ||
                        !value.qr_image.trim()))
            )
                throw Error('支付帮助暂不可用，请重新加载')
            data.value = value.enabled
                ? {
                      enabled: true,
                      title: value.title,
                      content: value.content,
                      qr_image: value.qr_image,
                      remark: value.remark
                  }
                : null
        } catch (cause) {
            if (turn === sequence && current()) {
                data.value = null
                error.value = coopError(cause, '支付帮助暂不可用，请重新加载')
            }
        } finally {
            if (turn === sequence && !disposed) loading.value = false
        }
    }
    function open() {
        identify()
        if (current() && data.value?.enabled === true) opened.value = true
    }
    function close() {
        opened.value = false
    }
    watch(
        enabled,
        (value) => {
            if (value) void refresh()
            else invalidate(true)
        },
        { immediate: true }
    )
    onShow(() => {
        active = true
        void refresh()
    })
    onHide(() => {
        active = false
        invalidate()
    })
    onBeforeUnmount(() => {
        disposed = true
        active = false
        invalidate(true)
    })
    return { data, loading, error, opened, refresh, open, close, current }
}
