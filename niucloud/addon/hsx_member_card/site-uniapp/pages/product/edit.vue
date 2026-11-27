<template>
    <view class="mc-page mc-product-edit">
        <scroll-view class="mc-product-edit__scroll" scroll-y :show-scrollbar="false">
            <view class="mc-content mc-product-edit__content">
                <MemberCardState
                    v-if="loading || loadError"
                    :loading="loading"
                    :error="loadError"
                    action="重新加载"
                    @action="load"
                />
                <template v-else-if="activationPending">
                    <view class="mc-card">
                        <view class="mc-title">卡种已保存为草稿</view>
                        <view class="mc-sub">{{ form.product_name }}</view>
                        <MemberCardNotice tone="warning" :text="error || '继续完成剩余步骤后，才能为客户开卡。'" />
                        <view class="mc-sub">不会重复创建卡种。已完成的期初库存步骤也不会重复执行。</view>
                    </view>
                    <MemberCardButton text="返回卡种列表检查" @click="backToList" />
                </template>
                <template v-else>
                    <MemberCardNotice
                        v-if="saved"
                        tone="success"
                        text="卡种已保存。之后新开卡使用新规则，已售卡仍按开卡时权益执行。"
                        closable
                    />
                    <view class="mc-card">
                        <view class="mc-section-title">卡种信息</view>
                        <u-form :model="form" labelWidth="88" :labelStyle="{ fontSize: '14px', color: '#53637a' }">
                            <u-form-item label="卡种名称" required borderBottom>
                                <u-input
                                    v-model="form.product_name"
                                    maxlength="100"
                                    border="none"
                                    placeholder="例如：贴膜 10 次卡"
                                />
                            </u-form-item>
                            <u-form-item label="销售价" required borderBottom>
                                <u-input v-model="form.sale_price" type="digit" border="none" placeholder="0.00">
                                    <template #suffix>元</template>
                                </u-input>
                            </u-form-item>
                        </u-form>
                        <MemberCardCollapse title="快捷方案" summary="终身贴膜：永久、不限次、每日 1 次">
                            <view class="mc-options">
                                <view
                                    v-for="option in bindingOptions"
                                    :key="option.value"
                                    class="mc-option"
                                    @click="applyLifetimePreset(option.value)"
                                >
                                    {{ option.label }}
                                </view>
                            </view>
                            <view class="mc-sub">应用方案会替换服务规则，售价不变，仍可继续修改。</view>
                        </MemberCardCollapse>
                    </view>
                    <view class="mc-card">
                        <view class="mc-section-title">服务权益</view>
                        <view class="mc-field">
                            <text class="mc-label mc-required">服务名称</text>
                            <view class="mc-field__value">
                                <u-input
                                    v-model="form.item.item_name"
                                    border="none"
                                    maxlength="100"
                                    placeholder="例如：贴膜服务"
                                />
                            </view>
                        </view>
                        <view class="mc-section-title" style="margin-top: 20rpx">次数模式</view>
                        <MemberCardSegmented v-model="form.item.usage_mode" :options="usageOptions" />
                        <view v-if="form.item.usage_mode === 'limited'" class="mc-field">
                            <text class="mc-grow mc-required">可用次数</text>
                            <u-number-box v-model="form.item.total_times" :min="1" :max="9999" />
                        </view>
                        <view class="mc-field">
                            <view class="mc-grow">
                                <view>每日上限</view>
                                <view class="mc-small">0 表示不限制</view>
                            </view>
                            <u-number-box v-model="form.item.daily_limit" :min="0" :max="99" />
                        </view>
                        <view class="mc-section-title" style="margin-top: 20rpx">适用对象</view>
                        <MemberCardSegmented v-model="form.item.binding_mode" :options="bindingOptions" />
                        <view class="mc-sub">{{ bindingHelp }}</view>
                    </view>
                    <view class="mc-card">
                        <view class="mc-section-title">生效与有效期</view>
                        <MemberCardSegmented v-model="form.effective_mode" :options="effectiveOptions" />
                        <view v-if="form.effective_mode === 'fixed'" class="mc-sub">
                            当前按指定日期生效：{{ dateTime(form.fixed_start_at) }}。如需改具体日期，请在电脑端设置。
                        </view>
                        <view class="mc-divider" />
                        <MemberCardSegmented v-model="form.validity_mode" :options="validityOptions" />
                        <view v-if="form.validity_mode === 'fixed'" class="mc-sub">
                            当前固定失效时间：{{ dateTime(form.fixed_end_at) }}。如需改具体日期，请在电脑端设置。
                        </view>
                        <view v-if="form.validity_mode === 'duration'" class="mc-field">
                            <text class="mc-grow">有效时长</text>
                            <u-number-box v-model="form.duration_value" :min="1" :max="3650" />
                            <picker
                                :range="durationUnits"
                                range-key="label"
                                :value="form.duration_unit === 'month' ? 1 : 0"
                                @change="changeDuration"
                            >
                                <text class="mc-link">{{ form.duration_unit === 'month' ? '个月' : '天' }}⌄</text>
                            </picker>
                        </view>
                    </view>
                    <view class="mc-card">
                        <MemberCardCollapse
                            title="核销耗材"
                            :summary="
                                form.item.consumable_name
                                    ? form.item.consumable_name +
                                      ' · 每次 ' +
                                      quantity(form.item.standard_consumable_qty) +
                                      form.item.consumable_unit
                                    : '未关联耗材'
                            "
                        >
                            <view class="mc-field">
                                <text class="mc-label">耗材名称</text>
                                <view class="mc-field__value">
                                    <u-input
                                        v-model="form.item.consumable_name"
                                        maxlength="100"
                                        border="none"
                                        placeholder="留空则不关联库存"
                                    />
                                </view>
                            </view>
                            <template v-if="form.item.consumable_name">
                                <view class="mc-field">
                                    <text class="mc-grow">标准用量</text>
                                    <u-number-box v-model="form.item.standard_consumable_qty" :min="0" :max="9999" />
                                </view>
                                <view class="mc-field">
                                    <text class="mc-label">计量单位</text>
                                    <view class="mc-field__value">
                                        <u-input
                                            v-model="form.item.consumable_unit"
                                            maxlength="20"
                                            border="none"
                                            placeholder="张"
                                        />
                                    </view>
                                </view>
                                <view v-if="!inventoryAvailable" class="mc-sub">
                                    当前未接入库存，只保存耗材规则，不会扣减库存。
                                </view>
                                <template v-else-if="!id">
                                    <view class="mc-field">
                                        <text class="mc-label">期初库存</text>
                                        <view class="mc-field__value">
                                            <u-input
                                                v-model="initialStock"
                                                type="digit"
                                                border="none"
                                                placeholder="选填，留空不调整库存"
                                            />
                                        </view>
                                    </view>
                                    <view class="mc-sub">
                                        如填写，将把默认库位中此耗材库存设置为该数量，不是追加数量。
                                    </view>
                                </template>
                                <template v-else>
                                    <MemberCardNotice
                                        v-if="consumableChanged"
                                        text="耗材名称或单位已修改，请先保存卡种，再调整库存。"
                                    />
                                    <view class="mc-link" @click="openStock">盘点 / 调整此耗材库存 ›</view>
                                </template>
                            </template>
                        </MemberCardCollapse>
                        <MemberCardCollapse title="更多设置" summary="划线价、使用说明与显示排序">
                            <view class="mc-field">
                                <text class="mc-label">划线价</text>
                                <view class="mc-field__value">
                                    <u-input v-model="form.market_price" type="digit" border="none" placeholder="选填">
                                        <template #suffix>元</template>
                                    </u-input>
                                </view>
                            </view>
                            <view class="mc-field">
                                <text class="mc-label">显示排序</text>
                                <view class="mc-field__value">
                                    <u-input v-model="form.sort" type="number" border="none" />
                                </view>
                            </view>
                            <u-textarea
                                v-model="form.usage_notice"
                                maxlength="1000"
                                placeholder="使用说明、特殊约定（选填）"
                                count
                            />
                        </MemberCardCollapse>
                    </view>
                    <MemberCardNotice
                        v-if="error"
                        tone="error"
                        :text="error"
                        action="查看卡种列表"
                        @action="backToList"
                    />
                </template>
            </view>
        </scroll-view>
        <MemberCardActionBar v-if="!loading && !loadError" fixed>
            <view class="mc-actionbar__button">
                <MemberCardButton v-if="saved && !activationPending" text="返回列表" @click="backToList" />
                <MemberCardButton v-else text="返回" :disabled="submitting" @click="backToList" />
            </view>
            <view class="mc-actionbar__button">
                <MemberCardButton
                    type="primary"
                    :text="activationPending ? '继续完成启用' : id ? '保存修改' : '保存并启用'"
                    :loading="submitting"
                    :disabled="stockSaving"
                    @click="submit"
                />
            </view>
        </MemberCardActionBar>
        <MemberCardSheet v-model:show="stockVisible" title="盘点耗材库存" :busy="stockSaving" height="70vh">
            <view class="mc-title">{{ savedConsumable.name }}</view>
            <view class="mc-sub">使用收款与耗材设置中的默认仓库 / 库位</view>
            <MemberCardNotice
                tone="warning"
                text="填写盘点后的库存总量，不是新增数量。填写 0 会把此位置的该耗材库存调整为 0；留空不能提交。"
            />
            <view class="mc-field">
                <text class="mc-label mc-required">盘点后数量</text>
                <view class="mc-field__value">
                    <u-input v-model="stockTarget" type="digit" border="none" placeholder="输入实际剩余数量">
                        <template #suffix>{{ savedConsumable.unit }}</template>
                    </u-input>
                </view>
            </view>
            <u-textarea v-model="stockRemark" maxlength="255" placeholder="填写盘点或调整原因（必填）" count />
            <MemberCardNotice v-if="stockError" tone="error" :text="stockError" />
            <template #footer>
                <MemberCardButton type="primary" text="确认调整库存" :loading="stockSaving" @click="adjustStock" />
            </template>
        </MemberCardSheet>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import {
    adjustCardProductStock,
    enableCardProduct,
    getCardProduct,
    getMemberCardConfig,
    saveCardProduct
} from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardSheet from '../../components/MemberCardSheet.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardSegmented from '../../components/MemberCardSegmented.vue'
import { quantity, dateTime, errorText, markMemberCardChanged } from '../../utils/presentation'
const id = ref(0),
    loading = ref(true),
    loadError = ref(''),
    submitting = ref(false),
    error = ref(''),
    saved = ref(false),
    activationPending = ref(false)
const stockVisible = ref(false),
    stockSaving = ref(false),
    stockTarget = ref(''),
    stockRemark = ref(''),
    stockError = ref(''),
    initialStock = ref('')
const inventoryConfig = ref<any>({})
const savedConsumable = reactive({ name: '', unit: '' })
const inventoryAvailable = computed(() => Number(inventoryConfig.value.inventory_available) === 1)
const form = reactive<any>({
    product_name: '',
    sale_price: '',
    market_price: '',
    effective_mode: 'immediate',
    validity_mode: 'permanent',
    duration_value: 30,
    duration_unit: 'day',
    usage_notice: '',
    sort: 0,
    item: {
        item_code: 'film_service',
        item_name: '贴膜服务',
        binding_mode: 'member',
        usage_mode: 'limited',
        total_times: 10,
        daily_limit: 0,
        recognition_mode: 'average',
        recognition_amount: 0,
        consumable_code: '',
        consumable_name: '钢化膜',
        consumable_unit: '张',
        standard_consumable_qty: 1
    }
})
const usageOptions = [
    { label: '有限次数', value: 'limited' },
    { label: '不限次数', value: 'unlimited' }
]
const bindingOptions = [
    { label: '按会员本人', value: 'member' },
    { label: '绑定 IMEI', value: 'imei' },
    { label: '限定型号', value: 'model' }
]
const effectiveOptions = computed(() => [
    { label: '开卡即生效', value: 'immediate' },
    { label: '首次核销生效', value: 'first_use' },
    ...(Number(form.fixed_start_at) > 0 ? [{ label: '指定日期', value: 'fixed' }] : [])
])
const validityOptions = computed(() => [
    { label: '永久有效', value: 'permanent' },
    { label: '固定时长', value: 'duration' },
    ...(Number(form.fixed_end_at) > 0 ? [{ label: '指定日期', value: 'fixed' }] : [])
])
const durationUnits = [
    { label: '天', value: 'day' },
    { label: '个月', value: 'month' }
]
const changeDuration = (event: any) => {
    form.duration_unit = durationUnits[Number(event.detail.value)]?.value || 'day'
}
const bindingHelp = computed(
    () =>
        (
            ({
                member: '核对购卡人姓名和手机号，可为本人设备服务。',
                imei: '开卡时绑定设备，核销必须验证相同 IMEI。',
                model: '开卡时指定型号，核销时验证适用型号。'
            }) as Record<string, string>
        )[form.item.binding_mode] || ''
)
const consumableChanged = computed(
    () => savedConsumable.name !== form.item.consumable_name || savedConsumable.unit !== form.item.consumable_unit
)
const applyLifetimePreset = (mode: string) => {
    form.product_name ||= '终身贴膜服务卡'
    form.item.item_name = '终身贴膜服务'
    form.item.binding_mode = mode
    form.item.usage_mode = 'unlimited'
    form.item.daily_limit = 1
    form.effective_mode = 'immediate'
    form.validity_mode = 'permanent'
    uni.showToast({ title: '方案已应用，可继续修改', icon: 'none' })
}
const validNumber = (value: any) => String(value).trim() !== '' && Number.isFinite(Number(value)) && Number(value) >= 0
const rememberConsumable = () => {
    savedConsumable.name = form.item.consumable_name
    savedConsumable.unit = form.item.consumable_unit
}
let pendingInitialStock = false
const load = async () => {
    loading.value = true
    loadError.value = ''
    try {
        inventoryConfig.value = ((await getMemberCardConfig()) as any)?.data || {}
        if (id.value) {
            const data: any = ((await getCardProduct(id.value)) as any)?.data
            if (!data?.id) throw new Error('missing product')
            Object.assign(form, data, { item: { ...form.item, ...(data.item || {}) } })
            rememberConsumable()
        }
        uni.setNavigationBarTitle({ title: id.value ? '编辑卡种' : '新增卡种' })
    } catch (e) {
        loadError.value = errorText(e, '卡种资料加载失败，请重试')
    } finally {
        loading.value = false
    }
}
const submit = async () => {
    if (submitting.value || stockSaving.value || loading.value) return
    if (!activationPending.value) {
        let message = ''
        if (!form.product_name.trim()) message = '请填写卡种名称'
        else if (!validNumber(form.sale_price)) message = '请填写正确的销售价'
        else if (form.market_price !== '' && !validNumber(form.market_price)) message = '划线价不能小于 0'
        else if (!form.item.item_name.trim()) message = '请填写服务名称'
        else if (
            form.item.usage_mode === 'limited' &&
            (!Number.isInteger(Number(form.item.total_times)) || Number(form.item.total_times) <= 0)
        )
            message = '可用次数必须为正整数'
        else if (
            form.validity_mode === 'duration' &&
            (!Number.isInteger(Number(form.duration_value)) || Number(form.duration_value) <= 0)
        )
            message = '有效时长必须为正整数'
        else if (
            form.item.consumable_name &&
            (!validNumber(form.item.standard_consumable_qty) || Number(form.item.standard_consumable_qty) <= 0)
        )
            message = '标准耗材用量必须大于 0'
        else if (!id.value && initialStock.value !== '' && !validNumber(initialStock.value))
            message = '期初库存需为大于等于 0 的数字'
        if (message) {
            uni.showToast({ title: message, icon: 'none' })
            return
        }
    }
    submitting.value = true
    error.value = ''
    saved.value = false
    try {
        if (!activationPending.value) {
            const wasNew = !id.value
            const response: any = await saveCardProduct(
                { ...form, market_price: form.market_price === '' ? 0 : form.market_price },
                id.value
            )
            const savedId = Number(response?.data?.id || id.value)
            if (!savedId) throw new Error('missing product')
            id.value = savedId
            rememberConsumable()
            markMemberCardChanged()
            activationPending.value = wasNew
            pendingInitialStock =
                wasNew && inventoryAvailable.value && !!form.item.consumable_name && initialStock.value !== ''
        }
        if (pendingInitialStock) {
            await adjustCardProductStock(id.value, {
                target_quantity: Number(initialStock.value),
                remark: '移动端新建卡种录入期初库存'
            })
            pendingInitialStock = false
            markMemberCardChanged()
        }
        if (activationPending.value) {
            await enableCardProduct(id.value)
            activationPending.value = false
            markMemberCardChanged()
        }
        saved.value = true
        uni.showToast({ title: '卡种已保存', icon: 'success' })
        uni.setNavigationBarTitle({ title: '编辑卡种' })
    } catch (e) {
        error.value = errorText(
            e,
            activationPending.value
                ? '卡种已保存，但后续步骤未完成，请核对后继续'
                : id.value
                  ? '保存修改失败，请重试'
                  : '尚未确认保存结果，请先到卡种列表核对，避免重复创建'
        )
    } finally {
        submitting.value = false
    }
}
const openStock = () => {
    if (consumableChanged.value) {
        uni.showToast({ title: '请先保存耗材修改', icon: 'none' })
        return
    }
    stockTarget.value = ''
    stockRemark.value = ''
    stockError.value = ''
    stockVisible.value = true
}
const adjustStock = async () => {
    if (stockSaving.value || submitting.value) return
    if (!validNumber(stockTarget.value) || !stockRemark.value.trim()) {
        stockError.value = '请输入实际库存数量并填写调整原因'
        return
    }
    stockSaving.value = true
    stockError.value = ''
    try {
        const modal = await uni.showModal({
            title: '确认盘点数量',
            content:
                '将“' +
                savedConsumable.name +
                '”在默认库位的库存设置为 ' +
                quantity(stockTarget.value) +
                savedConsumable.unit +
                '，不是追加此数量。',
            confirmText: '确认调整'
        })
        if (!modal.confirm) return
        const response: any = await adjustCardProductStock(id.value, {
            target_quantity: Number(stockTarget.value),
            remark: stockRemark.value.trim()
        })
        markMemberCardChanged()
        stockVisible.value = false
        uni.showToast({
            title: '库存已调整为 ' + quantity(response?.data?.stock_after ?? stockTarget.value),
            icon: 'none'
        })
    } catch (e) {
        stockError.value = errorText(e, '调整结果未确认，请到库存管理核对后再试')
    } finally {
        stockSaving.value = false
    }
}
const backToList = () => {
    if (submitting.value || stockSaving.value) return
    uni.navigateBack({ fail: () => uni.redirectTo({ url: '/addon/hsx_member_card/pages/product/list' }) })
}
onLoad((options: any) => {
    id.value = Number(options?.id || 0)
    void load()
})
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif

.mc-product-edit {
    height: 100vh;
    overflow: hidden;
}
.mc-product-edit__scroll {
    height: 100%;
    width: 100%;
}
.mc-product-edit__content {
    padding-bottom: calc(170rpx + env(safe-area-inset-bottom));
}
</style>
