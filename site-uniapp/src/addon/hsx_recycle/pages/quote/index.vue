<template>
    <view class="quote-page">
        <template v-if="!selected">
            <view class="quote-toolbar">
                <u-search v-model="keyword" placeholder="搜索报价分类" :show-action="false" bg-color="#f3f5f8" />
                <view class="quote-tabs">
                    <view v-for="tab in tabs" :key="tab.value" class="quote-tab" :class="{ 'quote-tab--active': status === tab.value }" @click="changeStatus(tab.value)">{{ tab.label }}</view>
                    <text class="quote-refresh" @click="loadOptions()">刷新</text>
                </view>
            </view>

            <view class="quote-content">
                <view v-if="loading && !categories.length" class="quote-state">正在加载报价分类…</view>
                <view v-else-if="loadError" class="quote-error">
                    <text>{{ loadError }}</text>
                    <button class="quote-button quote-button--small" :disabled="loading" @click="loadOptions()">重新加载</button>
                </view>
                <template v-if="categories.length">
                    <view class="quote-list-heading"><text>{{ filtered.length }} 个分类</text><text>{{ canEdit ? '选择分类更新报价图' : '当前账号仅可查看' }}</text></view>
                    <view v-for="item in visibleCategories" :key="item.category_id" class="quote-category" @click="openCategory(item)">
                        <view class="quote-category-thumb" :class="{ 'quote-category-thumb--ready': item.images }">
                            <u-icon name="photo" size="25" :color="item.images ? '#1b73e8' : '#a2adbd'" />
                        </view>
                        <view class="quote-category-info">
                            <text class="quote-category-name">{{ item.category_name }}</text>
                            <text v-if="item.parentName" class="quote-category-path">{{ item.parentName }}</text>
                            <view class="quote-category-status"><text :class="item.images ? 'quote-text-success' : 'quote-text-muted'">{{ item.images ? '已有报价图' : '待上传' }}</text><text v-if="item.site_id !== siteId" class="quote-readonly">公共 · 只读</text></view>
                        </view>
                        <u-icon name="arrow-right" size="15" color="#a2adbd" />
                    </view>
                    <view v-if="!filtered.length" class="quote-state">未找到匹配分类，试试其他关键词</view>
                    <button v-if="filtered.length > displayLimit" class="quote-button quote-button--more" @click="displayLimit += 40">加载更多分类</button>
                </template>
                <view v-else-if="!loading && !loadError" class="quote-state">暂无报价分类，请先在电脑端添加分类</view>
            </view>
        </template>

        <template v-else>
            <view class="quote-detail-header">
                <view class="quote-back" @click="closeCategory"><u-icon name="arrow-left" size="16" color="#64748b" /><text>分类列表</text></view>
                <text class="quote-detail-name">{{ selected.category_name }}</text>
                <text v-if="selected.parentName" class="quote-detail-path">{{ selected.parentName }}</text>
            </view>

            <view class="quote-content quote-content--detail">
                <view v-if="!editable" class="quote-note">{{ selected.site_id !== siteId ? '公共分类仅可查看，请联系平台管理员更新报价。' : '当前账号没有报价编辑权限，可联系站点管理员授权。' }}</view>
                <view v-if="Number(selected.is_show) === 0" class="quote-note">此分类暂未对客户展示，更新报价图不会自动开启展示。</view>
                <view class="quote-card">
                    <view class="quote-section-heading"><text>当前报价</text><text class="quote-link" :class="{ 'quote-link--disabled': refreshingCurrent || saving }" @click="refreshCurrent">{{ refreshingCurrent ? '刷新中…' : '刷新当前报价' }}</text></view>
                    <view v-if="selected.images" class="quote-preview" @click="preview(selected.images)">
                        <image :src="img(selected.images)" mode="widthFix" @error="currentImageError = true" @load="currentImageError = false" />
                        <view class="quote-preview-label">{{ currentImageError ? '图片加载失败，点击重试预览' : '点击查看完整报价图' }}</view>
                    </view>
                    <view v-else class="quote-image-empty"><u-icon name="photo" size="34" color="#b5bfcd" /><text>还没有报价图</text></view>
                    <text v-if="currentUpdatedAt" class="quote-caption">更新于 {{ formatQuoteTime(currentUpdatedAt) }}</text>
                    <text v-if="editable" class="quote-caption">只替换报价图，不改变分类显示和查看权限</text>
                </view>

                <view v-if="editable && draftLocal" class="quote-card quote-draft-card">
                    <view class="quote-section-heading"><text>新报价 · 尚未发布</text><text v-if="!uploading && !saving" class="quote-link" @click="clearDraft">移除</text></view>
                    <view class="quote-preview" @click="preview(draftPath || draftLocal, !draftPath)">
                        <image :src="draftPath ? img(draftPath) : draftLocal" mode="widthFix" />
                        <view class="quote-preview-label">点击核对完整长图</view>
                    </view>
                    <view v-if="uploading" class="quote-upload-status">原图上传中，请稍候…</view>
                    <view v-else-if="uploadError" class="quote-error quote-error--inline"><text>{{ uploadError }}</text><button class="quote-button quote-button--small" @click="uploadDraft">重试上传</button></view>
                    <view v-else class="quote-upload-status quote-text-success">图片已上传，确认更新后才会替换当前报价</view>
                </view>

                <view v-if="saveError" class="quote-error quote-save-error"><text>{{ saveError }}</text><text class="quote-caption">新图已保留，可核对当前报价后重试。</text></view>

                <view class="quote-card quote-history-card">
                    <view class="quote-section-heading quote-history-toggle" @click="toggleHistory"><text>更新记录</text><u-icon :name="historyVisible ? 'arrow-up' : 'arrow-down'" size="14" color="#94a3b8" /></view>
                    <view v-if="historyVisible" class="quote-history-content">
                        <view v-if="historyError" class="quote-error quote-error--inline"><text>{{ historyError }}</text><button class="quote-button quote-button--small" @click="loadHistory(true)">重试</button></view>
                        <view v-for="item in historyRows" :key="item.id" class="quote-history-row" @click="preview(item.images)">
                            <view class="quote-history-image"><u-icon name="photo" size="22" color="#94a3b8" /></view>
                            <view class="quote-history-info"><text>{{ formatQuoteTime(item.create_time) || '时间未记录' }}</text><text class="quote-caption">{{ item.operator_name || '系统更新' }}{{ item.remark ? ' · ' + item.remark : '' }}</text></view>
                            <u-icon name="arrow-right" size="13" color="#a2adbd" />
                        </view>
                        <view v-if="historyLoading" class="quote-history-state">正在加载…</view>
                        <view v-else-if="!historyRows.length && !historyError" class="quote-history-state">暂无更新记录</view>
                        <button v-if="historyRows.length < historyTotal && !historyLoading && !historyError" class="quote-button quote-button--more" @click="loadHistory()">查看更多记录</button>
                    </view>
                </view>

                <view v-if="editable" class="quote-help" @click="helpVisible = !helpVisible"><text>更新说明</text><u-icon :name="helpVisible ? 'arrow-up' : 'arrow-down'" size="12" color="#94a3b8" /></view>
                <view v-if="editable && helpVisible" class="quote-help-text">建议上传 JPG / PNG 原图，长图可点击放大。确认更新后，电脑端和客户报价页使用新图；历史记录保留。若此分类已配置自动采集，下一次采集可能覆盖手动更新。</view>
            </view>

            <view v-if="editable" class="quote-footer">
                <template v-if="draftLocal">
                    <view class="quote-footer-secondary"><u-button text="重新选图" :plain="true" :disabled="choosing || uploading || saving" @click="chooseImage" /></view>
                    <view class="quote-footer-primary"><u-button :text="saving ? '正在更新…' : '确认更新报价'" type="primary" :loading="saving" :disabled="!draftPath || uploading || saving || refreshingCurrent" @click="saveQuote" /></view>
                </template>
                <u-button v-else text="上传最新报价图" type="primary" :disabled="choosing" @click="chooseImage" />
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onUnload, onPullDownRefresh } from '@dcloudio/uni-app'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'
import { previewImages } from '@/addon/hsx_recycle/utils/preview'
import { getQuoteOptions, updateQuoteImage, getQuoteHistory } from '@/addon/hsx_recycle/api/quote'
import { flattenQuoteCategories, filterQuoteCategories, formatQuoteTime, withQuoteUploadTimeout } from '@/addon/hsx_recycle/utils/quote'
import type { QuoteCategory } from '@/addon/hsx_recycle/utils/quote'

const categories = ref<QuoteCategory[]>([])
const siteId = ref(-1)
const canEdit = ref(false)
const loading = ref(false)
const loadError = ref('')
const keyword = ref('')
const status = ref('all')
const displayLimit = ref(40)
const tabs = [{ label: '全部', value: 'all' }, { label: '已有报价', value: 'ready' }, { label: '待上传', value: 'empty' }]
const filtered = computed(() => filterQuoteCategories(categories.value, keyword.value, status.value))
const visibleCategories = computed(() => filtered.value.slice(0, displayLimit.value))
const selected = ref<QuoteCategory | null>(null)
const editable = computed(() => Boolean(selected.value && canEdit.value && siteId.value > 0 && selected.value.site_id === siteId.value))
const expectedImages = ref('')
const currentUpdatedAt = ref<string | number>('')
const currentImageError = ref(false)
const refreshingCurrent = ref(false)
const draftLocal = ref('')
const draftPath = ref('')
const choosing = ref(false)
const uploading = ref(false)
const uploadError = ref('')
const saving = ref(false)
const saveError = ref('')
const helpVisible = ref(false)
const historyVisible = ref(false)
const historyRows = ref<any[]>([])
const historyTotal = ref(0)
const historyPage = ref(0)
const historyLoading = ref(false)
const historyError = ref('')
let loadToken = 0
let uploadToken = 0
let chooseToken = 0
let historyToken = 0
let alive = true
let initialCategoryId = 0

const errorText = (error: any, fallback: string) => String(error?.msg || error?.message || error?.errMsg || fallback)
const confirm = (title: string, content: string): Promise<boolean> => new Promise(resolve => uni.showModal({ title, content, confirmText: '确认', success: result => resolve(result.confirm), fail: () => resolve(false) }))
const toast = (title: string) => uni.showToast({ title, icon: 'none' })

const loadOptions = async () => {
    const token = ++loadToken
    loading.value = true
    loadError.value = ''
    try {
        const res: any = await getQuoteOptions()
        if (!alive || token !== loadToken) return null
        if (!Array.isArray(res?.data?.categories)) throw new Error('报价分类返回异常，请重新加载')
        categories.value = flattenQuoteCategories(res.data.categories)
        siteId.value = Number(res.data.site_id)
        canEdit.value = res.data.can_edit === true || res.data.can_edit === 1
        if (initialCategoryId) {
            const category = categories.value.find(item => item.category_id === initialCategoryId)
            initialCategoryId = 0
            if (category) openCategory(category)
        }
        return categories.value
    } catch (error) {
        if (alive && token === loadToken) loadError.value = errorText(error, '报价分类加载失败，请重试')
        return null
    } finally {
        if (alive && token === loadToken) loading.value = false
    }
}

const changeStatus = (value: string) => { status.value = value; displayLimit.value = 40 }
watch(keyword, () => { displayLimit.value = 40 })

const resetHistory = () => {
    historyToken++
    historyRows.value = []
    historyTotal.value = 0
    historyPage.value = 0
    historyError.value = ''
    historyLoading.value = false
}

const clearDraft = () => {
    uploadToken++
    chooseToken++
    choosing.value = false
    draftLocal.value = ''
    draftPath.value = ''
    uploadError.value = ''
    saveError.value = ''
    uploading.value = false
}

const openCategory = (category: QuoteCategory) => {
    selected.value = { ...category }
    expectedImages.value = category.images
    currentUpdatedAt.value = ''
    currentImageError.value = false
    historyVisible.value = false
    helpVisible.value = false
    clearDraft()
    resetHistory()
    uni.pageScrollTo({ scrollTop: 0, duration: 0 })
}

const closeCategory = async () => {
    if (saving.value || refreshingCurrent.value) return
    if (draftLocal.value && !await confirm('放弃这次更新？', '当前报价尚未改变。返回后，新图草稿将不再保留。')) return
    clearDraft()
    resetHistory()
    selected.value = null
    uni.pageScrollTo({ scrollTop: 0, duration: 0 })
}

const refreshCurrent = async () => {
    if (!selected.value || refreshingCurrent.value || saving.value) return
    if (draftLocal.value && !await confirm('重新核对当前报价？', '将读取最新报价，新图草稿会保留。核对后仍需点击“确认更新报价”。')) return
    const categoryId = selected.value.category_id
    refreshingCurrent.value = true
    const rows = await loadOptions()
    if (alive && selected.value?.category_id === categoryId) {
        const fresh = rows?.find(item => item.category_id === categoryId)
        if (fresh) {
            selected.value = { ...fresh }
            expectedImages.value = fresh.images
            currentImageError.value = false
            currentUpdatedAt.value = ''
            saveError.value = ''
            resetHistory()
            if (historyVisible.value) loadHistory(true)
            toast('已读取当前最新报价')
        } else toast(loadError.value || '分类已不存在或无权查看，请返回刷新')
    }
    refreshingCurrent.value = false
}

const preview = (path: string, local = false) => {
    if (path) previewImages([local ? path : img(path)])
}

const chooseImage = () => {
    if (!editable.value || choosing.value || uploading.value || saving.value) return
    const categoryId = selected.value!.category_id
    const token = ++chooseToken
    choosing.value = true
    uni.chooseImage({
        count: 1,
        sourceType: ['album', 'camera'],
        sizeType: ['original'],
        success: result => {
            if (!alive || token !== chooseToken || selected.value?.category_id !== categoryId || !editable.value) return
            const path = result.tempFilePaths?.[0]
            if (!path) return toast('未取得图片，请重新选择')
            draftLocal.value = path
            draftPath.value = ''
            uploadError.value = ''
            saveError.value = ''
            uploadDraft()
        },
        fail: (error: any) => { if (alive && token === chooseToken && !String(error?.errMsg || '').includes('cancel')) toast('无法选择图片，请重试') },
        complete: () => { if (alive && token === chooseToken) choosing.value = false }
    })
}

const uploadDraft = async () => {
    if (!editable.value || !draftLocal.value || uploading.value || saving.value) return
    const token = ++uploadToken
    const categoryId = selected.value!.category_id
    const local = draftLocal.value
    uploading.value = true
    uploadError.value = ''
    try {
        const res: any = await withQuoteUploadTimeout(uploadImage({ filePath: local, name: 'file', timeout: 120000 }))
        if (!alive || token !== uploadToken || selected.value?.category_id !== categoryId) return
        const path = res?.data?.url || res?.data?.path
        if (typeof path !== 'string' || !path.trim()) throw new Error('上传未返回图片地址，请重新上传')
        draftPath.value = path.trim()
    } catch (error) {
        if (alive && token === uploadToken && selected.value?.category_id === categoryId) uploadError.value = errorText(error, '上传失败，请重试；当前报价未改变')
    } finally {
        if (alive && token === uploadToken) uploading.value = false
    }
}

const saveQuote = async () => {
    if (!editable.value || !draftPath.value || uploading.value || saving.value || refreshingCurrent.value) return
    saving.value = true
    saveError.value = ''
    try {
        const effect = Number(selected.value!.is_show) === 0 ? '此分类暂未展示，更新后仍不向客户展示。' : '电脑端与有查看权限的客户报价页使用新图。'
        if (!await confirm('确认更新报价？', `将替换“${selected.value!.category_name}”的报价图。${effect}不改变分类显示和查看权限。`)) return
        const categoryId = selected.value!.category_id
        const res: any = await updateQuoteImage(categoryId, { images: draftPath.value, expected_images: expectedImages.value, remark: '移动管理端更新报价图' })
        if (!alive || selected.value?.category_id !== categoryId) return
        if (Number(res?.data?.category_id) !== categoryId || typeof res?.data?.images !== 'string') throw new Error('更新结果未确认，请刷新当前报价后核对')
        selected.value.images = res.data.images
        expectedImages.value = res.data.images
        currentUpdatedAt.value = res.data.update_time || ''
        const category = categories.value.find(item => item.category_id === categoryId)
        if (category) category.images = res.data.images
        clearDraft()
        currentImageError.value = false
        resetHistory()
        if (historyVisible.value) loadHistory(true)
        toast(res.data.changed === false ? '当前报价已是这张图片' : '报价已更新')
    } catch (error) {
        if (alive) saveError.value = errorText(error, '未确认更新结果，请刷新当前报价后核对')
    } finally {
        if (alive) saving.value = false
    }
}

const toggleHistory = () => {
    historyVisible.value = !historyVisible.value
    if (historyVisible.value && !historyRows.value.length) loadHistory(true)
}

const loadHistory = async (reset = false) => {
    if (!selected.value || historyLoading.value) return
    const categoryId = selected.value.category_id
    const token = ++historyToken
    const page = reset ? 1 : historyPage.value + 1
    historyLoading.value = true
    historyError.value = ''
    try {
        const res: any = await getQuoteHistory(categoryId, page)
        if (!alive || token !== historyToken || selected.value?.category_id !== categoryId) return
        if (!Array.isArray(res?.data?.data)) throw new Error('更新记录返回异常，请重试')
        historyRows.value = reset ? res.data.data : [...historyRows.value, ...res.data.data]
        historyTotal.value = Number(res.data.total) || historyRows.value.length
        historyPage.value = page
    } catch (error) {
        if (alive && token === historyToken) historyError.value = errorText(error, '更新记录加载失败')
    } finally {
        if (alive && token === historyToken) historyLoading.value = false
    }
}

// 小程序系统返回也提示未发布草稿；不需要侵入框架路由。
watch(draftLocal, value => {
    // #ifdef MP-WEIXIN
    if (value) uni.enableAlertBeforeUnload?.({ message: '新报价尚未确认更新，确定离开吗？' })
    else uni.disableAlertBeforeUnload?.({})
    // #endif
})
onLoad((query: any) => { initialCategoryId = Number(query?.category_id) || 0; loadOptions() })
onPullDownRefresh(async () => {
    if (selected.value) await refreshCurrent()
    else await loadOptions()
    uni.stopPullDownRefresh()
})
onUnload(() => { alive = false; loadToken++; uploadToken++; chooseToken++; historyToken++ })
</script>

<style scoped lang="scss">
.quote-page { min-height: 100vh; background: #f5f7fa; color: var(--hsx-text-strong, #1f2937); font-size: 28rpx; }
.quote-toolbar { position: sticky; top: 0; z-index: 3; padding: 22rpx 24rpx 0; background: #fff; }
.quote-tabs { display: flex; align-items: center; gap: 34rpx; margin-top: 12rpx; }
.quote-tab { padding: 22rpx 0; color: #64748b; border-bottom: 4rpx solid transparent; font-size: 26rpx; }
.quote-tab--active { color: var(--hsx-primary, #1b73e8); border-color: var(--hsx-primary, #1b73e8); font-weight: 600; }
.quote-refresh { margin-left: auto; color: #64748b; font-size: 24rpx; padding: 18rpx 0 18rpx 12rpx; }
.quote-content { padding: 24rpx; }
.quote-content--detail { padding-bottom: calc(160rpx + env(safe-area-inset-bottom)); }
.quote-list-heading { display: flex; justify-content: space-between; gap: 16rpx; margin: 0 0 18rpx; color: #8b96a6; font-size: 23rpx; }
.quote-category { display: flex; align-items: center; gap: 20rpx; padding: 22rpx; margin-bottom: 16rpx; background: #fff; border-radius: 16rpx; }
.quote-category-thumb { width: 90rpx; height: 100rpx; border-radius: 10rpx; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #f2f5f9; overflow: hidden; }
.quote-category-thumb--ready { background: #eef5ff; }
.quote-category-info { flex: 1; min-width: 0; }
.quote-category-name, .quote-category-path { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.quote-category-name { font-size: 29rpx; font-weight: 600; }
.quote-category-path { margin-top: 8rpx; color: #94a3b8; font-size: 23rpx; }
.quote-category-status { display: flex; flex-wrap: wrap; gap: 14rpx; margin-top: 10rpx; font-size: 22rpx; }
.quote-text-success { color: #21976b; }
.quote-text-muted { color: #94a3b8; }
.quote-readonly { color: #94a3b8; }
.quote-state { padding: 100rpx 20rpx; text-align: center; line-height: 1.7; color: #94a3b8; font-size: 26rpx; }
.quote-button { margin: 0; padding: 0 24rpx; min-height: 64rpx; display: flex; align-items: center; justify-content: center; background: #fff; color: var(--hsx-primary, #1b73e8); font-size: 25rpx; line-height: 1.5; border: 1rpx solid #dbe3ee; border-radius: 10rpx; }
.quote-button::after { border: 0; }
.quote-button--small { margin-top: 18rpx; align-self: flex-start; }
.quote-button--more { margin: 24rpx auto; background: transparent; border: 0; }
.quote-error { display: flex; flex-direction: column; padding: 24rpx; border-radius: 14rpx; background: #fff6f3; color: #bd552d; font-size: 25rpx; line-height: 1.6; overflow-wrap: anywhere; }
.quote-error--inline { padding: 20rpx 0 0; background: transparent; }
.quote-save-error { margin-bottom: 20rpx; }
.quote-detail-header { background: #fff; padding: 20rpx 28rpx 28rpx; }
.quote-back { display: flex; align-items: center; gap: 6rpx; color: #64748b; padding: 4rpx 0 24rpx; font-size: 25rpx; }
.quote-detail-name { display: block; font-size: 35rpx; font-weight: 700; line-height: 1.5; overflow-wrap: anywhere; }
.quote-detail-path { display: block; color: #94a3b8; margin-top: 8rpx; font-size: 24rpx; line-height: 1.6; overflow-wrap: anywhere; }
.quote-note { margin-bottom: 20rpx; color: #7c8796; font-size: 24rpx; line-height: 1.7; }
.quote-card { background: #fff; border-radius: 18rpx; padding: 24rpx; margin-bottom: 20rpx; }
.quote-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; margin-bottom: 22rpx; font-size: 28rpx; font-weight: 600; }
.quote-link { flex-shrink: 0; font-size: 23rpx; color: var(--hsx-primary, #1b73e8); font-weight: 400; }
.quote-link--disabled { color: #b2bdcc; }
.quote-preview { position: relative; width: 100%; max-height: 420rpx; overflow: hidden; border: 1rpx solid #edf0f5; border-radius: 12rpx; background: #f6f8fa; }
.quote-preview image { width: 100%; display: block; min-height: 140rpx; }
.quote-preview-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(30, 41, 59, .68); color: #fff; text-align: center; padding: 14rpx; font-size: 23rpx; }
.quote-image-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16rpx; height: 220rpx; background: #f7f9fc; border-radius: 12rpx; color: #a1acbb; font-size: 25rpx; }
.quote-caption { display: block; margin-top: 12rpx; color: #94a3b8; font-size: 23rpx; line-height: 1.6; overflow-wrap: anywhere; }
.quote-draft-card { border: 1rpx solid #d4e7fc; }
.quote-upload-status { font-size: 24rpx; line-height: 1.7; color: #64748b; margin-top: 20rpx; }
.quote-history-card { padding-top: 0; padding-bottom: 0; }
.quote-history-toggle { padding: 26rpx 0; margin: 0; }
.quote-history-content { border-top: 1rpx solid #f0f3f7; }
.quote-history-row { display: flex; align-items: center; gap: 18rpx; padding: 22rpx 0; border-bottom: 1rpx solid #f1f4f8; font-size: 24rpx; }
.quote-history-image { width: 80rpx; height: 80rpx; border-radius: 8rpx; flex-shrink: 0; background: #f4f6f9; display: flex; align-items: center; justify-content: center; }
.quote-history-info { flex: 1; min-width: 0; }
.quote-history-info .quote-caption { margin-top: 7rpx; }
.quote-history-state { padding: 28rpx 0; color: #94a3b8; text-align: center; font-size: 24rpx; }
.quote-help { display: flex; align-items: center; justify-content: center; gap: 10rpx; color: #94a3b8; font-size: 23rpx; padding: 12rpx; }
.quote-help-text { color: #94a3b8; font-size: 23rpx; line-height: 1.8; padding: 12rpx 16rpx; }
.quote-footer { position: fixed; z-index: 4; left: 0; right: 0; bottom: 0; display: flex; gap: 20rpx; align-items: center; padding: 22rpx 24rpx calc(22rpx + env(safe-area-inset-bottom)); background: #fff; border-top: 1rpx solid #edf0f5; }
.quote-footer-secondary { width: 220rpx; flex-shrink: 0; }
.quote-footer-primary { flex: 1; min-width: 0; }
</style>
