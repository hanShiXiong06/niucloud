<template>
    <view class="quote-search-page">
        <!-- #ifdef H5 -->
        <up-navbar title="报价搜索" left-text="返回" :fixed="false" :safe-area-inset-top="false" @left-click="goBack" />
        <!-- #endif -->
        <view class="search-toolbar">
            <view class="search-toolbar-inner"><QuoteSearchField v-model="input" @search="submit" @clear="clearSearch" /></view>
        </view>
        <view class="search-body">
            <template v-if="!keyword">
                <view class="section-heading">
                    <text>最近搜索</text>
                    <button v-if="history.length" class="icon-button" aria-label="清空搜索历史" @click="clearHistory"><up-icon name="trash" size="20" color="#818995" /></button>
                </view>
                <view v-if="history.length" class="history-list">
                    <button v-for="entry in history" :key="entry.keyword" class="history-item" @click="submit(entry.keyword)">
                        <view class="history-keyword"><up-icon name="clock" size="16" color="#a1a7b0" /><text>{{ entry.keyword }}</text></view>
                        <view class="history-count"><text>上次 {{ entry.total }} 条</text><up-icon name="arrow-upward" size="13" color="#a1a7b0" /></view>
                    </button>
                </view>
                <view v-else class="empty-state"><up-icon name="search" size="42" color="#b4bcc7" /><text class="empty-title">搜型号，查回收价</text><text class="empty-note">还没有搜索记录</text></view>
            </template>
            <template v-else>
                <view class="result-heading"><text class="result-keyword">{{ keyword }}</text><text class="result-count">{{ loading && !rows.length ? '正在查找报价' : `共 ${total} 条报价` }}</text></view>
                <view v-if="loading && !rows.length" class="loading-state">
                    <view v-for="item in 3" :key="item" class="skeleton-result"><view class="skeleton-line short" /><view class="skeleton-line title" /><view class="skeleton-prices"><view /><view /><view /></view></view>
                </view>
                <view v-else-if="!rows.length && !error" class="empty-state"><up-icon name="search" size="42" color="#b4bcc7" /><text class="empty-title">没有找到相关型号</text><text class="empty-note">当前报价源暂无匹配记录</text><button class="secondary-button" @click="clearSearch">换个型号</button></view>
                <view v-if="rows.length" class="result-list"><QuoteSearchResult v-for="row in rows" :key="row.id" :row="row" @open="openQuote" /></view>
                <view v-if="error" class="error-state"><text>{{ error }}</text><button class="secondary-button" @click="retry">重新加载</button></view>
                <view v-else-if="rows.length" class="list-footer">
                    <button v-if="hasMore" class="load-more" :disabled="loading" @click="loadMore">{{ loading ? '加载中…' : '加载更多报价' }}</button>
                    <text v-else class="end-note">已显示全部报价</text>
                    <text class="price-notice">报价仅供参考，最终价格以质检结果为准</text>
                </view>
            </template>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad, onShow, onUnload, onReachBottom } from '@dcloudio/uni-app'
import { getSiteId, getToken, redirect } from '@/utils/common'
import useMemberStore from '@/stores/member'
import QuoteSearchField from '@/addon/recycle_quote_spider/components/QuoteSearchField.vue'
import QuoteSearchResult from '@/addon/recycle_quote_spider/components/QuoteSearchResult.vue'
import type { QuoteSearchRow } from '@/addon/recycle_quote_spider/api/quotation'
import { decodeSearchKeyword, normalizeSearchKeyword, readSearchHistory, recordSearchHistory, searchHistoryKey, PRICE_PAGE, type QuoteSearchHistory } from '@/addon/recycle_quote_spider/utils/quoteSearch'
import { useQuoteSearch } from './composables/useQuoteSearch'

const input = ref('')
const history = ref<QuoteSearchHistory[]>([])
let storageKey = ''
const { keyword, sourceId, rows, total, loading, error, hasMore, search, reset, loadMore, retry } = useQuoteSearch((value, count) => {
    history.value = recordSearchHistory(history.value, value, count)
    try { uni.setStorageSync(storageKey, history.value) } catch { /* Storage limits must not interrupt price queries. */ }
})

function loadHistory() {
    const siteId = getSiteId(import.meta.env.VITE_SITE_ID || uni.getStorageSync('wap_site_id'))
    const memberId = getToken() ? (useMemberStore().info?.member_id || uni.getStorageSync('wap_member_id') || 0) : 0
    const nextKey = searchHistoryKey(siteId, memberId, sourceId.value)
    if (storageKey === nextKey) return
    if (storageKey) { reset(); input.value = '' }
    storageKey = nextKey
    try { history.value = readSearchHistory(uni.getStorageSync(storageKey)) } catch { history.value = [] }
}

function submit(value: string) {
    input.value = normalizeSearchKeyword(value)
    search(input.value)
    uni.pageScrollTo({ scrollTop: 0, duration: 0 })
}
function clearSearch() { input.value = ''; reset() }
function goBack() { uni.navigateBack({ fail: () => redirect({ url: '/app/pages/index/index', mode: 'reLaunch' }) }) }
function clearHistory() {
    uni.showModal({ title: '清空最近搜索？', content: '仅清除当前账号在本机的搜索记录', success: result => {
        if (!result.confirm) return
        history.value = []
        try { uni.removeStorageSync(storageKey) } catch { /* A restricted storage environment can still search. */ }
    } })
}
function openQuote(row: QuoteSearchRow) {
    redirect({ url: PRICE_PAGE, param: { id: row.item_id, source: 'spider', model_name: encodeURIComponent(row.model_name) } })
}
onLoad(options => {
    sourceId.value = Math.max(0, Number(options?.source_id) || 0)
    loadHistory()
    input.value = decodeSearchKeyword(options?.keyword)
    if (input.value) search(input.value)
})
onShow(loadHistory)
onReachBottom(() => { if (!error.value) loadMore() })
onUnload(reset)
</script>

<style scoped lang="scss">
.quote-search-page { min-height: 100vh; background: #f6f7f9; color: #20252e; }
.search-toolbar { position: sticky; top: var(--window-top, 0px); z-index: 5; padding: 20rpx 24rpx; background: #fff; border-bottom: 1px solid #eef0f3; }
.search-toolbar-inner { max-width: 736px; margin: 0 auto; }
/* #ifdef H5 */
.search-toolbar { top: 0; }
/* #endif */
.search-body { max-width: 760px; margin: 0 auto; padding: 24rpx 24rpx calc(40rpx + env(safe-area-inset-bottom)); box-sizing: border-box; }
.section-heading, .result-heading { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; margin-bottom: 20rpx; min-height: 48rpx; }
.section-heading { font-size: 28rpx; font-weight: 600; }
.icon-button, .history-item, .secondary-button, .load-more { margin: 0; border: 0; }
.icon-button::after, .history-item::after, .secondary-button::after, .load-more::after { border: 0; }
.icon-button { display: flex; align-items: center; justify-content: center; padding: 0; width: 64rpx; height: 48rpx; background: transparent; }
.history-list { border-top: 1px solid #e9ecf0; }
.history-item { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 20rpx; padding: 26rpx 0; border-radius: 0; background: transparent; border-bottom: 1px solid #e9ecf0; text-align: left; line-height: 1.5; }
.history-keyword { display: flex; align-items: center; gap: 16rpx; flex: 1; min-width: 0; font-size: 28rpx; color: #363e49; overflow-wrap: anywhere; }
.history-count { display: flex; align-items: center; gap: 12rpx; flex-shrink: 0; font-size: 22rpx; color: #8c949f; }
.result-keyword { font-size: 28rpx; font-weight: 600; overflow-wrap: anywhere; }
.result-count { flex-shrink: 0; font-size: 24rpx; color: #7a8492; }
.result-list { display: flex; flex-direction: column; gap: 20rpx; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 120rpx 0 80rpx; gap: 20rpx; text-align: center; }
.empty-title { font-size: 30rpx; font-weight: 500; color: #434c58; }
.empty-note { font-size: 25rpx; color: #87909c; line-height: 1.6; }
.secondary-button, .load-more { padding: 18rpx 32rpx; line-height: 1.4; font-size: 26rpx; background: #fff; color: #2563eb; border: 1px solid #e1e5eb; border-radius: 12rpx; }
.empty-state .secondary-button { margin-top: 16rpx; }
.error-state { display: flex; flex-direction: column; align-items: center; gap: 24rpx; padding: 48rpx 12rpx; font-size: 26rpx; color: #a15335; text-align: center; overflow-wrap: anywhere; }
.list-footer { display: flex; flex-direction: column; align-items: center; gap: 24rpx; padding-top: 28rpx; }
.price-notice, .end-note { font-size: 22rpx; color: #929ba6; line-height: 1.5; text-align: center; }
.skeleton-result { padding: 28rpx; margin-bottom: 20rpx; border: 1px solid #e8ebef; border-radius: 8px; background: #fff; }
.skeleton-line, .skeleton-prices view { background: #edf0f3; border-radius: 6rpx; animation: quote-search-pulse 1.4s ease-in-out infinite; }
.skeleton-line { height: 24rpx; width: 65%; margin-bottom: 24rpx; }
.skeleton-line.short { width: 35%; height: 18rpx; }
.skeleton-line.title { height: 32rpx; }
.skeleton-prices { display: flex; gap: 24rpx; padding-top: 16rpx; }
.skeleton-prices view { flex: 1; height: 68rpx; }
@keyframes quote-search-pulse { 50% { opacity: .45; } }
@media (prefers-reduced-motion: reduce) { .skeleton-line, .skeleton-prices view { animation: none; } }
</style>
