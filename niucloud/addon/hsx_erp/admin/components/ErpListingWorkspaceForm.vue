<template>
    <div class="listing-form">
        <div v-if="definition.description" class="listing-form__notice">
            <div class="listing-form__notice-icon">i</div>
            <div>
                <strong>{{ basicPublish ? '上架前，只需完成 3 项' : definition.title }}</strong>
                <span>{{ definition.description }}</span>
            </div>
        </div>
        <div v-if="!basicPublish && contract?.mall?.message" class="listing-form__hint">{{ contract.mall.message }}</div>

        <el-form-item v-if="visible('mall_category_id')" label="商城分类" required>
            <ErpMallCategorySelect :model-value="Number(modelValue.mall_category_id || 0)" @update:model-value="value => updateField('mall_category_id', value)" />
        </el-form-item>

        <el-form-item v-if="visible('retail_price')" :required="required('retail_price')" :label="autoPricing ? '同行基准价' : '零售价'" class="listing-price-field">
            <div class="listing-price-field__body">
                <div class="listing-price-field__control">
                    <span class="listing-price-field__currency" aria-hidden="true">¥</span>
                    <el-input-number
                        :model-value="Number(modelValue.retail_price || 0)"
                        :min="0"
                        :precision="2"
                        :controls="false"
                        class="listing-price-field__input"
                        placeholder="请输入金额"
                        @update:model-value="value => updateField('retail_price', Number(value || 0))"
                    />
                </div>
                <div class="listing-price-field__hint">{{ autoPricing ? '填最高等级会员价，其他售价自动计算。' : '普通客户的实际售价，不影响采购成本。' }}</div>
                <div v-if="pricePreview" class="listing-price-field__preview" aria-live="polite">
                    <span class="listing-price-field__preview-label">售价预览</span>
                    <span>{{ pricePreview }}</span>
                </div>
            </div>
        </el-form-item>

        <div class="listing-form__grid">
            <el-form-item v-if="visible('catalog_product_id')" label="商品型号" :required="required('catalog_product_id')">
                <ErpCatalogProductSelect
                    :model-value="Number(modelValue.catalog_product_id || 0)"
                    placeholder="搜索并选择目录型号"
                    @update:model-value="value => updateField('catalog_product_id', Number(value || 0))"
                    @change="onCatalogChange"
                />
            </el-form-item>
            <el-form-item v-if="visible('spec')" label="设备规格" :required="required('spec')">
                <el-input
                    :model-value="modelValue.spec || ''"
                    placeholder="容量、颜色、成色、电池等"
                    @update:model-value="value => updateField('spec', value)"
                />
            </el-form-item>
        </div>

        <el-form-item v-if="visible('image_urls')" label="商品图片" :required="required('image_urls')">
            <div class="w-full">
                <upload-image
                    :model-value="modelValue.image_urls || ''"
                    :limit="9"
                    width="72px"
                    height="72px"
                    image-text="上传/选择"
                    @update:model-value="value => updateField('image_urls', value)"
                />
                <div class="listing-form__hint">第一张作为封面；请包含正面、背面及真实瑕疵图，避免遗漏影响购买的信息。</div>
            </div>
        </el-form-item>
        <el-button v-if="basicPublish" link type="primary" class="listing-form__more" @click="showExtras = !showExtras">{{ showExtras ? '收起补充资料' : '补充视频、说明（选填）' }}</el-button>
        <div v-show="!basicPublish || showExtras">
        <el-form-item v-if="visible('video_url')" label="展示视频" :required="required('video_url')">
            <div class="w-full">
                <upload-video
                    :model-value="modelValue.video_url || ''"
                    :limit="1"
                    @update:model-value="value => updateField('video_url', value)"
                />
                <div class="listing-form__hint">选填，最多 1 个视频。</div>
            </div>
        </el-form-item>
        <el-form-item v-if="visible('quality_remark')" label="质检备注" :required="required('quality_remark')">
            <el-input
                :model-value="modelValue.quality_remark || ''"
                type="textarea"
                :rows="2"
                placeholder="补充质检、外观情况；将随质检报告同步商城"
                @update:model-value="value => updateField('quality_remark', value)"
            />
        </el-form-item>
        <el-form-item v-if="visible('remark_public')" label="对外说明" :required="required('remark_public')">
            <el-input
                :model-value="modelValue.remark_public || ''"
                type="textarea"
                :rows="2"
                placeholder="展示给商城客户的商品说明"
                @update:model-value="value => updateField('remark_public', value)"
            />
        </el-form-item>
        <el-form-item v-if="visible('remark_internal')" label="对内备注" :required="required('remark_internal')">
            <el-input
                :model-value="modelValue.remark_internal || ''"
                placeholder="仅员工可见，不同步到商城"
                @update:model-value="value => updateField('remark_internal', value)"
            />
        </el-form-item>
        </div>
        <div v-if="basicPublish" class="listing-form__handoff">上架 ≠ 资料已核对。已知属性和质检报告会同步；未匹配项留给商城运营，在“资料待办”中核对。</div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { salesPricePreview } from '@/addon/hsx_erp/hooks/useSalesPricing'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
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
const visible = (field: string) => erpListingFieldVisible(props.contract, props.action, field)
const required = (field: string) => definition.value.required_fields?.includes(field) === true

function updateField(field: string, value: any) {
    emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function onCatalogChange(payload: any) {
    emit('catalog-change', payload)
}
</script>

<style scoped>
.listing-form__notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 14px 0;
    padding: 12px 14px;
    border: 1px solid #dbe8ff;
    border-radius: 10px;
    background: #f6f9ff;
}
.listing-form__notice-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 20px;
    height: 20px;
    border-radius: 50%;
    color: #fff;
    background: var(--el-color-primary);
    font-size: 12px;
    font-weight: 700;
}
.listing-form__notice strong,
.listing-form__notice span {
    display: block;
}
.listing-form__notice strong {
    color: #334155;
    font-size: 14px;
}
.listing-form__notice span {
    margin-top: 3px;
    color: #7c8ba1;
    font-size: 12px;
    line-height: 1.55;
}
.listing-form__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
}
.listing-price-field {
    margin-bottom: 20px;
}
.listing-price-field :deep(.el-form-item__label) {
    line-height: 38px;
    white-space: nowrap;
}
.listing-price-field__body {
    width: 100%;
    min-width: 0;
}
.listing-price-field__control {
    display: flex;
    align-items: center;
    box-sizing: border-box;
    width: 280px;
    max-width: 100%;
    min-height: 38px;
    padding-left: 12px;
    border: 1px solid var(--el-border-color);
    border-radius: 6px;
    background: #fff;
}
.listing-price-field__control:focus-within {
    border-color: var(--el-color-primary);
}
.listing-price-field__currency {
    color: #64748b;
    font-size: 15px;
}
.listing-price-field__input {
    flex: 1;
    width: 0;
    min-width: 0;
}
.listing-price-field__input :deep(.el-input__wrapper) {
    min-height: 36px;
    box-shadow: none !important;
}
.listing-price-field__input :deep(.el-input__inner) {
    color: #0f172a;
    font-size: 16px;
    font-weight: 600;
    text-align: right;
}
.listing-price-field__hint {
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}
.listing-price-field__preview {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    margin-top: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}
.listing-price-field__preview-label {
    flex-shrink: 0;
    color: #64748b;
}
.listing-form__hint {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
.listing-form__more{margin-bottom:16px}
.listing-form__handoff{border-top:1px solid #e2e8f0;padding-top:12px;font-size:12px;line-height:1.7;color:#64748b}
@media (max-width: 760px) {
    .listing-form__grid {
        grid-template-columns: 1fr;
    }
}
</style>
