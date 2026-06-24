import { ref, nextTick, onMounted } from 'vue'

type WxPrivacyOptions = { proactiveOnMount?: boolean }

export function useWxPrivacyBeforeAlbum(options?: WxPrivacyOptions) {
    const wxPrivacyPopupRef: any = ref(null)
    let afterAgree: (() => void) | null = null
    const proactiveOnMount = options?.proactiveOnMount !== false

    const onWxPrivacyAgree = () => {
        const fn = afterAgree
        afterAgree = null
        if (fn) fn()
    }

    const onWxPrivacyDisagree = () => {
        afterAgree = null
    }

    const requestPrivacyThen = (fn: () => void) => {
        afterAgree = fn
        nextTick(() => {
            if (wxPrivacyPopupRef.value) {
                wxPrivacyPopupRef.value.proactive()
            } else {
                const f = afterAgree
                afterAgree = null
                if (f) f()
            }
        })
    }

    if (proactiveOnMount) {
        onMounted(() => {
            nextTick(() => {
                if (wxPrivacyPopupRef.value) {
                    wxPrivacyPopupRef.value.proactive()
                }
            })
        })
    }

    return { wxPrivacyPopupRef, onWxPrivacyAgree, onWxPrivacyDisagree, requestPrivacyThen }
}
