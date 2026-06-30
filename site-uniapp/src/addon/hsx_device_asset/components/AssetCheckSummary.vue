<template>
    <view class="acs">
        <view class="acs__head">
            <text class="acs__title">{{ title }}</text>
            <view class="acs__badge" :class="abnormalRows.length ? 'is-bad' : 'is-ok'">
                <u-icon
                    :name="abnormalRows.length ? 'error-circle-fill' : 'checkmark-circle-fill'"
                    :color="abnormalRows.length ? '#fa5c1e' : '#22c55e'"
                    size="14"
                ></u-icon>
                <text>{{ abnormalRows.length ? `${abnormalRows.length} 项需关注` : '检测项全部正常' }}</text>
            </view>
        </view>

        <!-- 异常常驻 + 展开后接正常项，统一在一个浅底块里（参考回收插件，分隔更干净） -->
        <view v-if="displayRows.length" class="acs__body">
            <view class="acs__grid">
                <view
                    v-for="(it, i) in displayRows"
                    :key="i"
                    class="acs__item"
                    :class="{ 'acs__item--bad': it.abnormal }"
                >
                    <text class="acs__label">{{ it.label }}</text>
                    <text class="acs__value">{{ it.value }}</text>
                    <view v-if="editable" class="acs__del" @click.stop="hide(it.label)">
                        <u-icon name="close" color="#bbb" size="13"></u-icon>
                    </view>
                </view>
            </view>
        </view>

        <view v-if="normalRows.length" class="acs__toggle" @click="expanded = !expanded">
            <text>{{ expanded ? '收起正常项' : `展开正常项（${normalRows.length}）` }}</text>
            <u-icon :name="expanded ? 'arrow-up' : 'arrow-down'" color="#3c9cff" size="13"></u-icon>
        </view>

        <!-- 删减模式:已隐藏项(买家看不到),可点击恢复 -->
        <view v-if="editable && hiddenRows.length" class="acs__hidden">
            <text class="acs__hidden-tip">已隐藏 {{ hiddenRows.length }} 项(买家不可见),点可恢复：</text>
            <view class="acs__hidden-tags">
                <view v-for="(it, i) in hiddenRows" :key="'h' + i" class="acs__hidden-tag" @click="unhide(it.label)">
                    <text>{{ it.label }}</text>
                    <u-icon name="reload" color="#3c9cff" size="11"></u-icon>
                </view>
            </view>
        </view>

        <text v-if="!abnormalRows.length && !normalRows.length && !hiddenRows.length" class="acs__empty">{{ emptyText }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

/**
 * 质检结论展示（独立组件）。关键/异常项常驻高亮，正常项默认折叠，避免客户点来点去。
 * 数据来源二选一：
 *   - items：父级已构建好的 [{label, value, abnormal?}]
 *   - text ：原始质检字符串，形如「网络制式：全网通;\n屏幕显示：显示无明显缺陷;...」自动解析成行
 * 风格参考 hsx_recycle/RecycleCheckSummary，异常色统一 #fa5c1e。
 */
const props = withDefaults(defineProps<{
    title?: string
    items?: Array<{ label: string, value: any, abnormal?: boolean }>
    text?: string
    emptyText?: string
    // 定价员删减模式:开启后每项可×隐藏(只影响买家版,不动原始报告)
    editable?: boolean
    // 已隐藏的字段名(v-model:hiddenKeys)
    hiddenKeys?: string[]
}>(), {
    title: '质检结论',
    items: undefined,
    text: '',
    emptyText: '暂无质检信息',
    editable: false,
    hiddenKeys: () => []
})

const emit = defineEmits<{ (e: 'update:hiddenKeys', v: string[]): void }>()

const expanded = ref(false)
const hiddenSet = computed(() => new Set((props.hiddenKeys || []).map(k => String(k))))
const hide = (label: string) => {
    if (!label) return
    const next = Array.from(new Set([...(props.hiddenKeys || []).map(String), label]))
    emit('update:hiddenKeys', next)
}
const unhide = (label: string) => {
    emit('update:hiddenKeys', (props.hiddenKeys || []).map(String).filter(k => k !== label))
}

// 异常判定：保守关键词，避免「无维修 / 未拆」等被误判
const GOOD_RE = /正常|完美|无维修|无进水|无拆|未拆|健康|已激活|可还原|已注销|可注销|完好|无明显|100\s*%|^无|^未/
const BAD_RE = /划痕|裂|碎|缺失|损坏|故障|弯曲|进水|老化|亮点|有维修|已拆|改装|异常|发黄|变色|不开机|花屏|黑屏|磕碰|掉漆|维修|更换/
const detectAbnormal = (value: string, explicit?: boolean): boolean => {
    if (typeof explicit === 'boolean') return explicit
    if (!value) return false
    if (GOOD_RE.test(value)) return false
    return BAD_RE.test(value)
}

// 解析「label：value;\nlabel：value」字符串
const parseText = (raw: string): Array<{ label: string, value: string }> => {
    return String(raw || '')
        .split(/[;\n]+/)
        .map(seg => seg.trim())
        .filter(Boolean)
        .map(seg => {
            const m = seg.split(/[：:]/)
            if (m.length >= 2) {
                return { label: m[0].trim(), value: m.slice(1).join('：').trim() }
            }
            return { label: '', value: seg }
        })
        .filter(it => it.value !== '')
}

// 值本身就是「字段：值;字段：值」整段（如 卖家质检 把整段塞进一个 value）→ 拆成多行，丢掉外层包装 label
const expandStructured = (items: Array<{ label: string, value: string, abnormal?: boolean }>) => {
    const out: Array<{ label: string, value: string, abnormal?: boolean }> = []
    items.forEach(it => {
        const v = String(it.value ?? '')
        const parsed = parseText(v)
        if (parsed.length >= 2 && parsed.every(p => p.label)) {
            out.push(...parsed)
        } else {
            out.push(it)
        }
    })
    return out
}

const rows = computed<Array<{ label: string, value: string, abnormal: boolean }>>(() => {
    let base: Array<{ label: string, value: string, abnormal?: boolean }> = []
    if (Array.isArray(props.items) && props.items.length) {
        base = props.items.map(it => ({
            label: String(it.label || ''),
            value: typeof it.value === 'object' ? JSON.stringify(it.value) : String(it.value ?? ''),
            abnormal: it.abnormal
        }))
    } else if (props.text) {
        base = parseText(props.text)
    }
    return expandStructured(base)
        .filter(it => it.value !== '' && it.value !== 'null' && it.value !== 'undefined')
        .map(it => ({ label: it.label, value: it.value, abnormal: detectAbnormal(it.value, it.abnormal) }))
})

// 可见行 = 全部行剔除已隐藏(隐藏只影响展示与买家版)
const visibleRows = computed(() => rows.value.filter(r => !hiddenSet.value.has(r.label)))
// 已隐藏行(供恢复)
const hiddenRows = computed(() => rows.value.filter(r => hiddenSet.value.has(r.label)))
const abnormalRows = computed(() => visibleRows.value.filter(r => r.abnormal))
const normalRows = computed(() => visibleRows.value.filter(r => !r.abnormal))
// 异常常驻在前；展开后再接上正常项，合并到同一个块内渲染
const displayRows = computed(() => expanded.value ? [...abnormalRows.value, ...normalRows.value] : abnormalRows.value)
</script>

<style scoped lang="scss">
.acs {
    width: 100%;
}
.acs__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
}
.acs__title {
    color: #0f172a;
    font-size: 28rpx;
    font-weight: 700;
}
.acs__badge {
    display: flex;
    align-items: center;
    gap: 6rpx;
    padding: 6rpx 16rpx;
    border-radius: 999rpx;
    font-size: 22rpx;
}
.acs__badge.is-bad {
    color: #fa5c1e;
    background: #fff2e9;
}
.acs__badge.is-ok {
    color: #16a34a;
    background: #f0fdf4;
}
/* 浅底整块（参考回收插件 RecycleCheckSummary），异常+正常统一在内，分隔干净 */
.acs__body {
    padding: 18rpx 20rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    border: 1rpx solid #eef2f7;
}
.acs__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 24rpx;
    row-gap: 14rpx;
}
.acs__item {
    display: flex;
    align-items: flex-start;
    gap: 10rpx;
    font-size: 24rpx;
    line-height: 1.45;
    min-width: 0;
}
.acs__label {
    flex: 0 0 40%;
    color: #94a3b8;
    word-break: break-word;
}
.acs__value {
    flex: 1 1 60%;
    min-width: 0;
    text-align: right;
    color: #334155;
    word-break: break-word;
}
.acs__item--bad .acs__label,
.acs__item--bad .acs__value {
    color: #fa5c1e;
    font-weight: 600;
}
.acs__toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    margin-top: 14rpx;
    color: #3c9cff;
    font-size: 23rpx;
}
.acs__empty {
    font-size: 24rpx;
    color: #b0b3b8;
}
/* 删减模式:每项右侧的 × */
.acs__del {
    flex: 0 0 auto;
    margin-left: 8rpx;
    width: 34rpx;
    height: 34rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f2f3f5;
}
/* 已隐藏项区域 */
.acs__hidden {
    margin-top: 16rpx;
    padding-top: 12rpx;
    border-top: 1rpx dashed #e2e8f0;
}
.acs__hidden-tip {
    font-size: 22rpx;
    color: #909399;
}
.acs__hidden-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10rpx;
    margin-top: 10rpx;
}
.acs__hidden-tag {
    display: flex;
    align-items: center;
    gap: 4rpx;
    padding: 4rpx 14rpx;
    border-radius: 24rpx;
    background: #eef5ff;
    color: #3c9cff;
    font-size: 22rpx;
}
</style>
