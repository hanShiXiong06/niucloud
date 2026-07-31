<template>
    <div class="listing-form">
        <div v-if="definition.description" class="listing-form__notice">
            <div class="listing-form__notice-icon">i</div>
            <div>
                <strong>{{ definition.title }}</strong>
                <span>{{ definition.description }}</span>
            </div>
        </div>

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
            <el-form-item v-if="visible('retail_price')" label="销售价格" :required="required('retail_price')">
                <el-input-number
                    :model-value="Number(modelValue.retail_price || 0)"
                    :min="0"
                    :precision="2"
                    :controls="false"
                    class="!w-full"
                    placeholder="不影响采购成本"
                    @update:model-value="value => updateField('retail_price', Number(value || 0))"
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
                <div class="listing-form__hint">建议上传正面、背面、边框和真实瑕疵图，保存后作为商城展示资料。</div>
            </div>
        </el-form-item>
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
                placeholder="质检、外观说明（内部使用）"
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
</template>

<script setup lang="ts">
import { computed } from 'vue'
import ErpCatalogProductSelect from '@/addon/hsx_erp/components/ErpCatalogProductSelect.vue'
import { erpListingFieldVisible, erpListingFormDefinition, type ErpListingAction } from '@/addon/hsx_erp/hooks/useErpListingForm'

const props = defineProps<{
    modelValue: Record<string, any>
    contract?: Record<string, any>
    action: ErpListingAction
}>()
const emit = defineEmits(['update:modelValue', 'catalog-change'])

const definition = computed(() => erpListingFormDefinition(props.contract, props.action))
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
.listing-form__hint {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
@media (max-width: 760px) {
    .listing-form__grid {
        grid-template-columns: 1fr;
    }
}
</style>
