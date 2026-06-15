<template>
    <el-dialog v-model="visible" title="完成定价" width="560px" destroy-on-close class="da-form-dialog" @open="loadAsset">
        <div class="da-dialog-body">
            <div v-if="priceAsset" class="price-context">
                <div class="price-context__head">
                    <div>
                        <div class="price-context__title">{{ priceAsset.model || priceAsset.asset_no }}</div>
                        <div class="muted">成本 ¥{{ money(priceAsset.recycle_final_price) }} / IMEI {{ priceAsset.imei || '-' }}</div>
                    </div>
                    <el-tag :type="photoStatusType(priceAsset.photo_status)">{{ priceAsset.photo_status_name || priceAsset.photo_status }}</el-tag>
                </div>
                <div class="price-context__cols">
                    <div>
                        <div class="context-title">质检摘要</div>
                        <div v-if="checkSummaryEntries(priceAsset).length" class="mini-summary">
                            <div v-for="item in checkSummaryEntries(priceAsset).slice(0, 8)" :key="item.key">
                                <span>{{ item.key }}</span>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                        <el-empty v-else description="暂无摘要" :image-size="48" />
                    </div>
                    <div>
                        <div class="context-title">图片对比</div>
                        <div class="mini-images">
                            <el-image
                                v-for="(url, index) in priceCompareImages(priceAsset)"
                                :key="`${ url }-${ index }`"
                                :src="imgUrl(url)"
                                fit="cover"
                                :preview-src-list="priceCompareImages(priceAsset).map(imgUrl)"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <el-form :model="priceForm" label-width="90px">
                <div class="price-profit-panel">
                    <div>
                        <span>预估毛利</span>
                        <strong :class="{ danger: grossProfit < 0 }">¥{{ money(grossProfit) }}</strong>
                    </div>
                    <div>
                        <span>毛利率</span>
                        <strong :class="{ danger: Number(grossProfitRate) < 0 }">{{ grossProfitRate }}%</strong>
                    </div>
                    <div v-if="priceWarnings.length" class="price-warnings">
                        <el-alert v-for="item in priceWarnings" :key="item" type="warning" :closable="false" show-icon :title="item" />
                    </div>
                </div>
                <el-form-item label="销售价" required>
                    <el-input-number v-model="priceForm.sale_price" :min="0" :precision="0" class="!w-full" />
                </el-form-item>
                <el-form-item label="同行价">
                    <el-input-number v-model="priceForm.peer_price" :min="0" :precision="0" class="!w-full" />
                </el-form-item>
                <el-form-item label="最低价">
                    <el-input-number v-model="priceForm.min_price" :min="0" :precision="0" class="!w-full" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="priceForm.remark" type="textarea" :rows="3" placeholder="成色、渠道、底价原因等" />
                </el-form-item>
            </el-form>
        </div>
        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="priceLoading" @click="handleCompletePrice">保存定价</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { completeAssetPrice, getAssetInfo } from '@/addon/hsx_device_asset/api/device_asset'
import { useAssetFormat } from '../composables/useAssetFormat'

const props = defineProps<{ modelValue: boolean; assetRow: Record<string, any> | null }>()
const emit = defineEmits(['update:modelValue', 'success'])

const visible = computed({
    get: () => props.modelValue,
    set: (v: boolean) => emit('update:modelValue', v)
})

const { money, imgUrl, photoStatusType, checkSummaryEntries, priceCompareImages } = useAssetFormat()

const priceAsset = ref<any>(null)
const priceLoading = ref(false)
const priceForm = reactive({ sale_price: 0, peer_price: 0, min_price: 0, remark: '' })

const grossProfit = computed(() => Number(priceForm.sale_price || 0) - Number(priceAsset.value?.recycle_final_price || 0))
const grossProfitRate = computed(() => {
    const salePrice = Number(priceForm.sale_price || 0)
    if (salePrice <= 0) return '0.00'
    return ((grossProfit.value / salePrice) * 100).toFixed(2)
})
const priceWarnings = computed(() => {
    const warnings: string[] = []
    const salePrice = Number(priceForm.sale_price || 0)
    const minPrice = Number(priceForm.min_price || 0)
    const costPrice = Number(priceAsset.value?.recycle_final_price || 0)
    if (salePrice > 0 && costPrice > 0 && salePrice < costPrice) warnings.push('销售价低于回收成本，请确认是否亏损出货')
    if (salePrice > 0 && minPrice > salePrice) warnings.push('最低价高于销售价，请调整价格梯度')
    if (salePrice > 0 && !priceForm.remark && grossProfit.value < 0) warnings.push('亏损定价建议填写备注，便于后续 KPI 复盘')
    return warnings
})

const loadAsset = async () => {
    if (!props.assetRow?.id) return
    const res: any = await getAssetInfo(props.assetRow.id)
    priceAsset.value = res.data
    priceForm.sale_price = Number(priceAsset.value.sale_price || 0)
    priceForm.peer_price = Number(priceAsset.value.peer_price || 0)
    priceForm.min_price = Number(priceAsset.value.min_price || 0)
    priceForm.remark = priceAsset.value.price_remark || ''
}

const handleCompletePrice = async () => {
    if (!priceAsset.value?.id) return
    if (priceForm.sale_price <= 0) {
        ElMessage.warning('请输入销售价')
        return
    }
    if (Number(priceForm.min_price || 0) > 0 && Number(priceForm.min_price || 0) > Number(priceForm.sale_price || 0)) {
        ElMessage.warning('最低价不能高于销售价')
        return
    }
    priceLoading.value = true
    try {
        if (priceAsset.value.photo_status !== 'approved') {
            await ElMessageBox.confirm('当前照片还没有确认完成，仍然保存定价吗？建议先由复检人员确认照片。', '定价提醒', {
                type: 'warning'
            })
        }
        await completeAssetPrice(priceAsset.value.id, { ...priceForm })
        ElMessage.success('定价已保存')
        visible.value = false
        emit('success')
    } finally {
        priceLoading.value = false
    }
}
</script>

<style lang="scss" scoped>
.muted {
    color: var(--el-text-color-secondary);
    font-size: 12px;
}

.price-context {
    margin-bottom: 16px;
}

.price-context__head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.price-context__title {
    color: var(--el-text-color-primary);
    font-size: 16px;
    font-weight: 700;
}

.price-context__cols {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.context-title {
    margin-bottom: 8px;
    color: var(--el-text-color-primary);
    font-size: 13px;
    font-weight: 650;
}

.mini-summary {
    display: grid;
    gap: 6px;

    > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 7px 10px;
        border-radius: 6px;
        background: var(--el-bg-color);
        color: var(--el-text-color-secondary);
        font-size: 12px;

        strong {
            color: var(--el-text-color-primary);
            font-weight: 600;
            word-break: break-word;
            line-height: 1.6;
            white-space: pre-wrap;
            text-align: left;
        }
    }
}

.mini-images {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;

    .el-image {
        width: 100%;
        aspect-ratio: 1;
        border-radius: 6px;
        background: var(--el-bg-color);
    }
}

.price-profit-panel {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
    padding: 12px;
    border-radius: 8px;
    background: var(--el-bg-color-page);

    > div:not(.price-warnings) {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        color: var(--el-text-color-secondary);
        font-size: 13px;

        strong {
            color: var(--el-text-color-primary);
            font-size: 18px;
        }

        .danger {
            color: var(--el-color-danger);
        }
    }
}

.price-warnings {
    grid-column: 1 / -1;
    display: grid;
    gap: 8px;
}
</style>

<!-- 弹窗 teleport 到 body，定高/滚动用非 scoped 全局样式按类名定位 -->
<style lang="scss">
.da-form-dialog {
    .el-dialog__body {
        padding-top: 12px;
        padding-bottom: 8px;
    }

    .da-dialog-body {
        max-height: 56vh;
        overflow-y: auto;
        padding-right: 6px;
    }

    .da-dialog-body::-webkit-scrollbar {
        width: 6px;
    }

    .da-dialog-body::-webkit-scrollbar-thumb {
        border-radius: 6px;
        background: var(--el-border-color);
    }
}
</style>
