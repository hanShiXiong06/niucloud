<template>
    <view class="project-poster">
        <u-popup :show="show" @close="close" overlayOpacity="0.82">
            <view class="poster-popup" @touchmove.prevent.stop>
                <view class="poster-stage">
                    <view v-if="generating" class="poster-loading">
                        <view class="loading-ring"></view>
                        <view class="loading-title">正在生成推广海报</view>
                        <view class="loading-desc">首次生成需要合成图片，完成后再次打开会更快</view>
                    </view>
                    <image v-else-if="posterUrl" class="poster-image" :src="posterUrl" mode="aspectFit" :show-menu-by-longpress="true" />
                </view>

                <view v-if="posterUrl && !generating" class="poster-actions">
                    <!-- #ifdef MP -->
                    <button class="action-item" :plain="true" open-type="share">
                        <view class="action-icon wechat"><u-icon name="share-fill" color="#fff" size="24" /></view>
                        <text>分享给好友</text>
                    </button>
                    <button class="action-item" :plain="true" @click="savePoster">
                        <view class="action-icon save"><u-icon name="download" color="#181818" size="23" /></view>
                        <text>保存海报</text>
                    </button>
                    <!-- #endif -->

                    <!-- #ifdef H5 -->
                    <button class="action-item" :plain="true" @click="copyProjectUrl">
                        <view class="action-icon save"><u-icon name="link" color="#181818" size="23" /></view>
                        <text>复制项目链接</text>
                    </button>
                    <view class="h5-tip">也可以长按上方海报保存图片</view>
                    <!-- #endif -->

                    <!-- #ifdef APP-PLUS -->
                    <button class="action-item" :plain="true" @click="savePoster">
                        <view class="action-icon save"><u-icon name="download" color="#181818" size="23" /></view>
                        <text>保存海报</text>
                    </button>
                    <!-- #endif -->
                </view>
                <view class="poster-cancel" @click="close">取消</view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { copy, img } from '@/utils/common'
import { generateProjectCenterDistributionPoster } from '@/addon/hsx_project_center/api'

const props = defineProps({
    projectId: { type:Number, default:0 },
    inviteToken: { type:String, default:'' },
    posterId: { type:Number, default:0 },
    copyUrl: { type:String, default:'/addon/hsx_project_center/pages/project/detail' },
    copyUrlParam: { type:String, default:'' },
})
const emits = defineEmits(['loading', 'close'])
const show = ref(false)
const generating = ref(false)
const posterUrl = ref('')
const posterKey = ref('')
let pendingKey = ''
const minimumLoadingTime = 500

function currentKey() {
    return JSON.stringify({ project_id:props.projectId, invite:props.inviteToken, poster_id:props.posterId })
}

function open() {
    if (!props.projectId || !props.inviteToken) {
        uni.showToast({ title:'推广信息尚未准备好，请稍后重试', icon:'none' })
        return
    }
    show.value = true
    void loadPoster()
}

async function loadPoster(force = false) {
    const key = currentKey()
    if (!force && posterUrl.value && posterKey.value === key) {
        setLoading(false)
        return
    }
    if (pendingKey === key) {
        setLoading(true)
        return
    }
    pendingKey = key
    posterKey.value = key
    posterUrl.value = ''
    const loadingStartedAt = Date.now()
    setLoading(true)
    uni.showLoading({ title:'正在生成海报', mask:true })
    // 先让弹窗、按钮及 loading 完成一次真实渲染，避免缓存接口在同一帧内返回导致用户看不到状态。
    await nextTick()
    try {
        const response:any = await generateProjectCenterDistributionPoster(props.projectId, props.inviteToken, props.posterId)
        if (posterKey.value !== key) return
        const url = String(response?.data?.url || '')
        if (!url) throw new Error('海报地址为空')
        posterUrl.value = img(url)
    } catch (error:any) {
        if (posterKey.value === key) {
            uni.showToast({ title:error?.message || '海报生成失败，请稍后重试', icon:'none', duration:2600 })
            show.value = false
        }
    } finally {
        const remaining = minimumLoadingTime - (Date.now() - loadingStartedAt)
        if (remaining > 0) await new Promise(resolve => setTimeout(resolve, remaining))
        if (pendingKey === key) pendingKey = ''
        uni.hideLoading()
        setLoading(false)
    }
}

function setLoading(value:boolean) {
    generating.value = value
    emits('loading', value)
}

function copyProjectUrl() {
    // #ifdef H5
    let basePath = location.pathname
    for (const packageName of ['/app/', '/addon/']) {
        const index = basePath.indexOf(packageName)
        if (index !== -1) basePath = basePath.slice(0, index)
    }
    copy(`${location.origin}${basePath}${props.copyUrl}${props.copyUrlParam}`)
    // #endif
}

// #ifdef MP || APP-PLUS
function savePoster() {
    if (!posterUrl.value || generating.value) return
    uni.showLoading({ title:'正在保存', mask:true })
    uni.downloadFile({
        url:posterUrl.value,
        success: (download) => {
            if (download.errMsg !== 'downloadFile:ok') {
                uni.showToast({ title:'海报下载失败', icon:'none' })
                return
            }
            uni.saveImageToPhotosAlbum({
                filePath:download.tempFilePath,
                success: () => uni.showToast({ title:'已保存到相册', icon:'success' }),
                fail: (error) => {
                    const denied = String(error?.errMsg || '').includes('auth')
                    uni.showToast({ title:denied ? '请在设置中允许保存到相册' : '保存失败，请长按海报重试', icon:'none' })
                },
            })
        },
        fail: () => uni.showToast({ title:'海报下载失败，请检查网络', icon:'none' }),
        complete: () => uni.hideLoading(),
    })
}
// #endif

function close() {
    show.value = false
    setLoading(false)
    emits('close')
}

defineExpose({ open, loadPoster })
</script>

<style scoped lang="scss">
.poster-popup{background:#fff}.poster-stage{display:flex;height:calc(100vh - 310rpx - env(safe-area-inset-bottom));min-height:760rpx;align-items:center;justify-content:center;background:rgba(15,20,28,.96)}
.poster-image{width:88%;height:92%}.poster-loading{display:flex;width:560rpx;min-height:270rpx;flex-direction:column;align-items:center;justify-content:center;border:1rpx solid rgba(255,255,255,.13);border-radius:28rpx;background:rgba(255,255,255,.07);color:#fff}
.loading-ring{width:58rpx;height:58rpx;border:6rpx solid rgba(254,229,2,.24);border-top-color:#fee502;border-radius:50%;animation:poster-spin .8s linear infinite}.loading-title{margin-top:30rpx;font-size:29rpx;font-weight:700}.loading-desc{max-width:450rpx;margin-top:12rpx;color:rgba(255,255,255,.64);font-size:22rpx;line-height:34rpx;text-align:center}
.poster-actions{display:flex;min-height:190rpx;align-items:center;justify-content:center;gap:70rpx;padding:24rpx 28rpx;background:#fff}.action-item{display:flex;width:150rpx;flex-direction:column;align-items:center;margin:0;padding:0;border:0;background:transparent;font-size:22rpx;line-height:1.2}.action-item::after{border:0}.action-item text{margin-top:14rpx;color:#344054}.action-icon{display:flex;width:76rpx;height:76rpx;align-items:center;justify-content:center;border-radius:24rpx}.action-icon.wechat{background:#07c160}.action-icon.save{background:#fee502}.h5-tip{color:#98a2b3;font-size:21rpx}
.poster-cancel{height:88rpx;border-top:1rpx solid #eef0f3;background:#fff;color:#344054;font-size:26rpx;line-height:88rpx;text-align:center;padding-bottom:env(safe-area-inset-bottom)}
@keyframes poster-spin{to{transform:rotate(360deg)}}
</style>
