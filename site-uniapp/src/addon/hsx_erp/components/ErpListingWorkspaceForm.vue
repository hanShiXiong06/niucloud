<template>
    <view class="listing-form">
        <ErpListingLayout v-if="basicPublish" :model-value="sectionOrder" @update:model-value="setSectionOrder" class="listing-layout-control" />
        <text v-if="basicPublish && storageError" class="listing-layout-error">{{ storageError }}</text>
        <view v-if="!basicPublish && definition.description" class="listing-form__notice">
            <view class="listing-form__notice-icon">
                <u-icon name="info-circle" color="#2563eb" size="17" />
            </view>
            <view class="listing-form__notice-copy">
                <text class="listing-form__notice-title">{{ basicPublish ? '确认分类、价格和商品图片' : definition.title }}</text>
                <text class="listing-form__notice-desc">{{ definition.description }}</text>
            </view>
        </view>
        <view v-if="!basicPublish && contract?.mall?.message" class="listing-form__handoff">{{ contract.mall.message }}</view>
        <view v-if="visible('mall_category_id')" :style="basicPublish ? sectionStyle('category') : undefined">
        <ErpMallCategorySelect :model-value="Number(modelValue.mall_category_id || 0)"
            :selected-label="modelValue.mall_category_name || ''"
            @change="value => basicPublish ? selectCategory(value.value, value.label) : emit('update:modelValue', { ...modelValue, mall_category_id: value.value, mall_category_name: value.label })" />
        <text v-if="categorySuggestionNotice" class="listing-mall-card__hint">{{ categorySuggestionNotice }}</text>
        </view>

        <view v-if="basicPublish" class="listing-mall-card" :style="sectionStyle('details')">
            <view class="listing-mall-card__head"><text>商城规格</text></view>
            <ErpMallBrandSelect :model-value="Number(modelValue.mall_brand_id || 0)" :brands="mallBrands" :disabled="mallLoading || !!mallError" @change="value => selectBrand(value.brand_id, value.brand_name)" />
            <view class="listing-mall-attribute">
                <text class="listing-mall-attribute__label">内存</text>
                <view v-if="memoryOptions.length" class="listing-mall-attribute__select" @click="openAttribute('mall_memory')"><text :class="{ placeholder: !modelValue.mall_memory }">{{ modelValue.mall_memory || '请选择内存' }}</text><u-icon name="arrow-right" size="13" color="#94a3b8" /></view>
                <u-input v-else :model-value="modelValue.mall_memory || ''" border="none" inputAlign="right" maxlength="50" placeholder="填写内存，如 256GB" :disabled="!attributesReady || !modelValue.mall_category_id || !!mallError" @update:model-value="value => changeIdentity({ mall_memory: value })" />
            </view>
            <view class="listing-mall-attribute">
                <text class="listing-mall-attribute__label">颜色</text>
                <view v-if="colorOptions.length" class="listing-mall-attribute__select" @click="openAttribute('mall_color')"><text :class="{ placeholder: !modelValue.mall_color }">{{ modelValue.mall_color || '请选择颜色' }}</text><u-icon name="arrow-right" size="13" color="#94a3b8" /></view>
                <u-input v-else :model-value="modelValue.mall_color || ''" border="none" inputAlign="right" maxlength="50" placeholder="填写颜色，如 银色" :disabled="!attributesReady || !modelValue.mall_category_id || !!mallError" @update:model-value="value => changeIdentity({ mall_color: value })" />
            </view>
            <text v-if="mallLoading" class="listing-mall-card__hint">正在加载本站商城规格…</text>
            <text v-else-if="mallError" class="listing-mall-card__hint listing-mall-card__hint--error" @click="refreshMall">{{ mallError }} · 点击重试</text>
            <text v-else-if="modelValue.mall_category_id && (!memoryOptions.length || !colorOptions.length)" class="listing-mall-card__hint">字典暂无对应项，可填写实际内存、颜色。</text>
            <text v-if="attributeNotice" class="listing-mall-card__hint listing-mall-card__hint--error">{{ attributeNotice }}</text>
            <text v-if="attributesReady && gradeNeedsSelection" class="listing-mall-card__hint listing-mall-card__hint--error" @click="showAttributes = true">原成色等级已失效，请展开商品资料重新选择或清空。</text>
            <text v-if="labelsReady && invalidLabels" class="listing-mall-card__hint listing-mall-card__hint--error" @click="showAttributes = true">原标签已失效，请展开商品资料移除或重选。</text>
            <text v-if="attributesReady && invalidBrand" class="listing-mall-card__hint listing-mall-card__hint--error">原品牌已移除，请重新选择或清空。</text>
            <view class="listing-section-toggle" role="button" :aria-expanded="showAttributes" @click="showAttributes = !showAttributes"><view class="listing-section-toggle__copy"><text class="listing-section-toggle__title">商品资料</text><text class="listing-section-toggle__summary">{{ materialSummary }}</text></view><text class="listing-section-toggle__action">{{ showAttributes ? '收起' : '完善' }}</text><u-icon :name="showAttributes ? 'arrow-up' : 'arrow-down'" size="13" color="#2563eb" /></view>
            <view v-show="showAttributes">
            <text class="listing-mall-card__label">成色等级</text>
            <view v-if="grades.length" class="listing-mall-chips">
                <view v-for="grade in grades" :key="grade.grade_id" class="listing-mall-chips__item" :class="{ 'is-selected': Number(modelValue.mall_grade_id || 0) === grade.grade_id }" @click="selectGrade(grade.grade_id, grade.grade_name)">{{ grade.grade_name }}</view>
            </view>
            <text v-if="!grades.length" class="listing-mall-card__hint">{{ mallLoading ? '正在加载等级…' : '商城暂无等级，可留空。' }}</text>
            <text v-if="attributesReady && gradeNeedsSelection" class="listing-mall-card__hint listing-mall-card__hint--error">{{ modelValue.mall_grade_name ? `原等级“${modelValue.mall_grade_name}”已失效或未唯一匹配` : '原等级已停用或删除' }}，请重新选择。</text>
            <text v-if="attributesReady && gradeNeedsSelection" class="listing-mall-card__hint" @click="selectGrade(0)">清空原等级</text>
            <view v-if="visible('mall_battery_health')" class="listing-mall-warranty">
                <text class="listing-mall-attribute__label">电池健康度</text>
                <view class="listing-mall-battery__input"><u-input :model-value="modelValue.mall_battery_health ?? ''" type="digit" border="none" inputAlign="right" placeholder="未填写" @update:model-value="onBatteryHealthChange" /></view>
                <text class="listing-mall-battery__suffix">%</text>
                <text v-if="modelValue.mall_battery_health !== '' && modelValue.mall_battery_health != null" class="listing-mall-warranty__clear" @click.stop="updateField('mall_battery_health', '')">清空</text>
            </view>
            <template v-if="visible('mall_warranty_date')">
                <view class="listing-mall-warranty">
                    <text class="listing-mall-attribute__label">保修到期</text>
                    <picker class="listing-mall-warranty__picker" mode="date" :value="modelValue.mall_warranty_date || warrantyPickerDefault" start="2000-01-01" end="2099-12-31" @change="onWarrantyDateChange">
                        <view class="listing-mall-attribute__select"><text :class="{ placeholder: !modelValue.mall_warranty_date }">{{ modelValue.mall_warranty_date || '未填写' }}</text><u-icon name="arrow-right" size="13" color="#94a3b8" /></view>
                    </picker>
                    <text v-if="modelValue.mall_warranty_date" class="listing-mall-warranty__clear" @click.stop="updateField('mall_warranty_date', '')">清空</text>
                </view>
            </template>
            <ErpMallLabels :model-value="modelValue.mall_label_ids || []" :labels="mallLabels" :disabled="mallLoading || !labelsReady" @update:model-value="value => updateField('mall_label_ids', value)" />
            </view>
        </view>

        <view v-if="visible('retail_price')" class="listing-price-card" :style="basicPublish ? sectionStyle('price') : undefined">
            <view class="listing-price-card__row">
                <text class="listing-price-card__title">{{ fieldLabel(autoPricing ? '同行基准价' : '零售价', 'retail_price') }}</text>
                <view class="listing-price-card__editor">
                    <text class="listing-price-card__currency">¥</text>
                    <u-input
                        :model-value="modelValue.retail_price || ''"
                        type="digit"
                        placeholder="请输入金额"
                        border="none"
                        inputAlign="right"
                        :custom-style="priceInputStyle"
                        @update:model-value="value => updateField('retail_price', value)"
                    />
                </view>
            </view>
            <text class="listing-price-card__desc">{{ autoPricing ? '填最高等级会员价，其他售价自动计算。' : '普通客户的实际售价，不影响采购成本。' }}</text>
            <view v-if="pricePreview" class="listing-price-card__preview">
                <text class="listing-price-card__preview-label">售价预览</text>
                <text>{{ pricePreview }}</text>
            </view>
        </view>

        <ErpCatalogProductPopup
            v-if="visible('catalog_product_id')"
            :model-value="Number(modelValue.catalog_product_id || 0)"
            :selected-label="catalogDisplayLabel"
            :category-path="modelValue.category_path || ''"
            :label="fieldLabel('商品目录型号', 'catalog_product_id')"
            placeholder="先选品类，再选择品牌、系列和型号"
            :embedded="true"
            :clearable="true"
            @update:model-value="value => updateField('catalog_product_id', Number(value || 0))"
            @change="onCatalogChange"
        />
        <view v-if="visible('spec')" class="listing-form-row">
            <text class="listing-form-row__label">{{ fieldLabel('设备规格', 'spec') }}</text>
            <u-input
                :model-value="modelValue.spec || ''"
                placeholder="容量、颜色、成色、电池等"
                border="none"
                inputAlign="right"
                @update:model-value="value => updateField('spec', value)"
            />
        </view>
        <view :style="basicPublish ? sectionStyle('images') : undefined">
        <view v-if="basicPublish" class="listing-image-mode">
            <text class="listing-image-mode__label">图片方式</text>
            <view class="listing-mall-chips">
                <view class="listing-mall-chips__item" :class="{ 'is-selected': imageMode === 'standard' }" @click="selectMode('standard')">标准图片</view>
                <view class="listing-mall-chips__item" :class="{ 'is-selected': imageMode === 'custom' }" @click="selectMode('custom')">自定义实拍</view>
            </view>
        </view>
        <ErpVoucherUploader
            v-if="visible('image_urls') && (!basicPublish || imageMode === 'custom' || canUploadStandard || standardImages.matched)"
            :key="basicPublish ? imageContextKey : 'plain'"
            :model-value="modelValue.image_urls || ''"
            :title="fieldLabel('商品图片', 'image_urls')"
            :hint="basicPublish ? imageHint : '上传正面、背面、边框和瑕疵图，支持点击预览'"
            :add-text="basicPublish && imageMode === 'standard' ? '上传标准图' : '上传/拍摄'"
            :readonly="basicPublish && imageMode === 'standard' && standardImages.matched"
            :max-count="9"
            @update:model-value="onImagesChange"
        />
        <view v-else-if="visible('image_urls') && basicPublish" class="listing-standard-placeholder" :class="{ 'listing-mall-card__hint--error': !!mallError }">{{ imageHint }}</view>
        </view>
        <view :style="basicPublish ? sectionStyle('extras') : undefined">
        <view v-if="basicPublish" class="listing-section-toggle listing-section-toggle--card" role="button" :aria-expanded="showExtras" @click="showExtras = !showExtras"><view class="listing-section-toggle__copy"><text class="listing-section-toggle__title">视频与说明</text><text class="listing-section-toggle__summary">{{ extrasSummary }}</text></view><text class="listing-section-toggle__action">{{ showExtras ? '收起' : '补充' }}</text><u-icon :name="showExtras ? 'arrow-up' : 'arrow-down'" size="13" color="#2563eb" /></view>
        <view v-show="!basicPublish || showExtras">
        <view v-if="visible('video_url')" class="listing-video">
            <ErpVideoUploader
                :model-value="modelValue.video_url || ''"
                @update:model-value="value => updateField('video_url', value)"
                @busy-change="value => emit('media-busy', value)"
            />
        </view>
        <view v-if="visible('quality_remark')" class="listing-textarea">
            <view class="listing-textarea__head">
                <text>{{ fieldLabel('质检备注', 'quality_remark') }}</text>
                <text class="listing-textarea__badge">随质检报告同步</text>
            </view>
            <u-textarea
                :model-value="modelValue.quality_remark || ''"
                placeholder="补充质检或外观说明"
                :maxlength="500"
                @update:model-value="value => updateField('quality_remark', value)"
            />
        </view>
        <view v-if="visible('remark_public')" class="listing-textarea">
            <view class="listing-textarea__head">
                <text>{{ fieldLabel('对外说明', 'remark_public') }}</text>
                <text class="listing-textarea__badge">客户可见</text>
            </view>
            <u-textarea
                :model-value="modelValue.remark_public || ''"
                placeholder="展示给商城客户的商品说明"
                :maxlength="500"
                @update:model-value="value => updateField('remark_public', value)"
            />
        </view>
        <view v-if="visible('remark_internal')" class="listing-form-row">
            <text class="listing-form-row__label">{{ fieldLabel('对内备注', 'remark_internal') }}</text>
            <u-input
                :model-value="modelValue.remark_internal || ''"
                placeholder="仅员工可见"
                border="none"
                inputAlign="right"
                @update:model-value="value => updateField('remark_internal', value)"
            />
        </view>
        </view>
        </view>
        <view v-if="basicPublish" class="listing-publish-result"><text>上架后客户即可购买</text><text class="listing-publish-result__link" @click="showRules = !showRules">{{ showRules ? '收起规则' : '上架规则' }}</text></view>
        <view v-if="basicPublish && showRules" class="listing-form__handoff">分类、售价、图片及已填商品资料一并同步商城。电池、保修信息显示在副标题；未知项留空。质检报告自动同步，无需重复录入。标准图只复用相同型号、内存和颜色；真实瑕疵请用实拍图。保存不会自动代替运营确认资料。</view>
        <u-popup :show="attributePicker.show" mode="bottom" round="16" :z-index="10120" @close="attributePicker.show = false">
            <view class="listing-attribute-picker">
                <view class="listing-attribute-picker__head"><text>选择{{ attributePicker.field === 'mall_memory' ? '内存' : '颜色' }}</text><u-icon name="close" @click="attributePicker.show = false" /></view>
                <u-search v-model="attributePicker.keyword" :show-action="false" placeholder="输入关键词搜索" />
                <scroll-view scroll-y class="listing-attribute-picker__list">
                    <view v-for="value in attributePickerItems" :key="value" class="listing-attribute-picker__item" @click="chooseAttribute(value)"><text>{{ value }}</text><u-icon v-if="modelValue[attributePicker.field] === value" name="checkmark" color="#2563eb" /></view>
                    <view v-if="!attributePickerItems.length" class="listing-attribute-picker__empty">没有匹配项，请换个关键词</view>
                </scroll-view>
                <view class="listing-attribute-picker__clear" @click="chooseAttribute('')">清空选择</view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { salesPricePreview } from '@/addon/hsx_erp/hooks/useSalesPricing'
import ErpCatalogProductPopup from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import ErpMallCategorySelect from '@/addon/hsx_erp/components/ErpMallCategorySelect.vue'
import ErpMallLabels from '@/addon/hsx_erp/components/ErpMallLabels.vue'
import ErpMallBrandSelect from '@/addon/hsx_erp/components/ErpMallBrandSelect.vue'
import ErpVideoUploader from '@/addon/hsx_erp/components/ErpVideoUploader.vue'
import ErpListingLayout from '@/addon/hsx_erp/components/ErpListingLayout.vue'
import { useErpListingLayout } from '@/addon/hsx_erp/hooks/useErpListingLayout'
import { erpListingFieldVisible, erpListingFormDefinition, type ErpListingAction } from '@/addon/hsx_erp/hooks/useErpListingForm'
import { useErpMallListingMedia } from '@/addon/hsx_erp/hooks/useErpMallListingMedia'

const props = defineProps<{
    modelValue: Record<string, any>
    contract?: Record<string, any>
    pricing?: Record<string, any>
    action: ErpListingAction
}>()
const emit = defineEmits(['update:modelValue', 'catalog-change', 'media-busy'])

const definition = computed(() => erpListingFormDefinition(props.contract, props.action))
const basicPublish = computed(() => Number(definition.value.publish_basic) === 1)
const autoPricing = computed(() => Number(props.pricing?.enabled) === 1)
const hasPrice = computed(() => Number.isFinite(Number(props.modelValue.retail_price)) && Number(props.modelValue.retail_price) > 0)
const pricePreview = computed(() => autoPricing.value && hasPrice.value ? salesPricePreview(props.pricing, props.modelValue.retail_price) : '')
const showExtras = ref(false)
const showAttributes = ref(true)
const showRules = ref(false)
const { sectionOrder, sectionStyle, setSectionOrder, storageError } = useErpListingLayout()
const materialSummary = computed(() => [
    props.modelValue.mall_grade_name,
    props.modelValue.mall_battery_health !== '' && props.modelValue.mall_battery_health != null ? `电池 ${props.modelValue.mall_battery_health}%` : '',
    props.modelValue.mall_warranty_date ? `保修至 ${props.modelValue.mall_warranty_date}` : '',
    Array.isArray(props.modelValue.mall_label_ids) && props.modelValue.mall_label_ids.length ? `${props.modelValue.mall_label_ids.length} 个标签` : '',
].filter(Boolean).join(' · ') || '成色、电池、保修、商城标签，选填')
const extrasSummary = computed(() => [props.modelValue.video_url ? '已传视频' : '', ...['quality_remark', 'remark_public', 'remark_internal'].filter(key => String(props.modelValue[key] || '').trim()).map(key => ({ quality_remark: '质检备注', remark_public: '对外说明', remark_internal: '内部备注' }[key]))].filter(Boolean).join(' · ') || '选填，不影响基本上架')
const priceInputStyle = { fontSize: '30rpx', fontWeight: '600', color: '#0f172a', padding: '0', minWidth: '0' }
const catalogDisplayLabel = computed(() => [
    props.modelValue.category_path,
    props.modelValue.brand_name,
    props.modelValue.series_name,
    props.modelValue.catalog_product_name,
].map(value => String(value || '').trim()).filter(Boolean).join(' / '))
const visible = (field: string) => erpListingFieldVisible(props.contract, props.action, field)
const required = (field: string) => definition.value.required_fields?.includes(field) === true
const fieldLabel = (label: string, field: string) => required(field) ? `${label} *` : label
const { loading: mallLoading, attributesReady, labelsReady, error: mallError, memoryOptions, colorOptions, attributeNotice, grades, mallLabels, mallBrands, categorySuggestionNotice, gradeNeedsSelection, standard: standardImages, mode: imageMode, imageContextKey, canUploadStandard, imageHint, selectMode, selectCategory, selectGrade, selectBrand, changeIdentity, changeImages, refresh: refreshMall } = useErpMallListingMedia(
    () => props.modelValue, () => basicPublish.value,
    patch => emit('update:modelValue', { ...props.modelValue, ...patch }),
)
const invalidLabels = computed(() => (props.modelValue.mall_label_ids || []).some((id: any) => !mallLabels.value.some(item => item.label_id === Number(id))))
const invalidBrand = computed(() => Number(props.modelValue.mall_brand_id || 0) > 0 && !mallBrands.value.some(item => item.brand_id === Number(props.modelValue.mall_brand_id)))
const attributePicker = reactive({ show: false, field: 'mall_memory', keyword: '' })
const attributePickerItems = computed(() => (attributePicker.field === 'mall_memory' ? memoryOptions.value : colorOptions.value)
    .filter(value => value.toLowerCase().includes(attributePicker.keyword.trim().toLowerCase())))
const warrantyPickerDefault = computed(() => {
    const date = new Date()
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
})

function updateField(field: string, value: any) {
    emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function onCatalogChange(payload: any) {
    emit('catalog-change', payload)
}
function openAttribute(field: 'mall_memory' | 'mall_color') {
    attributePicker.field = field; attributePicker.keyword = ''; attributePicker.show = true
}
function chooseAttribute(value: string) {
    changeIdentity({ [attributePicker.field]: value }); attributePicker.show = false
}
function onImagesChange(value: any) {
    if (basicPublish.value) changeImages(value)
    else updateField('image_urls', value)
}
function onWarrantyDateChange(event: any) {
    updateField('mall_warranty_date', String(event?.detail?.value || ''))
}
function onBatteryHealthChange(value: any) {
    const text = String(value ?? '')
    updateField('mall_battery_health', text === '' ? '' : (/^[0-9]+$/.test(text) ? Number(text) : text))
}
</script>

<style scoped lang="scss">
.listing-form{display:flex;flex-direction:column}.listing-layout-control,.listing-layout-error{order:-1}.listing-layout-error{font-size:24rpx;line-height:1.6;color:#b45309}.listing-publish-result,.listing-form__handoff{order:99}
.listing-section-toggle{display:flex;align-items:center;gap:16rpx;min-height:104rpx;padding:20rpx 0;border-top:1rpx solid #e2e8f0;margin-top:20rpx;box-sizing:border-box}.listing-section-toggle--card{padding:20rpx;border:1rpx solid #e2e8f0;border-radius:16rpx;background:#fff}.listing-section-toggle__copy{flex:1;min-width:0}.listing-section-toggle__title{display:block;font-size:28rpx;font-weight:600;color:#334155}.listing-section-toggle__summary{display:block;margin-top:6rpx;font-size:24rpx;color:#64748b;line-height:1.55;word-break:break-word}.listing-section-toggle__action{flex-shrink:0;color:#2563eb;font-size:25rpx}.listing-publish-result{display:flex;align-items:center;justify-content:space-between;gap:16rpx;min-height:88rpx;font-size:24rpx;color:#64748b}.listing-publish-result__link{color:#2563eb;padding:20rpx 0}
.listing-mall-warranty{display:flex;align-items:center;gap:16rpx;min-height:84rpx;border-top:1rpx solid #f1f5f9;margin-top:20rpx}.listing-mall-warranty .listing-mall-attribute__label{width:auto}.listing-mall-warranty__picker,.listing-mall-battery__input{flex:1;min-width:0}.listing-mall-battery__suffix{flex-shrink:0;color:#64748b;font-size:26rpx}.listing-mall-warranty__clear{flex-shrink:0;padding:16rpx 0 16rpx 10rpx;color:#2563eb;font-size:24rpx}
.listing-mall-card,.listing-image-mode{margin:20rpx 0;padding:20rpx;border:1rpx solid #e2e8f0;border-radius:16rpx;background:#fff}.listing-mall-card__head{display:flex;justify-content:space-between;align-items:center;gap:16rpx;color:#334155;font-size:28rpx;font-weight:600}.listing-mall-card__optional{color:#94a3b8;font-size:23rpx;font-weight:400}.listing-mall-card__model{display:block;margin:12rpx 0 6rpx;color:#64748b;font-size:24rpx;line-height:1.5}.listing-mall-attribute{display:flex;align-items:center;gap:20rpx;min-height:86rpx;border-bottom:1rpx solid #f1f5f9}.listing-mall-attribute__label{flex-shrink:0;width:90rpx;font-size:27rpx;color:#475569}.listing-mall-attribute__select{display:flex;justify-content:flex-end;align-items:center;gap:14rpx;flex:1;min-width:0;font-size:27rpx;color:#0f172a}.listing-mall-attribute__select .placeholder{color:#94a3b8}.listing-mall-card__hint{display:block;margin-top:12rpx;font-size:23rpx;line-height:1.6;color:#64748b}.listing-mall-card__hint--error{color:#e45656}.listing-mall-card__label,.listing-image-mode__label{display:block;margin:24rpx 0 14rpx;font-size:27rpx;color:#334155;font-weight:600}.listing-image-mode__label{margin-top:0}.listing-mall-chips{display:flex;flex-wrap:wrap;gap:14rpx}.listing-mall-chips__item{padding:12rpx 22rpx;border:1rpx solid #dce3ed;border-radius:10rpx;background:#fff;color:#475569;font-size:25rpx;line-height:1.5}.listing-mall-chips__item.is-selected{color:#2563eb;border-color:#93b7fa;background:#eff6ff}.listing-standard-placeholder{padding:28rpx;border:1rpx dashed #cbd5e1;border-radius:12rpx;background:#f8fafc;color:#64748b;font-size:24rpx;line-height:1.7}.listing-attribute-picker{padding:24rpx}.listing-attribute-picker__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:24rpx;font-size:30rpx;font-weight:600}.listing-attribute-picker__list{height:44vh;margin-top:20rpx}.listing-attribute-picker__item{display:flex;align-items:center;justify-content:space-between;gap:16rpx;padding:24rpx 8rpx;border-bottom:1rpx solid #f1f5f9;color:#334155;font-size:28rpx}.listing-attribute-picker__empty{padding:36rpx;text-align:center;color:#94a3b8;font-size:25rpx}.listing-attribute-picker__clear{padding:22rpx;text-align:center;color:#2563eb;font-size:26rpx}
.listing-form__more{padding:24rpx 4rpx;color:#2563eb;font-size:26rpx}
.listing-form__handoff{padding:20rpx 4rpx;font-size:24rpx;line-height:1.6;color:#64748b}
.listing-form {
    padding: 0 0 24rpx;
}
.listing-form__notice {
    display: flex;
    align-items: flex-start;
    gap: 16rpx;
    margin: 20rpx 0;
    padding: 20rpx;
    border: 1rpx solid #dbeafe;
    border-radius: 16rpx;
    background: #f5f9ff;
}
.listing-form__notice-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 40rpx;
    height: 40rpx;
    border-radius: 12rpx;
    background: #e8f1ff;
}
.listing-form__notice-copy {
    min-width: 0;
    flex: 1;
}
.listing-form__notice-title,
.listing-form__notice-desc {
    display: block;
}
.listing-form__notice-title {
    color: #334155;
    font-size: 28rpx;
    font-weight: 600;
}
.listing-form__notice-desc {
    margin-top: 6rpx;
    color: #8290a6;
    font-size: 24rpx;
    line-height: 1.55;
}
.listing-form-row {
    display: flex;
    align-items: center;
    min-height: 92rpx;
    gap: 20rpx;
    padding: 0 22rpx;
    border-bottom: 1rpx solid #edf1f6;
    background: #fff;
}
.listing-form-row__label {
    flex: 0 0 180rpx;
    color: #334155;
    font-size: 27rpx;
    font-weight: 500;
}
.listing-price-card {
    margin: 20rpx 0;
    padding: 20rpx;
    border: 1rpx solid #e2e8f0;
    border-radius: 16rpx;
    background: #fff;
}
.listing-price-card__row {
    display: flex;
    align-items: center;
    gap: 20rpx;
}
.listing-price-card__title {
    flex-shrink: 0;
    color: #334155;
    font-size: 28rpx;
    font-weight: 600;
}
.listing-price-card__desc {
    display: block;
    margin-top: 12rpx;
    color: #64748b;
    font-size: 23rpx;
    line-height: 1.5;
}
.listing-price-card__editor {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    min-height: 72rpx;
    padding: 0 16rpx;
    border: 1rpx solid #dcdfe6;
    border-radius: 10rpx;
    background: #fff;
    box-sizing: border-box;
}
.listing-price-card__currency {
    margin-right: 10rpx;
    color: #64748b;
    font-size: 28rpx;
}
.listing-price-card__preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 16rpx;
    margin-top: 16rpx;
    padding-top: 16rpx;
    border-top: 1rpx solid #f1f5f9;
    color: #334155;
    font-size: 24rpx;
    line-height: 1.6;
    word-break: break-word;
}
.listing-price-card__preview-label {
    flex-shrink: 0;
    color: #64748b;
}
.listing-video,
.listing-textarea {
    margin-top: 18rpx;
    padding: 22rpx;
    border: 1rpx solid #e7edf5;
    border-radius: 18rpx;
    background: #fff;
}
.listing-video__head,
.listing-textarea__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    margin-bottom: 18rpx;
}
.listing-video__title,
.listing-textarea__head text {
    color: #334155;
    font-size: 27rpx;
    font-weight: 600;
}
.listing-video__hint {
    display: block;
    margin-top: 5rpx;
    color: #94a3b8;
    font-size: 23rpx;
}
.listing-video__badge,
.listing-textarea__badge {
    color: #94a3b8;
    font-size: 22rpx;
    font-weight: 400;
}
</style>
