<template>
    <view v-if="visible" class="area-query-card" :style="cardStyle">
        <view v-if="showMask" class="component-mask" :style="maskStyle"></view>
        <view class="query-content">
            <view class="query-head">
                <view class="query-mark" :style="{ backgroundColor: accentColor }"></view>
                <view class="head-copy"><view class="query-title" :style="{ color: titleColor }">{{ title }}</view><view v-if="subtitle" class="query-subtitle" :style="{ color: textColor }">{{ subtitle }}</view></view>
                <view class="required-badge">付款前</view>
            </view>

            <view class="field-label" :style="{ color: titleColor }">{{ fieldLabel }}</view>
            <view class="area-field" :class="{ selected: !!areaName }" @click="chooseArea">
                <u-icon name="map-fill" :color="areaName ? '#756500' : '#98a2b3'" size="20" />
                <text :style="{ color: areaName ? titleColor : '#98A2B3' }">{{ areaName || placeholder }}</text>
                <u-icon name="arrow-right" color="#98a2b3" size="15" />
            </view>

            <view v-if="queryResult" class="query-result" :class="queryResult.eligible ? 'is-eligible' : 'is-ineligible'">
                <u-icon :name="queryResult.eligible ? 'checkmark-circle-fill' : 'close-circle-fill'" :color="queryResult.eligible ? '#12b76a' : '#f04438'" size="22" />
                <view><view class="result-title">{{ queryResult.eligible ? '可以参加' : '暂不可参加' }}</view><view class="result-message">{{ queryResult.message }}</view></view>
            </view>

            <view class="query-button" :class="{ disabled: !areaName || querying }" :style="buttonStyle" @click="queryEligibility">
                <u-loading-icon v-if="querying" color="#181818" size="17" />
                <text>{{ querying ? '正在查询' : buttonText }}</text>
            </view>
            <view v-if="showRuleHint && ruleHint" class="rule-hint" :style="{ color: textColor }"><u-icon name="info-circle" :color="textColor" size="13" /><text>{{ ruleHint }}</text></view>
        </view>
        <area-select ref="areaSelectRef" :area-id="deepestAreaId" @complete="areaSelected" />
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import useDiyStore from '@/app/stores/diy'
import AreaSelect from '@/components/area-select/area-select.vue'
import { getProjectCenterAreaEligibility } from '@/addon/hsx_project_center/api'
import { useProjectCenterDiyStyle } from '../useProjectCenterDiyStyle'

const props = defineProps({
    component: { type: Object, default: () => ({}) },
    index: { type: Number, default: 0 },
    projectId: { type: Number, default: 0 },
    initialSelection: { type: Object, default: () => ({}) },
    initialResult: { type: Object, default: () => null }
})
const emit = defineEmits(['update:componentIsShow', 'change', 'result'])
const diyStore = useDiyStore()
const diyComponent = computed<any>(() => diyStore.mode === 'decorate' ? (diyStore.value[props.index] || props.component) : props.component)
const isDecorate = computed(() => diyStore.mode === 'decorate')
const runtimeRule = computed<any>(() => diyComponent.value?.runtimeRule || {})
const runtimeProjectId = computed(() => Number(props.projectId || diyComponent.value?.projectId || 0))
const visible = computed(() => isDecorate.value || Number(runtimeRule.value?.enabled) === 1)
const selected = ref<any>({})
const queryResult = ref<any>(null)
const querying = ref(false), areaSelectRef = ref<any>()

const title = computed(() => String(diyComponent.value?.title || runtimeRule.value?.title || '查询所在地区是否可参与'))
const subtitle = computed(() => String(diyComponent.value?.subtitle || runtimeRule.value?.tips || '选择门店所在省、市、区，立即查看参与资格'))
const fieldLabel = computed(() => String(diyComponent.value?.fieldLabel || '门店所在地区'))
const placeholder = computed(() => String(diyComponent.value?.placeholder || '请选择省 / 市 / 区'))
const buttonText = computed(() => String(diyComponent.value?.buttonText || runtimeRule.value?.button_text || '立即查询'))
const ruleHint = computed(() => String(diyComponent.value?.ruleHint || '实际结果以当前项目后台配置的可参与地区为准'))
const showRuleHint = computed(() => Number(diyComponent.value?.showRuleHint ?? 1) === 1)
const panelColor = computed(() => diyComponent.value?.panelColor || '#FFFFFF')
const titleColor = computed(() => diyComponent.value?.titleColor || '#26334D')
const textColor = computed(() => diyComponent.value?.textColor || '#667085')
const accentColor = computed(() => diyComponent.value?.accentColor || '#FEE502')
const buttonTextColor = computed(() => diyComponent.value?.buttonTextColor || '#181818')
const { cardStyle, showMask, maskStyle } = useProjectCenterDiyStyle(diyComponent, panelColor)
const buttonStyle = computed(() => ({ backgroundColor: accentColor.value, color: buttonTextColor.value }))
const areaName = computed(() => {
    if (isDecorate.value && !selected.value?.province?.name) return '广东省 / 广州市 / 天河区'
    return ['province', 'city', 'district'].map(key => selected.value?.[key]?.name || '').filter(Boolean).join(' / ')
})
const deepestAreaId = computed(() => Number(selected.value?.district?.id || selected.value?.city?.id || selected.value?.province?.id || 0))

function clone(value:any) { try { return JSON.parse(JSON.stringify(value || {})) } catch (_) { return {} } }
function chooseArea() { if (isDecorate.value) return; areaSelectRef.value?.open() }
function areaSelected(value:any) {
    selected.value = clone(value)
    queryResult.value = null
    emit('change', clone(selected.value))
}
async function queryEligibility() {
    if (querying.value) return
    if (isDecorate.value) {
        queryResult.value = { eligible: 1, message: '当前地区可以参加，请继续完成付款。', status: 'eligible' }
        return
    }
    if (!areaName.value) { uni.showToast({ title:'请先选择门店所在地区', icon:'none' }); return }
    if (runtimeProjectId.value <= 0) { uni.showToast({ title:'未识别到当前项目', icon:'none' }); return }
    querying.value = true
    try {
        const res:any = await getProjectCenterAreaEligibility(runtimeProjectId.value, {
            province_id: Number(selected.value?.province?.id || 0),
            city_id: Number(selected.value?.city?.id || 0),
            district_id: Number(selected.value?.district?.id || 0)
        })
        queryResult.value = res.data || null
        emit('result', clone(queryResult.value))
        uni.$emit('project-center-area-eligibility-result', { project_id:runtimeProjectId.value, result:clone(queryResult.value) })
    } catch (error:any) {
        uni.showToast({ title:String(error?.msg || error?.message || '地区资格查询失败'), icon:'none', duration:2600 })
    } finally { querying.value = false }
}

watch(() => props.initialSelection, value => { if (value && Object.keys(value).length) selected.value = clone(value) }, { immediate: true, deep: true })
watch(() => props.initialResult, value => { if (value && Object.keys(value).length) queryResult.value = clone(value) }, { immediate: true, deep: true })
watch(visible, value => emit('update:componentIsShow', value))
onMounted(() => emit('update:componentIsShow', visible.value))
</script>

<style lang="scss" scoped>
.area-query-card{position:relative;overflow:hidden;border:1rpx solid rgba(152,162,179,.2);border-radius:24rpx;box-shadow:0 10rpx 30rpx rgba(34,52,88,.065)}.component-mask{position:absolute;z-index:0;inset:0;pointer-events:none}.query-content{position:relative;z-index:1;padding:25rpx}.query-head{display:flex;align-items:flex-start;gap:12rpx}.query-mark{width:8rpx;height:36rpx;flex:none;border-radius:8rpx}.head-copy{min-width:0;flex:1}.query-title{font-size:30rpx;font-weight:750;line-height:40rpx}.query-subtitle{margin-top:5rpx;font-size:21rpx;line-height:31rpx}.required-badge{flex:none;padding:6rpx 11rpx;border-radius:9rpx;background:#fff7ad;color:#665900;font-size:18rpx;font-weight:700}.field-label{margin-top:24rpx;font-size:23rpx;font-weight:650}.area-field{display:flex;height:82rpx;align-items:center;gap:12rpx;margin-top:10rpx;padding:0 18rpx;border:2rpx solid #e4e7ec;border-radius:16rpx;background:#fbfcfd}.area-field.selected{border-color:#e2d779;background:#fffef4}.area-field text{min-width:0;flex:1;overflow:hidden;font-size:25rpx;text-overflow:ellipsis;white-space:nowrap}.query-button{display:flex;height:78rpx;align-items:center;justify-content:center;gap:10rpx;margin-top:17rpx;border-radius:40rpx;font-size:26rpx;font-weight:750;box-shadow:0 8rpx 18rpx rgba(210,185,0,.2)}.query-button.disabled{opacity:.5;box-shadow:none}.query-result{display:flex;align-items:flex-start;gap:12rpx;margin-top:15rpx;padding:17rpx 18rpx;border:1rpx solid;border-radius:15rpx}.query-result.is-eligible{border-color:#abefc6;background:#ecfdf3;color:#027a48}.query-result.is-ineligible{border-color:#fecdca;background:#fff6f5;color:#b42318}.result-title{font-size:24rpx;font-weight:750}.result-message{margin-top:4rpx;font-size:21rpx;line-height:31rpx}.rule-hint{display:flex;align-items:flex-start;justify-content:center;gap:7rpx;margin-top:13rpx;font-size:18rpx;line-height:28rpx}.rule-hint text{min-width:0}
</style>
