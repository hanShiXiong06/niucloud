<template>
    <view class="listing-form">
        <view v-if="definition.description" class="listing-form__notice">
            <view class="listing-form__notice-icon">
                <u-icon name="info-circle" color="#2563eb" size="17" />
            </view>
            <view class="listing-form__notice-copy">
                <text class="listing-form__notice-title">{{ basicPublish ? '上架前，只需完成 3 项' : definition.title }}</text>
                <text class="listing-form__notice-desc">{{ definition.description }}</text>
            </view>
        </view>
        <view v-if="!basicPublish && contract?.mall?.message" class="listing-form__handoff">{{ contract.mall.message }}</view>
        <ErpMallCategorySelect v-if="visible('mall_category_id')" :model-value="Number(modelValue.mall_category_id || 0)"
            :selected-label="modelValue.mall_category_name || ''"
            @change="value => emit('update:modelValue', { ...modelValue, mall_category_id: value.value, mall_category_name: value.label })" />

        <view v-if="visible('retail_price')" class="listing-price-card">
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
        <ErpVoucherUploader
            v-if="visible('image_urls')"
            :model-value="modelValue.image_urls || ''"
            :title="fieldLabel('商品图片', 'image_urls')"
            hint="上传正面、背面、边框和瑕疵图，支持点击预览"
            add-text="上传图片"
            :max-count="9"
            @update:model-value="value => updateField('image_urls', value)"
        />
        <view v-if="basicPublish" class="listing-form__more" @click="showExtras = !showExtras">{{ showExtras ? '收起补充资料' : '补充视频、说明（选填）' }}</view>
        <view v-show="!basicPublish || showExtras">
        <view v-if="visible('video_url')" class="listing-video">
            <view class="listing-video__head">
                <view>
                    <text class="listing-video__title">{{ fieldLabel('展示视频', 'video_url') }}</text>
                    <text class="listing-video__hint">选填，随商品资料同步商城</text>
                </view>
                <text class="listing-video__badge">最多 1 个</text>
            </view>
            <upload-video
                :model-value="modelValue.video_url || ''"
                :max-count="1"
                @update:model-value="value => updateField('video_url', value)"
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
        <view v-if="basicPublish" class="listing-form__handoff">上架后客户即可购买。内存、电池等自动匹配，未匹配项不猜测；商城运营在“资料待办”中继续核对。</view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { salesPricePreview } from '@/addon/hsx_erp/hooks/useSalesPricing'
import ErpCatalogProductPopup from '@/addon/hsx_erp/components/ErpCatalogProductPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import ErpMallCategorySelect from '@/addon/hsx_erp/components/ErpMallCategorySelect.vue'
import { erpListingFieldVisible, erpListingFormDefinition, type ErpListingAction } from '@/addon/hsx_erp/hooks/useErpListingForm'

const props = defineProps<{
    modelValue: Record<string, any>
    contract?: Record<string, any>
    pricing?: Record<string, any>
    action: ErpListingAction
}>()
const emit = defineEmits(['update:modelValue', 'catalog-change'])

const definition = computed(() => erpListingFormDefinition(props.contract, props.action))
const basicPublish = computed(() => Number(definition.value.publish_basic) === 1)
const autoPricing = computed(() => Number(props.pricing?.enabled) === 1)
const hasPrice = computed(() => Number.isFinite(Number(props.modelValue.retail_price)) && Number(props.modelValue.retail_price) > 0)
const pricePreview = computed(() => autoPricing.value && hasPrice.value ? salesPricePreview(props.pricing, props.modelValue.retail_price) : '')
const showExtras = ref(false)
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

function updateField(field: string, value: any) {
    emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function onCatalogChange(payload: any) {
    emit('catalog-change', payload)
}
</script>

<style scoped lang="scss">
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
