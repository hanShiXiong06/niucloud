<template>
    <view v-if="rows.length || remarkText" class="rcs-wrap">
        <!-- 质检员手填备注：着重显示（橙色块），模板覆盖不到的关键补充信息 -->
        <view v-if="remarkText" class="rcs-remark">
            <view class="rcs-remark__head">
                <u-icon name="edit-pen-fill" color="#fa5c1e" size="14"></u-icon>
                <text class="rcs-remark__label">质检备注</text>
            </view>
            <text class="rcs-remark__text">{{ remarkText }}</text>
        </view>
        <view v-if="rows.length" class="rcs">
            <view
                v-for="(it, i) in rows"
                :key="i"
                class="rcs__item"
                :class="{ 'rcs__item--bad': it.abnormal }"
            >
                <text class="rcs__label">{{ it.label }}</text>
                <text class="rcs__value">{{ it.value }}</text>
            </view>
        </view>
    </view>
    
    <text v-else-if="emptyText" class="rcs__empty">{{ emptyText }}</text>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 质检结果（JSON）统一展示：两列、紧凑，label 左 / value 右，各自超宽折行，异常项高亮。
 * 数据来源二选一：
 *   - items：父级已构建好的 [{label, value, abnormal?}]
 *   - meta ：原始 check_meta（含 result_items），可选 labelMap 把选项 value→label
 */
const props = withDefaults(defineProps<{
    items?: Array<{ label: string, value: any, abnormal?: boolean }>
    meta?: Record<string, any>
    labelMap?: Record<string, Record<string, string>>
    emptyText?: string
    remark?: string
}>(), {
    items: undefined,
    meta: () => ({}),
    labelMap: () => ({}),
    emptyText: '',
    remark: ''
})

const remarkText = computed(() => String(props.remark || '').trim())

const isOn = (v: any) => v === true || v === 1 || v === '1' || v === 'true' || v === '开启'

const resolve = (fieldKey: string, v: any) => {
    const m = props.labelMap[fieldKey]
    return (m && m[String(v)]) || String(v)
}

const formatValue = (item: any): string => {
    if (item?.component === 'switch') return isOn(item.value) ? '是' : '否'
    if (Array.isArray(item?.labels) && item.labels.length) {
        return item.labels.map((l: any) => String(l)).filter((l: string) => l && l !== 'true' && l !== 'false').join('、')
    }
    if (Array.isArray(item?.option_items) && item.option_items.length) {
        return item.option_items.map((o: any) => o.label || o.name || o.value).filter(Boolean).join('、')
    }
    if (Array.isArray(item?.value)) return item.value.filter(Boolean).map((v: any) => resolve(item.field_key, v)).join('、')
    if (item?.value !== undefined && item?.value !== null && item?.value !== '') return resolve(item.field_key, item.value)
    return item?.text || ''
}

// 异常判定：优先用显式标记 / 选项 is_default 不匹配；否则保守关键词（避免"无维修"等误判）
const GOOD_RE = /正常|完美|无维修|无进水|无拆|未拆|健康|已激活|可还原|已注销|可注销|100\s*%|^无|^未/
const BAD_RE = /划痕|裂|碎|缺失|损坏|故障|弯曲|进水|老化|亮点|有维修|已拆|改装|异常|发黄|变色|不开机|花屏|黑屏/
const detectAbnormal = (item: any, valueText: string): boolean => {
    if (typeof item?.abnormal === 'boolean') return item.abnormal
    const opts = Array.isArray(item?.option_items) ? item.option_items : (Array.isArray(item?.options) ? item.options : [])
    const defs = opts.filter((o: any) => Number(o?.is_default) === 1).map((o: any) => String(o.value))
    if (defs.length && item?.value !== undefined && item?.value !== null && item?.value !== '') {
        const vals = Array.isArray(item.value) ? item.value.map(String) : [String(item.value)]
        return vals.some((v: string) => !defs.includes(v))
    }
    if (!valueText) return false
    if (GOOD_RE.test(valueText)) return false
    return BAD_RE.test(valueText)
}

const rows = computed<Array<{ label: string, value: string, abnormal: boolean }>>(() => {
    if (Array.isArray(props.items)) {
        return props.items
            .map((it: any) => {
                const value = typeof it.value === 'object' ? JSON.stringify(it.value) : String(it.value ?? '')
                return { label: String(it.label || ''), value, abnormal: typeof it.abnormal === 'boolean' ? it.abnormal : detectAbnormal(it, value) }
            })
            .filter((it) => it.value !== '' && it.value !== 'null' && it.value !== 'undefined')
    }
    const meta = props.meta || {}
    const resultItems = Array.isArray(meta.result_items) ? meta.result_items : []
    return resultItems
        .map((item: any) => {
            const value = formatValue(item)
            return { label: String(item.field_name || item.field_key || '质检项'), value, abnormal: detectAbnormal(item, value) }
        })
        .filter((it: any) => it.value !== '' && it.value !== undefined && it.value !== null)
})
</script>

<style scoped lang="scss">
.rcs-wrap {
    width: 100%;
}
/* 质检备注：橙色弱底 + 笔形图标，着重突出人工补充信息 */
.rcs-remark {
    margin: 14rpx 0 ;
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
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 24rpx;
    row-gap: 12rpx;
    /* 浅底 + 边框，让质检两列与上方的内存/颜色/保修标签拉开层次 */
    padding: 18rpx 20rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    border: 1rpx solid #eef2f7;
}
.rcs__item {
    display: flex;
    align-items: flex-start;
    gap: 10rpx;
    font-size: 24rpx;
    line-height: 1.45;
    min-width: 0;
}
.rcs__label {
    flex: 0 0 40%;
    color: #94a3b8;
    text-align: left;
    word-break: break-word;
}
.rcs__value {
    flex: 1 1 60%;
    min-width: 0;
    text-align: right;
    color: #334155;
    word-break: break-word;
}
.rcs__item--bad .rcs__label,
.rcs__item--bad .rcs__value {
    color: #fa5c1e;
    font-weight: 600;
}
.rcs__empty {
    font-size: 24rpx;
    color: #b0b3b8;
}
</style>
