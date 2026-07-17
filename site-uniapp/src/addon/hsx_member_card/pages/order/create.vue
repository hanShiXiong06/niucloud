<template>
    <view class="mc-page create-page">
        <view class="page-intro">
            <view class="page-intro__icon"><u-icon name="plus" color="#ffffff" size="23" /></view>
            <view>
                <text>快速开卡</text>
                <small>客户、卡种与收款一次完成</small>
            </view>
        </view>

        <view class="form-section mc-surface">
            <view class="section-label">
                <view class="mc-icon-box"><u-icon name="account" color="#2563eb" size="19" /></view>
                <view><text>购卡客户</text><small>支持姓名、手机号与会员号检索</small></view>
            </view>
            <view class="select-row" :class="{ selected: member.member_id }" @click="memberVisible = true">
                <view v-if="member.member_id" class="selected-member">
                    <view class="member-avatar">{{ String(member.display_name || '客').slice(0, 1) }}</view>
                    <view><strong>{{ member.display_name }}</strong><small>{{ member.mobile_masked }}</small></view>
                </view>
                <view v-else class="placeholder-row">
                    <u-icon name="search" color="#94a3b8" size="18" />
                    <text>选择或快速创建客户</text>
                </view>
                <u-icon name="arrow-right" color="#94a3b8" size="16" />
            </view>
        </view>

        <view class="form-section mc-surface">
            <view class="section-label">
                <view class="mc-icon-box mc-icon-box--violet"><u-icon name="order" color="#7c3aed" size="19" /></view>
                <view><text>选择卡种</text><small>选择本次销售的服务权益</small></view>
            </view>
            <view class="products">
                <view v-for="product in products" :key="product.id" class="product" :class="{ active: form.product_id === product.id }" @click="form.product_id = product.id">
                    <view class="product__check">
                        <u-icon :name="form.product_id === product.id ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="form.product_id === product.id ? '#2563eb' : '#cbd5e1'" size="21" />
                    </view>
                    <view class="product__main">
                        <strong>{{ product.product_name }}</strong>
                        <small>{{ product.item?.usage_mode === 'unlimited' ? '不限次' : `${product.item?.total_times || 0} 次` }} · {{ product.validity_text }}</small>
                    </view>
                    <text class="product__price">¥{{ money(product.sale_price) }}</text>
                </view>
                <view v-if="!products.length" class="empty-product">
                    <u-icon name="order" color="#cbd5e1" size="34" />
                    <text>暂无已启用卡种</text>
                    <small>请先前往 PC 端创建并启用卡种</small>
                </view>
            </view>
        </view>

        <view class="form-section mc-surface">
            <view class="section-label">
                <view class="mc-icon-box mc-icon-box--green"><u-icon name="account" color="#16a34a" size="19" /></view>
                <view><text>收款处理</text><small>现结自动记入实际收款流水</small></view>
            </view>
            <view class="mode-grid">
                <view class="mode-card" :class="{ active: form.settlement_mode === 'immediate' }" @click="form.settlement_mode = 'immediate'">
                    <u-icon name="checkmark-circle" :color="form.settlement_mode === 'immediate' ? '#2563eb' : '#94a3b8'" size="19" />
                    <view><text>现场收款</text><small>选择实际到账账户</small></view>
                </view>
                <view v-if="config.allow_receivable === 1" class="mode-card" :class="{ active: form.settlement_mode === 'receivable' }" @click="form.settlement_mode = 'receivable'">
                    <u-icon name="clock" :color="form.settlement_mode === 'receivable' ? '#2563eb' : '#94a3b8'" size="19" />
                    <view><text>记入应收</text><small>由财务后续跟进</small></view>
                </view>
            </view>
            <view v-if="form.settlement_mode === 'immediate'" class="select-row account-row" @click="accountVisible = true">
                <view><strong>到账账户</strong><small>{{ accountName || '请选择实际到账账户' }}</small></view>
                <u-icon name="arrow-right" color="#94a3b8" size="16" />
            </view>
            <ErpVoucherUploader v-if="form.settlement_mode === 'immediate'" v-model="voucherUrls" title="收款凭证（选填）" hint="上传转账截图，财务流水长期留痕" />
        </view>

        <view class="form-section mc-surface remark-section">
            <view class="section-label section-label--simple">
                <view class="mc-icon-box mc-icon-box--orange"><u-icon name="file-text" color="#d97706" size="19" /></view>
                <view><text>业务备注</text><small>选填活动来源或特殊约定</small></view>
            </view>
            <u-textarea v-model="form.remark" height="110rpx" maxlength="255" placeholder="请输入备注（选填）" />
        </view>

        <view class="mc-bottom-action">
            <MemberCardButton type="primary" icon="checkmark" text="确认开卡" :loading="submitting" @click="submit" />
        </view>

        <MemberCardMemberPopup v-model:show="memberVisible" @select="member = $event" />
        <u-popup :show="accountVisible" mode="bottom" :safe-area-inset-bottom="true" round="20" @close="accountVisible = false">
            <view class="mc-sheet account-sheet">
                <view class="mc-sheet-head">
                    <view><text class="mc-sheet-title">选择到账账户</text><text class="mc-sheet-sub">本次收款将记录到所选资金账户</text></view>
                    <u-icon name="close" color="#94a3b8" size="20" @click="accountVisible = false" />
                </view>
                <view v-for="account in config.capital_account_options || []" :key="account.id" class="account-item" :class="{ active: form.capital_account_id === account.id }" @click="selectAccount(account)">
                    <view class="account-item__icon"><u-icon name="account" color="#2563eb" size="18" /></view>
                    <text>{{ account.name }}</text>
                    <u-icon v-if="form.capital_account_id === account.id" name="checkmark-circle-fill" color="#2563eb" size="20" />
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createCardOrder, getCardProductOptions, getMemberCardConfig, memberCardRequestId } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'

const memberVisible = ref(false)
const accountVisible = ref(false)
const submitting = ref(false)
const products = ref<any[]>([])
const config = ref<any>({ allow_receivable: 1, capital_account_options: [] })
const member = ref<any>({})
const voucherUrls = ref('')
const form = reactive<any>({ product_id: 0, settlement_mode: 'immediate', capital_account_id: 0, remark: '' })
const money = (value: any) => Number(value || 0).toFixed(2)
const accountName = computed(() => config.value.capital_account_options?.find((item: any) => Number(item.id) === Number(form.capital_account_id))?.name || '')

const selectAccount = (account: any) => {
    form.capital_account_id = Number(account.id)
    accountVisible.value = false
}
const submit = async () => {
    if (!member.value.member_id) return uni.showToast({ title: '请选择购卡客户', icon: 'none' })
    if (!form.product_id) return uni.showToast({ title: '请选择卡种', icon: 'none' })
    if (form.settlement_mode === 'immediate' && !form.capital_account_id) return uni.showToast({ title: '请选择到账账户', icon: 'none' })
    submitting.value = true
    try {
        const result: any = (await createCardOrder({
            ...form,
            member_id: member.value.member_id,
            request_id: memberCardRequestId('issue'),
            voucher_urls: voucherUrls.value ? voucherUrls.value.split(',').filter(Boolean) : [],
        }))?.data
        uni.showToast({ title: result.success ? '开卡成功' : '开卡单已保存', icon: result.success ? 'success' : 'none' })
        setTimeout(() => uni.redirectTo({ url: '/addon/hsx_member_card/pages/order/list' }), 700)
    } finally {
        submitting.value = false
    }
}

onLoad(async () => {
    const [productResult, configResult]: any = await Promise.all([getCardProductOptions(), getMemberCardConfig()])
    products.value = productResult?.data || []
    config.value = configResult?.data || config.value
    form.capital_account_id = Number(config.value.default_capital_account_id || 0)
})
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';

.create-page { padding-bottom: 150rpx; }
.page-intro { display: flex; margin: 2rpx 2rpx 22rpx; align-items: center; gap: 17rpx; }
.page-intro__icon { display: flex; width: 72rpx; height: 72rpx; align-items: center; justify-content: center; border-radius: 18rpx; background: #2563eb; }
.page-intro text, .page-intro small { display: block; }
.page-intro text { font-size: 35rpx; font-weight: 750; }
.page-intro small { margin-top: 6rpx; color: #64748b; font-size: 22rpx; }
.form-section { margin-bottom: 18rpx; padding: 24rpx; }
.section-label { display: flex; margin-bottom: 20rpx; align-items: center; gap: 15rpx; }
.section-label > view:last-child { display: flex; flex-direction: column; gap: 5rpx; }
.section-label text { font-size: 27rpx; font-weight: 700; }
.section-label small { color: #94a3b8; font-size: 20rpx; }
.section-label--simple { margin-bottom: 17rpx; }
.select-row { display: flex; min-height: 88rpx; padding: 0 18rpx; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 16rpx; border: 1rpx solid #dfe6ef; border-radius: 14rpx; }
.select-row.selected { border-color: #bfdbfe; background: #f8fbff; }
.selected-member { display: flex; min-width: 0; flex: 1; align-items: center; gap: 13rpx; }
.selected-member > view:last-child, .account-row > view { display: flex; flex-direction: column; gap: 5rpx; }
.member-avatar { display: flex; width: 54rpx; height: 54rpx; flex: 0 0 54rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-weight: 700; }
.select-row small, .product small, .mode-card small { color: #64748b; font-size: 21rpx; }
.placeholder-row { display: flex; align-items: center; gap: 10rpx; color: #94a3b8; font-size: 24rpx; }
.products { display: flex; flex-direction: column; gap: 12rpx; }
.product { display: flex; min-height: 90rpx; padding: 18rpx; box-sizing: border-box; align-items: center; gap: 13rpx; border: 2rpx solid #edf1f6; border-radius: 15rpx; transition: border-color .2s; }
.product.active { border-color: #60a5fa; background: #f8fbff; }
.product__check { display: flex; flex: 0 0 auto; }
.product__main { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 7rpx; }
.product__price { color: #ea580c; font-size: 27rpx; font-weight: 750; }
.empty-product { display: flex; padding: 46rpx 20rpx; flex-direction: column; align-items: center; gap: 9rpx; color: #64748b; }
.empty-product small { color: #94a3b8; font-size: 21rpx; }
.mode-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12rpx; }
.mode-card { display: flex; min-height: 92rpx; padding: 16rpx; box-sizing: border-box; align-items: center; gap: 11rpx; border: 1rpx solid #e2e8f0; border-radius: 14rpx; }
.mode-card.active { border-color: #60a5fa; background: #eff6ff; }
.mode-card > view { display: flex; min-width: 0; flex-direction: column; gap: 5rpx; }
.mode-card text { font-size: 24rpx; font-weight: 650; }
.account-row { margin: 16rpx 0; padding: 16rpx;}
.remark-section :deep(.u-textarea) { background: #f8fafc !important; }
.account-item { display: flex; min-height: 88rpx; padding: 0 16rpx; align-items: center; gap: 15rpx; border-top: 1rpx solid #edf1f6; }
.account-item.active { background: #f8fbff; }
.account-item text { min-width: 0; flex: 1; }
.account-item__icon { display: flex; width: 52rpx; height: 52rpx; align-items: center; justify-content: center; border-radius: 13rpx; background: #eff6ff; }
</style>
