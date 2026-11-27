<template>
  <view class="detail-page">
    <view v-if="loading" class="loading">正在生成报告...</view>

    <block v-else>
      <ReportHeader :brand-name="displayConfig.brand_name" :report="report" />
      <DeviceSummaryCard
        :report="report"
        :show-image="!!displayConfig.detail.show_device_image"
        @copy="copyText"
        @preview="previewImage"
      />
      <ReportFieldList :fields="report.fields" @preview="previewImage" />

      <view class="notice-card">
        <text class="notice-title">报告说明</text>
        <text class="notice-text">{{ displayConfig.support_text }}</text>
      </view>

      <ReportActionBar
        :share-config="displayConfig.share"
        @copy="copyReport"
        @poster="openSharePoster"
      />
      <ReportWatermark :config="displayConfig.watermark" />
      <share-poster
        ref="sharePosterRef"
        posterType="hsx_phone_query_report"
        :posterId="0"
        :posterParam="posterParam"
        copyUrl="/addon/hsx_phone_query/pages/detail"
        :copyUrlParam="detailShareQuery"
      />
    </block>
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShareAppMessage } from '@dcloudio/uni-app'
import { getDisplayConfig, getModelDetail } from '@/addon/hsx_phone_query/api/index'
import sharePoster from '@/components/share-poster/share-poster.vue'
import DeviceSummaryCard from '@/addon/hsx_phone_query/components/report/DeviceSummaryCard.vue'
import ReportActionBar from '@/addon/hsx_phone_query/components/report/ReportActionBar.vue'
import ReportFieldList from '@/addon/hsx_phone_query/components/report/ReportFieldList.vue'
import ReportHeader from '@/addon/hsx_phone_query/components/report/ReportHeader.vue'
import ReportWatermark from '@/addon/hsx_phone_query/components/report/ReportWatermark.vue'
import { buildReportModel, displayFieldValue } from '@/addon/hsx_phone_query/components/report/report-format'
import useMemberStore from '@/stores/member'

const currentId = ref<number>(0)
const loading = ref(true)
const detail = ref<any>({})
const sharePosterRef = ref<any>(null)
const posterParam = ref<any>({})
const memberStore = useMemberStore()

const displayConfig = ref<any>({
  brand_name: '手机查询报告',
  support_text: '查询结果仅供交易验机参考',
  detail: {
    show_device_image: 1,
    show_empty_fields: 0,
    show_raw_result: 0,
    mask_query_code: 0,
  },
  watermark: {
    enabled: 1,
    text: '仅供参考',
    color: 'rgba(18, 24, 38, 0.06)',
    size: 88,
    opacity: 0.72,
    rotate: -24,
  },
  share: {
    enabled: 1,
    poster_enabled: 1,
    title: '设备查询报告',
    subtitle: '长按识别或分享给客户查看',
    footer: '报告由系统自动生成',
    customer_phone: '',
  },
})

const report = computed(() => buildReportModel(detail.value, displayConfig.value))
const inviteMemberId = computed(() => Number(memberStore.info?.member_id || memberStore.info?.id || 0))
const detailShareQuery = computed(() => {
  const params = [`?id=${currentId.value}`]
  if (inviteMemberId.value) params.push(`&invite_member_id=${inviteMemberId.value}`)
  return params.join('')
})

onLoad(async (option: any) => {
  currentId.value = Number(option?.id || 0)
  await Promise.all([loadDisplayConfig(), loadDetail()])
})

async function loadDetail() {
  if (!currentId.value) {
    loading.value = false
    uni.showToast({ title: '参数错误', icon: 'none' })
    return
  }

  try {
    loading.value = true
    const res = await getModelDetail(currentId.value)
    if (res.code === 1) {
      detail.value = res.data || {}
    } else {
      uni.showToast({ title: res.msg || '获取详情失败', icon: 'none' })
    }
  } catch (e) {
    uni.showToast({ title: '获取详情失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

async function loadDisplayConfig() {
  try {
    const res = await getDisplayConfig()
    if (res.code === 1 && res.data) {
      displayConfig.value = mergeConfig(displayConfig.value, res.data)
    }
  } catch (e) {
    // 使用本地默认配置
  }
}

function mergeConfig(base: any, incoming: any) {
  return {
    ...base,
    ...incoming,
    detail: { ...base.detail, ...(incoming.detail || {}) },
    watermark: { ...base.watermark, ...(incoming.watermark || {}) },
    share: { ...base.share, ...(incoming.share || {}) },
  }
}

function copyText(text: string) {
  if (!text) return
  uni.setClipboardData({
    data: text,
    success: () => uni.showToast({ title: '复制成功', icon: 'none' }),
  })
}

function copyReport() {
  const lines = [
    displayConfig.value.brand_name,
    `查询项目：${detail.value?.type_name || ''}`,
    `查询码：${report.value.displayCode}`,
    ...report.value.fields.map((item: any) => `${item.label}：${displayFieldValue(item)}`),
  ]
  copyText(lines.filter(Boolean).join('\n'))
}

function previewImage(url: string) {
  if (!url) return
  uni.previewImage({ urls: [url], current: url })
}

function openSharePoster() {
  const param = {
    result_id: currentId.value,
    invite_member_id: inviteMemberId.value,
  }
  posterParam.value = param
  sharePosterRef.value?.openShare({
    type: 'hsx_phone_query_report',
    param,
  })
}

onShareAppMessage(() => ({
  title: `${displayConfig.value.share.title || '设备查询报告'}-${report.value.title}`,
  path: `/addon/hsx_phone_query/pages/detail${detailShareQuery.value}`,
  imageUrl: report.value.image || '',
}))
</script>

<style lang="scss" scoped>
.detail-page {
  min-height: 100vh;
  background: #f6f8fb;
  padding-bottom: 24rpx;
}

.loading {
  padding-top: 240rpx;
  text-align: center;
  font-size: 28rpx;
  color: #667085;
}

.notice-card {
  margin: 0 24rpx 24rpx;
  padding: 24rpx 26rpx;
  background: #fff;
  border-radius: 14rpx;
  box-shadow: 0 10rpx 28rpx rgba(17, 24, 39, 0.05);
}

.notice-title {
  display: block;
  font-size: 28rpx;
  font-weight: 700;
  color: #111827;
  margin-bottom: 10rpx;
}

.notice-text {
  display: block;
  font-size: 24rpx;
  color: #667085;
  line-height: 36rpx;
}
</style>
