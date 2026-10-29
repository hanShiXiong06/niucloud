<template>
    <div class="listing-form">
        <ErpListingLayout v-if="basicPublish" :model-value="sectionOrder" @update:model-value="setSectionOrder" class="listing-layout-control" />
        <div v-if="basicPublish && storageError" class="listing-layout-error">{{ storageError }}</div>
        <div v-if="!basicPublish && definition.description" class="listing-form__notice">
            <div class="listing-form__notice-icon">i</div>
            <div>
                <strong>{{ basicPublish ? '确认分类、价格和商品图片' : definition.title }}</strong>
                <span>{{ definition.description }}</span>
            </div>
        </div>
        <div v-if="!basicPublish && contract?.mall?.message" class="listing-form__hint">{{ contract.mall.message }}</div>

        <el-form-item v-if="visible('mall_category_id')" label="商城分类" required :style="basicPublish ? sectionStyle('category') : undefined">
            <div class="w-full"><ErpMallCategorySelect :model-value="Number(modelValue.mall_category_id || 0)" @update:model-value="value => basicPublish ? selectCategory(value) : updateField('mall_category_id', value)" /><div v-if="categorySuggestionNotice" class="listing-form__hint">{{ categorySuggestionNotice }}</div></div>
        </el-form-item>

        <section v-if="basicPublish" :style="sectionStyle('details')">
            <el-form-item label="商城规格">
                <div class="listing-mall-fields">
                    <ErpMallBrandSelect :model-value="Number(modelValue.mall_brand_id || 0)" :brands="mallBrands" :disabled="mallLoading || !!mallError" @change="value => selectBrand(value.brand_id, value.brand_name)" />
                    <div class="listing-mall-fields__grid">
                        <el-select v-if="memoryOptions.length" :model-value="modelValue.mall_memory || ''" filterable clearable placeholder="选择内存" :loading="mallLoading" @update:model-value="value => changeIdentity({ mall_memory: value || '' })">
                            <el-option v-for="value in memoryOptions" :key="value" :label="value" :value="value" />
                        </el-select>
                        <el-input v-else :model-value="modelValue.mall_memory || ''" clearable maxlength="50" placeholder="填写内存，如 256GB" :disabled="!attributesReady || !modelValue.mall_category_id || !!mallError" @update:model-value="value => changeIdentity({ mall_memory: value })" />
                        <el-select v-if="colorOptions.length" :model-value="modelValue.mall_color || ''" filterable clearable placeholder="选择颜色" :loading="mallLoading" @update:model-value="value => changeIdentity({ mall_color: value || '' })">
                            <el-option v-for="value in colorOptions" :key="value" :label="value" :value="value" />
                        </el-select>
                        <el-input v-else :model-value="modelValue.mall_color || ''" clearable maxlength="50" placeholder="填写颜色，如 银色" :disabled="!attributesReady || !modelValue.mall_category_id || !!mallError" @update:model-value="value => changeIdentity({ mall_color: value })" />
                    </div>
                    <div v-if="mallLoading" class="listing-form__hint">正在加载本站商城规格…</div>
                    <div v-else-if="mallError" class="listing-form__hint listing-form__hint--error">{{ mallError }} <el-button link type="primary" @click="refreshMall">重新加载</el-button></div>
                    <div v-else-if="modelValue.mall_category_id && (!memoryOptions.length || !colorOptions.length)" class="listing-form__hint">字典暂无对应项，可填写实际内存、颜色。</div>
                    <div v-if="attributeNotice" class="listing-form__hint listing-form__hint--error">{{ attributeNotice }}</div>
                </div>
            </el-form-item>
            <div v-if="attributesReady && gradeNeedsSelection" class="listing-form__hint listing-form__hint--error">原成色等级已失效，请 <el-button link type="primary" @click="showAttributes = true">展开商品资料重新选择或清空</el-button></div>
            <div v-if="labelsReady && invalidLabels" class="listing-form__hint listing-form__hint--error">原标签已失效，请 <el-button link type="primary" @click="showAttributes = true">展开商品资料移除或重选</el-button></div>
            <div v-if="attributesReady && invalidBrand" class="listing-form__hint listing-form__hint--error">原品牌已移除，请重新选择品牌或清空。</div>
            <button type="button" class="listing-section-toggle" :aria-expanded="showAttributes" @click="showAttributes = !showAttributes">
                <span class="listing-section-toggle__copy"><strong>商品资料</strong><span>{{ materialSummary }}</span></span><span class="listing-section-toggle__action">{{ showAttributes ? '收起' : '完善' }} {{ showAttributes ? '−' : '+' }}</span>
            </button>
            <div v-show="showAttributes" class="listing-section-content">
            <el-form-item label="成色等级">
                <div class="listing-mall-fields">
                    <div v-if="grades.length" class="listing-grade-options">
                        <button v-for="grade in grades" :key="grade.grade_id" type="button" class="listing-grade-options__item" :class="{ 'is-selected': Number(modelValue.mall_grade_id || 0) === grade.grade_id }" :aria-pressed="Number(modelValue.mall_grade_id || 0) === grade.grade_id" @click="selectGrade(grade.grade_id, grade.grade_name)">{{ grade.grade_name }}</button>
                    </div>
                    <div v-else class="listing-form__hint">{{ mallLoading ? '正在加载等级…' : '商城暂无等级，可留空。' }}</div>
                    <div v-if="attributesReady && gradeNeedsSelection" class="listing-form__hint listing-form__hint--error">{{ modelValue.mall_grade_name ? `原等级“${modelValue.mall_grade_name}”已失效或未唯一匹配` : '原等级已停用或删除' }}，请重新选择，或 <el-button link type="primary" @click="selectGrade(0)">清空等级</el-button></div>
                </div>
            </el-form-item>
            <div v-if="visible('mall_battery_health') || visible('mall_warranty_date')" class="listing-form__grid">
                <el-form-item v-if="visible('mall_battery_health')" label="电池健康度">
                    <div class="listing-mall-fields">
                        <el-input :model-value="modelValue.mall_battery_health ?? ''" inputmode="numeric" clearable placeholder="未填写" @update:model-value="onBatteryHealthChange"><template #suffix>%</template></el-input>
                    </div>
                </el-form-item>
                <el-form-item v-if="visible('mall_warranty_date')" label="保修到期日">
                    <div class="listing-mall-fields">
                        <el-date-picker :model-value="modelValue.mall_warranty_date || ''" type="date" value-format="YYYY-MM-DD" format="YYYY-MM-DD" clearable placeholder="未填写" class="listing-mall-warranty-picker" @update:model-value="value => updateField('mall_warranty_date', String(value || ''))" />
                    </div>
                </el-form-item>
            </div>
            <ErpMallLabels :model-value="modelValue.mall_label_ids || []" :labels="mallLabels" :disabled="mallLoading || !labelsReady" @update:model-value="value => updateField('mall_label_ids', value)" />
            </div>
        </section>

        <el-form-item v-if="visible('retail_price')" :required="required('retail_price')" :label="autoPricing ? '同行基准价' : '零售价'" class="listing-price-field" :style="basicPublish ? sectionStyle('price') : undefined">
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

        <div :style="basicPublish ? sectionStyle('images') : undefined">
        <el-form-item v-if="basicPublish" label="图片方式">
            <el-radio-group :model-value="imageMode" @change="onImageModeChange">
                <el-radio-button label="standard">标准图片</el-radio-button>
                <el-radio-button label="custom">自定义实拍</el-radio-button>
            </el-radio-group>
        </el-form-item>
        <el-form-item v-if="visible('image_urls')" label="商品图片" :required="required('image_urls')">
            <div class="w-full">
                <div v-if="basicPublish && imageMode === 'standard' && standardImages.matched" class="listing-standard-gallery">
                    <el-image v-for="(url, index) in standardImages.images" :key="url" :src="img(url)" :preview-src-list="standardImages.images.map(value => img(value))" :initial-index="index" preview-teleported fit="cover" />
                </div>
                <upload-image
                    v-else-if="!basicPublish || imageMode === 'custom' || canUploadStandard"
                    :key="basicPublish ? imageContextKey : 'plain'"
                    :model-value="modelValue.image_urls || ''"
                    :limit="9"
                    width="72px"
                    height="72px"
                    :image-text="basicPublish && imageMode === 'standard' ? '上传标准图' : '上传/拍摄'"
                    @update:model-value="onImagesChange"
                />
                <div v-else class="listing-standard-placeholder">{{ mallLoading ? '标准图匹配中…' : '先确认商城规格，再上传标准图片' }}</div>
                <div class="listing-form__hint" :class="{ 'listing-form__hint--error': !!mallError && imageMode === 'standard' }">{{ basicPublish ? imageHint : '第一张作为封面；请包含正面、背面及真实瑕疵图，避免遗漏影响购买的信息。' }}</div>
            </div>
        </el-form-item>
        </div>
        <div :style="basicPublish ? sectionStyle('extras') : undefined">
        <button v-if="basicPublish" type="button" class="listing-section-toggle" :aria-expanded="showExtras" @click="showExtras = !showExtras"><span class="listing-section-toggle__copy"><strong>视频与说明</strong><span>{{ extrasSummary }}</span></span><span class="listing-section-toggle__action">{{ showExtras ? '收起 −' : '补充 +' }}</span></button>
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
        </div>
        <div v-if="basicPublish" class="listing-publish-result"><span>上架后客户即可购买</span><el-button link @click="showRules = !showRules">{{ showRules ? '收起规则' : '上架规则' }}</el-button></div>
        <div v-if="basicPublish && showRules" class="listing-form__handoff">分类、售价、图片及已填商品资料一并同步商城。电池、保修信息显示在副标题；未知项留空。质检报告自动同步，不需要重复录入。标准图只复用相同型号、内存和颜色；真实瑕疵请用实拍图。保存不会自动代替运营确认资料。</div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { img } from '@/utils/common'
import { salesPricePreview } from '@/addon/hsx_erp/hooks/useSalesPricing'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import ErpMallCategorySelect from '@/addon/hsx_erp/components/ErpMallCategorySelect.vue'
import ErpMallLabels from '@/addon/hsx_erp/components/ErpMallLabels.vue'
import ErpMallBrandSelect from '@/addon/hsx_erp/components/ErpMallBrandSelect.vue'
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
const emit = defineEmits(['update:modelValue', 'catalog-change'])

const definition = computed(() => erpListingFormDefinition(props.contract, props.action))
const basicPublish = computed(() => Number(definition.value.publish_basic) === 1)
const autoPricing = computed(() => Number(props.pricing?.enabled) === 1)
const hasPrice = computed(() => Number.isFinite(Number(props.modelValue.retail_price)) && Number(props.modelValue.retail_price) > 0)
const pricePreview = computed(() => autoPricing.value && hasPrice.value ? salesPricePreview(props.pricing, props.modelValue.retail_price) : '')
const showExtras = ref(false)
const showAttributes = ref(false)
const showRules = ref(false)
const { sectionOrder, sectionStyle, setSectionOrder, storageError } = useErpListingLayout()
const materialSummary = computed(() => [
    props.modelValue.mall_grade_name,
    props.modelValue.mall_battery_health !== '' && props.modelValue.mall_battery_health != null ? `电池 ${props.modelValue.mall_battery_health}%` : '',
    props.modelValue.mall_warranty_date ? `保修至 ${props.modelValue.mall_warranty_date}` : '',
    Array.isArray(props.modelValue.mall_label_ids) && props.modelValue.mall_label_ids.length ? `${props.modelValue.mall_label_ids.length} 个标签` : '',
].filter(Boolean).join(' · ') || '成色、电池、保修、商城标签，选填')
const extrasSummary = computed(() => [props.modelValue.video_url ? '已传视频' : '', ...['quality_remark', 'remark_public', 'remark_internal'].filter(key => String(props.modelValue[key] || '').trim()).map(key => ({ quality_remark: '质检备注', remark_public: '对外说明', remark_internal: '内部备注' }[key]))].filter(Boolean).join(' · ') || '选填，不影响基本上架')
const visible = (field: string) => erpListingFieldVisible(props.contract, props.action, field)
const required = (field: string) => definition.value.required_fields?.includes(field) === true
const { loading: mallLoading, attributesReady, labelsReady, error: mallError, memoryOptions, colorOptions, attributeNotice, grades, mallLabels, mallBrands, categorySuggestionNotice, gradeNeedsSelection, standard: standardImages, mode: imageMode, imageContextKey, canUploadStandard, imageHint, selectMode, selectCategory, selectGrade, selectBrand, changeIdentity, changeImages, refresh: refreshMall } = useErpMallListingMedia(
    () => props.modelValue, () => basicPublish.value,
    patch => emit('update:modelValue', { ...props.modelValue, ...patch }),
)
const invalidLabels = computed(() => (props.modelValue.mall_label_ids || []).some((id: any) => !mallLabels.value.some(item => item.label_id === Number(id))))
const invalidBrand = computed(() => Number(props.modelValue.mall_brand_id || 0) > 0 && !mallBrands.value.some(item => item.brand_id === Number(props.modelValue.mall_brand_id)))

function updateField(field: string, value: any) {
    emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function onCatalogChange(payload: any) {
    emit('catalog-change', payload)
}
function onImageModeChange(value: any) { selectMode(value === 'custom' ? 'custom' : 'standard') }
function onImagesChange(value: any) {
    if (basicPublish.value) changeImages(value)
    else updateField('image_urls', value)
}
function onBatteryHealthChange(value: any) {
    const text = String(value ?? '')
    // 保留无效编辑态以显示校验提示，不能默默把小数四舍五入或把空值写成 0。
    updateField('mall_battery_health', text === '' ? '' : (/^[0-9]+$/.test(text) ? Number(text) : text))
}
</script>

<style scoped>
.listing-form{display:flex;flex-direction:column}.listing-layout-control,.listing-layout-error{order:-1}.listing-layout-error{font-size:12px;line-height:1.5;color:var(--el-color-warning-dark-2)}.listing-publish-result,.listing-form__handoff{order:99}
.listing-section-toggle{display:flex;align-items:center;gap:12px;width:100%;padding:12px 14px;margin:12px 0;border:1px solid var(--el-border-color);border-radius:8px;background:var(--el-fill-color-blank);text-align:left;cursor:pointer}.listing-section-toggle__copy{display:flex;flex:1;min-width:0;flex-direction:column;gap:4px}.listing-section-toggle__copy strong{font-size:14px;font-weight:600;color:var(--el-text-color-primary)}.listing-section-toggle__copy>span{font-size:12px;line-height:1.5;color:var(--el-text-color-secondary);overflow-wrap:anywhere}.listing-section-toggle__action{flex-shrink:0;font-size:13px;color:var(--el-color-primary)}.listing-section-content{padding:8px 0}.listing-publish-result{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;font-size:12px;color:var(--el-text-color-secondary)}
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
.listing-mall-fields{width:100%;min-width:0}.listing-mall-fields__model{margin-bottom:8px;font-size:13px;color:#475569}.listing-mall-fields__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.listing-mall-warranty-picker{width:240px;max-width:100%}.listing-grade-options{display:flex;flex-wrap:wrap;gap:8px}.listing-grade-options__item{padding:6px 12px;border:1px solid #dce3ed;border-radius:6px;background:#fff;color:#475569;font-size:13px;line-height:1.5;cursor:pointer}.listing-grade-options__item.is-selected{border-color:var(--el-color-primary);background:var(--el-color-primary-light-9);color:var(--el-color-primary)}.listing-standard-gallery{display:flex;flex-wrap:wrap;gap:10px}.listing-standard-gallery .el-image{width:72px;height:72px;border:1px solid #e2e8f0;border-radius:6px}.listing-standard-placeholder{padding:18px;border:1px dashed #cbd5e1;border-radius:6px;background:#f8fafc;color:#64748b;font-size:12px}.listing-form__hint--error{color:var(--el-color-danger)}
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
