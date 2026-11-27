<template>
    <view v-if="rows.length || remarkText" class="rcs-wrap">
        <!-- 质检员手填备注：橙色块着重显示 -->
        <view v-if="remarkText" class="rcs-remark">
            <view class="rcs-remark__head">
                <u-icon name="edit-pen-fill" color="#fa5c1e" size="14"></u-icon>
                <text class="rcs-remark__label">质检备注</text>
            </view>
            <text class="rcs-remark__text">{{ remarkText }}</text>
        </view>

        <view v-if="rows.length" class="rcs">
            <!-- 异常/注意项：始终展开、置顶、彩色标识(异常红 / 注意橙) -->
            <view v-for="(it, i) in flaggedRows" :key="'f' + i" class="rcs__line">
                <text class="rcs__name">{{ it.label }}</text>
                <view class="rcs__tag" :class="it.severity === 'abnormal' ? 'rcs__tag--bad' : 'rcs__tag--warn'">
                    <text class="rcs__tag-flag">{{ it.severity === 'abnormal' ? '异常' : '注意' }}</text>
                    <text class="rcs__tag-text">{{ it.value }}</text>
                </view>
            </view>

            <!-- 正常项：折叠，默认只显前 N 个 -->
            <view v-for="(it, i) in visibleNormalRows" :key="'n' + i" class="rcs__line">
                <text class="rcs__name">{{ it.label }}</text>
                <view class="rcs__tag rcs__tag--ok">
                    <text class="rcs__tag-text">{{ it.value }}</text>
                </view>
            </view>
            <view v-if="normalRows.length > normalLimit" class="rcs__toggle" @click="expanded = !expanded">
                <text>{{ expanded ? '收起正常项' : `展开其余 ${normalRows.length - normalLimit} 项正常` }}</text>
                <u-icon :name="expanded ? 'arrow-up' : 'arrow-down'" color="#909399" size="13"></u-icon>
            </view>
        </view>
    </view>

    <text v-else-if="emptyText" class="rcs__empty">{{ emptyText }}</text>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

/**
 * 质检结果统一展示(自封装):
 *   - 异常 / 注意项置顶并彩色标识(异常红、注意橙),正常项折叠
 *   - value 一律解析成中文 label(优先 option_items / labels,绝不显示选项 ID)
 *   - 异常级别(severity)完全以后端为准(字典唯一事实源),前端不判定
 * 输入二选一:
 *   - meta ：原始 check_meta(含 result_items,后端已注入 severity / option_items)【推荐】
 *   - items：父级已构建好的 [{label, value, severity?}](兼容旧用法)
 */
const props = withDefaults(defineProps<{
    meta?: Record<string, any>
    items?: Array<{ label: string, value: any, severity?: string, abnormal?: boolean }>
    labelMap?: Record<string, Record<string, string>>
    emptyText?: string
    remark?: string
    normalLimit?: number
}>(), {
    meta: () => ({}),
    items: undefined,
    labelMap: () => ({}),
    emptyText: '',
    remark: '',
    normalLimit: 2
})

const expanded = ref(false)
const remarkText = computed(() => String(props.remark || '').trim())

const isOn = (v: any) => v === true || v === 1 || v === '1' || v === 'true' || v === '开启'
const SEV_RANK: Record<string, number> = { abnormal: 0, general: 1, normal: 2 }

// value → 中文 label:优先用后端注入的 option_items/labels,其次 labelMap,最后才回退原值
const resolveLabel = (fieldKey: string, v: any) => {
    const m = props.labelMap[fieldKey]
    return (m && m[String(v)]) || String(v)
}
const formatValue = (item: any): string => {
    if (item?.component === 'switch') return isOn(item.value) ? '是' : '否'
    if (Array.isArray(item?.option_items) && item.option_items.length) {
        return item.option_items.map((o: any) => o.label || o.name || o.value).filter(Boolean).join('、')
    }
    if (Array.isArray(item?.labels) && item.labels.length) {
        return item.labels.map((l: any) => String(l)).filter((l: string) => l && l !== 'true' && l !== 'false').join('、')
    }
    const vals = Array.isArray(item?.value)
        ? item.value
        : (item?.value !== undefined && item?.value !== null && item?.value !== '' ? [item.value] : [])
    if (vals.length) return vals.map((v: any) => resolveLabel(item.field_key, v)).filter(Boolean).join('、')
    return String(item?.text || '')
}

// severity 取后端值;无则由选项 severity 推定;再无则 normal
const detectSeverity = (item: any): string => {
    const direct = String(item?.severity || '')
    if (direct === 'abnormal' || direct === 'general' || direct === 'normal') return direct
    if (typeof item?.abnormal === 'boolean') return item.abnormal ? 'abnormal' : 'normal'
    const opts = Array.isArray(item?.option_items) ? item.option_items
        : (Array.isArray(item?.options) ? item.options : [])
    let worst = 'normal'
    for (const o of opts) {
        const s = String(o?.severity || 'normal')
        if ((SEV_RANK[s] ?? 9) < (SEV_RANK[worst] ?? 9)) worst = s
    }
    return worst
}

const rows = computed<Array<{ label: string, value: string, severity: string }>>(() => {
    let list: Array<{ label: string, value: string, severity: string }> = []
    if (Array.isArray(props.items)) {
        list = props.items.map((it: any) => ({
            label: String(it.label || ''),
            value: typeof it.value === 'object' ? formatValue(it) : String(it.value ?? ''),
            severity: detectSeverity(it)
        }))
    } else {
        const meta = props.meta || {}
        const resultItems = Array.isArray(meta.result_items) ? meta.result_items : []
        list = resultItems.map((item: any) => ({
            label: String(item.field_name || item.field_key || '质检项'),
            value: formatValue(item),
            severity: detectSeverity(item)
        }))
    }
    return list.filter((it) => it.value !== '' && it.value !== 'null' && it.value !== 'undefined')
})

// 异常在前(abnormal → general),正常单独折叠
const flaggedRows = computed(() =>
    rows.value
        .filter((r) => r.severity !== 'normal')
        .sort((a, b) => (SEV_RANK[a.severity] ?? 9) - (SEV_RANK[b.severity] ?? 9))
)
const normalRows = computed(() => rows.value.filter((r) => r.severity === 'normal'))
const visibleNormalRows = computed(() => expanded.value ? normalRows.value : normalRows.value.slice(0, props.normalLimit))
</script>

<style scoped lang="scss">
.rcs-wrap {
    width: 100%;
    margin-top: 10rpx;
}
.rcs-remark {
    margin: 14rpx 0;
    padding: 16rpx 18rpx;
    border-radius: 14rpx;
    background: #fff7f2;
    border: 1rpx solid #ffe0cf;
}
.rcs-remark__head {
    display: flex;
    align-items: center;
    gap: 8rpx;
    margin-bottom: 8rpx;
}
.rcs-remark__label {
    color: #fa5c1e;
    font-size: 23rpx;
    font-weight: 700;
}
.rcs-remark__text {
    display: block;
    color: #5b4636;
    font-size: 24rpx;
    line-height: 1.55;
    word-break: break-word;
}
.rcs {
    padding: 16rpx 20rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    border: 1rpx solid #eef2f7;
}
.rcs__line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    padding: 8rpx 0;
    font-size: 24rpx;
    line-height: 1.4;
    min-width: 0;
}
.rcs__line + .rcs__line {
    border-top: 1rpx solid #eef2f7;
}
.rcs__name {
    flex: 0 0 auto;
    color: #94a3b8;
    word-break: break-word;
}
.rcs__tag {
    flex: 0 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 4rpx 12rpx;
    border-radius: 8rpx;
    text-align: right;
}
.rcs__tag-text {
    min-width: 0;
    word-break: break-word;
    font-size: 23rpx;
}
.rcs__tag-flag {
    flex: 0 0 auto;
    font-size: 19rpx;
    font-weight: 700;
    padding: 2rpx 8rpx;
    border-radius: 6rpx;
    color: #fff;
}
/* 异常=红 */
.rcs__tag--bad {
    background: #fef0ef;
}
.rcs__tag--bad .rcs__tag-text { color: #f5483b; font-weight: 600; }
.rcs__tag--bad .rcs__tag-flag { background: #f5483b; }
/* 注意=橙 */
.rcs__tag--warn {
    background: #fff5eb;
}
.rcs__tag--warn .rcs__tag-text { color: #d46b08; font-weight: 600; }
.rcs__tag--warn .rcs__tag-flag { background: #fa8c16; }
/* 正常=绿弱底 */
.rcs__tag--ok {
    background: #f0f9f0;
}
.rcs__tag--ok .rcs__tag-text { color: #52854a; }
.rcs__toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6rpx;
    margin-top: 10rpx;
    padding-top: 10rpx;
    border-top: 1rpx dashed #e2e8f0;
    color: #909399;
    font-size: 22rpx;
}
.rcs__empty {
    font-size: 24rpx;
    color: #b0b3b8;
}
</style>
