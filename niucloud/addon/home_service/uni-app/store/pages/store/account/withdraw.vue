<template>
  <view :style="themeColor()" class="withdraw-page">
    <!-- 顶部装饰背景 -->
    <view class="top-decoration" v-if="!pageLoading && config.is_open == 1" ></view>
    
    <scroll-view :scroll-y="true" class="w-screen h-screen bg-[var(--page-bg-color)]" v-if="!pageLoading && config.is_open == 1">
      <view class="sidebar-margin pt-[var(--top-m)]">
        <!-- 余额和金额输入卡片 - 美化后 -->
        <view class="card-template money-card">
          <view class="font-500 text-[30rpx] text-[#333] leading-[42rpx]">{{ t('availableBalance') }}</view>
          <view class="flex items-end justify-between mt-[16rpx]">
            <view class="flex items-baseline">
              <text class="text-[28rpx] text-[var(--text-color-light6)] leading-[40rpx]">{{ t('currency') }}</text>
              <text class="text-[56rpx] font-semibold text-[#333] ml-[2rpx]">{{ moneyFormat(cashOutMoney) }}</text>
            </view>
            <view class="text-[24rpx] leading-[36rpx] withdrawal-btn" @click="allMoney">{{ t('allMoney') }}</view>
          </view>
          
          <!-- 金额输入区域 - 美化后 -->
          <view class="amount-input-container">
            <text class="pt-[4rpx] text-[44rpx] text-[#333] iconfont iconrenminbiV6xx price-font "></text>
            <input type="digit" class="h-[76rpx] leading-[76rpx] pl-[10rpx] flex-1 font-500 text-[54rpx] bg-[#fff] amount-input" 
                   v-model="applyData.apply_money" maxlength="7"
                   :placeholder="applyData.apply_money?'':(t('enterAmount'))"
                   placeholder-class="apply-price" :adjust-position="false" 
                   @blur="onAmountBlur" />
            <text v-if="Number(serviceMoney)" class="text-[24rpx] text-[var(--text-color-light6)] mr-[20rpx]">{{ t('fee') }}{{ serviceMoney }}</text>
            <text @click="clearMoney" v-if="applyData.apply_money" class="nc-iconfont nc-icon-cuohaoV6xx1 !text-[32rpx] text-[var(--text-color-light9)] clear-btn"></text>
          </view>
          <view class="pt-[12rpx] flex items-center justify-between px-[4rpx]">
            <view class="text-[24rpx] text-[var(--text-color-light6)] leading-[36rpx]">
              <text>{{ t('minAmount') }}{{ t('currency') }}{{ moneyFormat(config.min) }}</text>
              <text>{{ t('feeRate') }}{{ config.rate + '%' }}</text>
            </view>
          </view>
        </view>

        <!-- 提现方式卡片 - 美化后 -->
        <view class="mt-[20rpx] card-template payment-methods-card">
          <view class="font-500 text-[30rpx] text-[#333] leading-[42rpx] mb-[30rpx]">{{ t('paymentMethod') }}</view>
          
          <!-- 提现到微信 -->
          <view class="payment-method-item mb-[20rpx]" 
                v-if="config.transfer_type.includes('wechatpay') && openId" 
                :class="{'selected-wechat': applyData.transfer_type == 'wechatpay'}"
                @click="transferWeixin">
            <view>
              <image class="h-[60rpx] w-[60rpx] align-middle" :src="img('static/resource/images/member/apply_withdrawal/wechat.png')" mode="widthFix" />
            </view>
            <view class="flex-1 px-[20rpx]">
              <view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('wechatPay') }}</view>
              <view class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">{{ t('wechatPayDesc') }}</view>
            </view>
            <view class="flex items-center">
              <view class="check-icon" v-if="applyData.transfer_type == 'wechatpay'"></view>
            </view>
          </view>

          <!-- 提现到微信收款码 -->
          <view class="payment-method-item mb-[20rpx]" 
                v-if="config.transfer_type.includes('wechat_code')" 
                :class="{'selected-wechat-code': applyData.transfer_type == 'wechat_code' && wechatCodeInfo}">
            <view @click="transferWechatCode">
              <image class="h-[60rpx] w-[60rpx] align-middle" :src="img('static/resource/images/member/apply_withdrawal/wechat_code.png')" mode="widthFix" />
            </view>
            <view class="flex-1 px-[22rpx]" @click="transferWechatCode">
              <view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('wechatCode') }}</view>
              <view class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
                <view v-if="wechatCodeInfo" class="truncate max-w-[440rpx]">
                  <text>{{ t('wechatCodeDesc') }}</text>
                  <text class="text-[#333]">{{ wechatCodeInfo.account_no }}</text>
                </view>
                <view v-else>{{ t('addWechat') }}</view>
              </view>
            </view>
            <view class="flex items-center">
              <view class="check-icon" v-if="applyData.transfer_type == 'wechat_code' && wechatCodeInfo"></view>
              <button v-if="!wechatCodeInfo && !wechatCodeLoading" hover-class="none" class="add-account-btn" 
                      @click="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'wechat_code', mode: 'select' }, mode: 'redirectTo' })">添加</button>
              <text v-else class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]" 
                    @click.stop="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'wechat_code', mode: 'select' }, mode: 'redirectTo' })"></text>
            </view>
          </view>

          <!-- 提现到支付宝 -->
          <view class="payment-method-item mb-[20rpx]" 
                v-if="config.transfer_type.includes('alipay')" 
                :class="{'selected-alipay': applyData.transfer_type == 'alipay' && alipayAccountInfo}">
            <view @click="transferAlipay">
              <image class="h-[60rpx] w-[60rpx] align-middle" :src="img('static/resource/images/member/apply_withdrawal/alipay-icon.png')" mode="widthFix" />
            </view>
            <view class="flex-1 px-[22rpx]" @click="transferAlipay">
              <view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('alipay') }}</view>
              <view class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
                <view v-if="alipayAccountInfo" class="truncate max-w-[440rpx]">
                  {{ t('alipayDesc') }}
                  <text class="text-[#333]">{{ alipayAccountInfo.account_no }}</text>
                </view>
                <view v-else>{{ t('addAlipay') }}</view>
              </view>
            </view>
            <view class="flex items-center">
              <view class="check-icon" v-if="applyData.transfer_type == 'alipay' && alipayAccountInfo"></view>
              <!-- 添加支付宝账号的按钮 -->
              <button hover-class="none" class="add-account-btn" 
                      v-if="!alipayAccountInfo && !alipayLoading" 
                      @click="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'alipay', mode: 'select' }, mode: 'redirectTo' })">{{ t('add') }}</button>
              <text v-else class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]" 
                    @click.stop="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'alipay', mode: 'select' }, mode: 'redirectTo' })"></text>
            </view>
          </view>

          <!-- 提现到银行卡 -->
          <view class="payment-method-item" 
                v-if="config.transfer_type.includes('bank')" 
                :class="{'selected-bank': applyData.transfer_type == 'bank' && bankAccountInfo}">
            <view @click="transferBank">
              <image class="h-[42rpx] w-[60rpx] align-middle" :src="img('static/resource/images/member/apply_withdrawal/bank-icon.png')" mode="widthFix" />
            </view>
            <view class="flex-1 px-[20rpx]" @click="transferBank">
              <view class="text-[28rpx] text-[#333] leading-[40rpx] mb-[6rpx]">{{ t('bank') }}</view>
              <view class="text-[var(--text-color-light9)] text-[24rpx] leading-[34rpx]">
                <view v-if="bankAccountInfo" class="truncate max-w-[440rpx]">
                  <text>{{ t('bankDesc', { bankName: bankAccountInfo.bank_name }) }}</text>
                  <text class="text-[#333]">{{ bankAccountInfo.account_no.substring(bankAccountInfo.account_no.length - 4) }}</text>
                </view>
                <view v-else>{{ t('addBank') }}</view>
              </view>
            </view>
            <view class="flex items-center">
              <view class="check-icon" v-if="applyData.transfer_type == 'bank' && bankAccountInfo"></view>
              <button hover-class="none" class="add-account-btn" 
                      v-if="!bankAccountInfo && !bankLoading" 
                      @click="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'bank', mode: 'select' }, mode: 'redirectTo' })">{{ t('add') }}</button>
              <text v-else class="nc-iconfont nc-icon-youV6xx text-[28rpx] text-[var(--text-color-light9)] p-[10rpx]" 
                    @click.stop="redirect({ url: '/addon/home_service/store/pages/store/account/withdraw_list', param: { type: 'bank', mode: 'select' }, mode: 'redirectTo' })"></text>
            </view>
          </view>
        </view>

        <view class="tab-bar-placeholder"></view>
        <!-- 底部固定操作栏 - 美化后 -->
        <view class="fixed bottom-[0] tab-bar left-0 right-0 px-[var(--sidebar-m)] bg-[var(--page-bg-color)]">
          <view class="fixed bottom-[0] tab-bar left-0 right-0 px-[var(--sidebar-m)] bg-[var(--page-bg-color)]">
          	<button
          		class="h-[80rpx] !text-[#fff] leading-[80rpx] !bg-[var(--technician-bg-one)] rounded-[10rpx] text-[26rpx] withdraw-submit-btn"
          		:disabled="!canSubmit" :loading="loading" @click="cashOut">{{ t('submit') }}</button>
          	<button
          		class="h-[80rpx] !text-[#000] leading-[80rpx] !bg-[transparent] rounded-[50rpx] text-[26rpx]"
          		@click="redirect({url:'/addon/home_service/store/pages/member/cash/cash_out'})">提现记录</button><!-- 修改disabled属性 -->
          </view>
    
        </view>
      </view>
    </scroll-view>
    
    <view class="h-[100vh] w-[100vw] bg-[var(--page-bg-color)] overflow-hidden" v-if="config.is_open == 0 && !pageLoading">
      <view class="empty-page">
        <image class="mb-[10rpx]"
        	:src="img('addon/home_service/withdraw_close.png')" mode="widthFix"></image>
        <view class="desc">{{ t('notOpen') }}</view>
      </view>
    </view>
    
    <loading-page :loading="pageLoading"></loading-page>
  </view>
</template>

<script lang="ts" setup>
import { ref, reactive, watch, computed } from 'vue'
import { t } from '@/locale'
import { moneyFormat, redirect, getToken, img, deepClone, getWinxinOpenId } from '@/utils/common'
import useMemberStore from '@/stores/member'

import { getStoreInfo } from '@/addon/home_service/store/api/store'

import { cashOutConfig, getFirstCashOutAccountInfo, cashOutApply, getCashoutAccountInfo } from '@/addon/home_service/store/api/account'

import { onLoad, onShow } from '@dcloudio/uni-app'

// 主题颜色处理函数 - 门店页面使用黄色主题
const themeColor = () => {
  return {
    '--store-bg-one': '#FBD700' // 黄色主题色
  }
}

const pageLoading = ref(true)
const loading = ref(false)
const memberStore = useMemberStore()
const storeInfo = ref<any>(null)

// 申请提现数据
const applyData = reactive({
    apply_money: '',
    transfer_type: '',
    account_type: 'commission', // 修改默认值为commission
    account_id: 0,
    transfer_payee: {
        open_id: '',
        channel: ''
    }
})

// 修改：可提现金额计算方式
const cashOutMoney = computed(() => {
    // 从storeInfo中的commission获取可提现余额
    return storeInfo.value ? storeInfo.value.commission : 0
})

watch(() => applyData.transfer_type, (nval) => {
    switch (nval) {
        case 'bank':
            applyData.account_id = bankAccountInfo.value ? bankAccountInfo.value.account_id : 0
            break;
        case 'alipay':
            applyData.account_id = alipayAccountInfo.value ? alipayAccountInfo.value.account_id : 0
            break;
        case 'wechat_code':
            applyData.account_id = wechatCodeInfo.value ? wechatCodeInfo.value.account_id : 0
            break;
        default:
            applyData.account_id = 0
    }
}, { immediate: true })

const config = reactive<AnyObject>({
    is_auto_transfer: 0, // 是否自动转账
    is_auto_verify: 0, // 是否自动审核
    is_open: 0, // 是否启用提现
    min: 0, // 最低提现金额
    rate: 0, // 手续费比率
    transfer_type: [] // 提现方式
})

let query: any = {}
let openId = ''
onLoad(async(data) => {
    query = data
    openId = uni.getStorageSync('openid') || ''
    uni.getStorageSync('cashOutAccountType') && (applyData.account_type = uni.getStorageSync('cashOutAccountType'));

    // 强制使用commission作为账户类型
    applyData.account_type = 'commission';
    
    if (!['money', 'commission'].includes(applyData.account_type)) {
        uni.showToast({
            title: t('abnormal'),
            icon: 'none',
            success() {
                setTimeout(() => {
                    if (getCurrentPages().length > 1) {
                        uni.navigateBack({
                            delta: 1
                        });
                    } else {
                        redirect({
                            url: '/app/pages/member/index',
                            mode: 'reLaunch'
                        });
                    }
                }, 1500)
            }
        })
        return;
    }

    // 提现配置
    await cashOutConfig().then(async (res: any) => {
        for (let key in deepClone(res.data)) {
            config[key] = deepClone(res.data[key]);
        }
        if (config.transfer_type.includes('wechatpay') && !openId) {
            config.transfer_type.splice(config.transfer_type.indexOf('wechatpay'), 1)
        } else {
            config.transfer_type.includes('wechatpay') && transferWeixin()
        }
        config.transfer_type.includes('bank') && getBankAccountInfo()
        config.transfer_type.includes('alipay') && getAlipayAccountInfo()
        config.transfer_type.includes('wechat_code') && getWechatCodeInfo()
        applyData.transfer_type = config.transfer_type[0]
        if (query.type) {
            applyData.transfer_type = query.type
        }
        // 新增：获取门店信息，获取可提现余额
        await getStoreInfo().then((storeRes: any) => {
            if (storeRes.code === 1 && storeRes.data) {
                storeInfo.value = storeRes.data
            }
        })
        
        pageLoading.value = false
    })
})

onShow(() => {
    if (getToken()) {
        memberStore.getMemberInfo()
    }
})
// 手续费
const serviceMoney = computed(() => {
    let money = 0
    if (applyData.apply_money && Number(config.rate)) {
        money = Number(applyData.apply_money) * Number(config.rate) / 100
    }
    return money.toFixed(2);
})

//全部提现
const allMoney = () => {
    if (parseFloat(cashOutMoney.value)) applyData.apply_money = moneyFormat(cashOutMoney.value)
}

// 清空提现金额
const clearMoney = () => {
    applyData.apply_money = '';
}

const verify = () => {
    if (!applyData.transfer_type) {
        uni.showToast({ title: t('noMethod'), icon: 'none' })
        return false
    }
    if (uni.$u.test.isEmpty(applyData.apply_money)) {
        uni.showToast({ title: t('enterAmount'), icon: 'none' })
        return false
    }
    if (!uni.$u.test.amount(applyData.apply_money)) {
        uni.showToast({ title: t('invalidFormat'), icon: 'none' })
        return false
    }
    if (parseFloat(applyData.apply_money) > parseFloat(cashOutMoney.value)) {
        uni.showToast({ title: t('exceedLimit'), icon: 'none' })
        return false
    }
    if (parseFloat(applyData.apply_money) < parseFloat(config.min)) {
        uni.showToast({ title: t('belowMin'), icon: 'none' })
        return false
    }
    return true;
}

/**
 * 获取支付宝提现账号信息
 */
const alipayLoading = ref(false)
const alipayAccountInfo: any = ref(null)
const getAlipayAccountInfo = () => {
    const data = { account_type: 'alipay', account_id: 0 }
    let request = getFirstCashOutAccountInfo

    if (query.type && query.type == 'alipay' && query.account_id) {
        request = getCashoutAccountInfo
        data.account_id = query.account_id
    }
    alipayLoading.value = true
    request(data).then((res: any) => {
        if (res.data && res.data.account_id) {
            alipayAccountInfo.value = res.data
            // 初始化赋值
            if (applyData.transfer_type == 'alipay' && !applyData.account_id) {
                applyData.account_id = alipayAccountInfo.value.account_id;
            }
        }
        alipayLoading.value = false
    })
}

/**
 * 获取银行卡提现账号信息
 */
const bankLoading = ref(false)
const bankAccountInfo: any = ref(null)
const getBankAccountInfo = () => {
    const data = { account_type: 'bank', account_id: 0 }
    let request = getFirstCashOutAccountInfo

    if (query.type && query.type == 'bank' && query.account_id) {
        request = getCashoutAccountInfo
        data.account_id = query.account_id
    }
    bankLoading.value = true
    request(data).then((res: any) => {
        if (res.data && res.data.account_id) {
            bankAccountInfo.value = res.data
            // 初始化赋值
            if (applyData.transfer_type == 'bank' && !applyData.account_id) {
                applyData.account_id = bankAccountInfo.value.account_id;
            }
        }
        bankLoading.value = false
    })
}


/**
 * 获取微信收款码提现账号信息
 */
const wechatCodeLoading = ref(false)
const wechatCodeInfo: any = ref(null)
const getWechatCodeInfo = () => {
    const data = { account_type: 'wechat_code', account_id: 0 }
    let request = getFirstCashOutAccountInfo

    if (query.type && query.type == 'wechat_code' && query.account_id) {
        request = getCashoutAccountInfo
        data.account_id = query.account_id
    }
    wechatCodeLoading.value = true
    request(data).then((res: any) => {
        if (res.data && res.data.account_id) {
            wechatCodeInfo.value = res.data
            // 初始化赋值
            if (applyData.transfer_type == 'wechat_code' && !applyData.account_id) {
                applyData.account_id = wechatCodeInfo.value.account_id;
            }
        }
        wechatCodeLoading.value = false
    })
}

/**
 * 申请提现
 */
const cashOut = () => {
    // 额外增加一次手动验证，确保在任何情况下都能显示提示
    if (!applyData.transfer_type) {
        uni.showToast({ title: t('noMethod'), icon: 'none' })
        return
    }
    
    if (uni.$u.test.isEmpty(applyData.apply_money) || Number(applyData.apply_money) <= 0) {
        uni.showToast({ title: t('enterAmount'), icon: 'none' })
        return
    }
    
    // 调用原有的verify函数进行更详细的验证
    if (!verify()) {
        return; // 验证失败时直接返回，不继续执行
    }
    
    if (loading.value) return
    loading.value = true

    cashOutApply(applyData).then((res: any) => {
        loading.value = false
        // 提现成功后重新获取门店信息以更新余额
        getStoreInfo().then((storeRes: any) => {
            if (storeRes.code === 1 && storeRes.data) {
                storeInfo.value = storeRes.data
            }
        })

        redirect({
            url: '/addon/home_service/store/pages/member/cash/cash_out'
        })
        //  redirect({
        //     url: '/addon/home_service/store/pages/store/account/account_statement'
        // })
    }).catch(() => {
        loading.value = false
    })
}

// 选中提现到支付宝
const transferAlipay = () => {
    if (!alipayAccountInfo.value) {
        uni.showToast({ title: t('addAlipay'), icon: 'none' })
        return false
    }
    applyData.transfer_type = 'alipay'
}
// 选中提现到银行卡
const transferBank = () => {
    if (!bankAccountInfo.value) {
        uni.showToast({ title: t('addBank'), icon: 'none' })
        return false
    }
    applyData.transfer_type = 'bank'
}
// 选中提现到微信
const transferWeixin = () => {
    // let data = getWinxinOpenId();

    // applyData.transfer_payee.open_id = data.wechat ? data.wechat : data.weapp;
    // applyData.transfer_payee.channel = data.wechat ? 'wechat' : 'weapp';

    applyData.transfer_type = 'wechatpay'
}

// 选中提现到微信收款码
const transferWechatCode = () => {
    if (!wechatCodeInfo.value) {
        uni.showToast({ title: t('addWechat'), icon: 'none' })
        return false
    }
    applyData.transfer_type = 'wechat_code'
}
// 添加到script setup部分
const onAmountBlur = () => {
    if (applyData.apply_money) {
        verify();
    }
}
// 添加实时验证提示
watch([() => applyData.apply_money, () => applyData.transfer_type], () => {
    // 可以在这里添加实时提示逻辑
}, { immediate: true })
// 添加缺失的canSubmit计算属性
const canSubmit = computed(() => {
    // 检查是否选择了提现方式
    if (!applyData.transfer_type) {
        return false
    }
    
    // 检查是否输入了金额
    if (!applyData.apply_money || Number(applyData.apply_money) <= 0) {
        return false
    }
    
    // 检查提现方式对应的账号信息是否存在
    if (applyData.transfer_type === 'bank' && !bankAccountInfo.value) {
        return false
    }
    if (applyData.transfer_type === 'alipay' && !alipayAccountInfo.value) {
        return false
    }
    if (applyData.transfer_type === 'wechat_code' && !wechatCodeInfo.value) {
        return false
    }
    
    return true
})
</script>

<style lang="scss" scoped>
:deep(.apply-price) {
    color: var(--text-color-light9);
    font-size: 26rpx;
    font-weight: normal;
    line-height: 76rpx;
}

.tab-bar-placeholder {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 166rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 166rpx);
}

.tab-bar {
    padding-bottom: calc(constant(safe-area-inset-bottom) + 30rpx);
    padding-bottom: calc(env(safe-area-inset-bottom) + 30rpx);
}

/* 新增的美化样式 */
.withdraw-page {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
}

/* 顶部装饰背景 */
.top-decoration {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 260rpx;
    background: linear-gradient(135deg, #FBD700 0%, #F6BC00 100%);
    border-radius: 0 0 40rpx 40rpx;
    z-index: 0;
}

/* 余额和金额输入卡片 */
.money-card {
    position: relative;
    background: #FFFFFF;
    border-radius: 24rpx;
    padding: 30rpx;
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.06);
    overflow: hidden;
    transition: all 0.3s ease;
    z-index: 1;
}

.money-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 8rpx;
    background: linear-gradient(90deg, #FBD700, #F6BC00);
}

/* 全部提现按钮 */
.withdrawal-btn {
    padding: 8rpx 20rpx;
    background-color: rgba(251, 215, 0, 0.1);
    border-radius: 100rpx;
    transition: all 0.2s ease;
}

.withdrawal-btn:active {
    background-color: rgba(251, 215, 0, 0.2);
    transform: scale(0.95);
}

/* 金额输入区域 */
.amount-input-container {
    display: flex;
    align-items: center;
    padding: 24rpx 0 16rpx;
    border-bottom: 2rpx solid #F1F2F5;
    margin-top: 20rpx;
}

.amount-input {
    border: none;
    outline: none;
    background: transparent;
    transition: all 0.3s ease;
}

/* 清空按钮 */
.clear-btn {
    padding: 8rpx;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-btn:active {
    background-color: #F1F2F5;
}

/* 提现方式卡片 */
.payment-methods-card {
    position: relative;
    background: #FFFFFF;
    border-radius: 24rpx;
    padding: 30rpx;
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.06);
    z-index: 1;
}

/* 支付方式项 */
.payment-method-item {
    display: flex;
    align-items: center;
    padding: 24rpx 0;
    border-bottom: 2rpx solid #F1F2F5;
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-method-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.payment-method-item:active {
    background-color: #F8F9FA;
}

/* 选中状态样式 */
.selected-wechat,
.selected-wechat-code,
.selected-alipay,
.selected-bank {
    background-color: rgba(251, 215, 0, 0.05);
    border-radius: 16rpx;
    margin: -10rpx 0;
    padding: 34rpx 0;
}

/* 选中图标 */
.check-icon {
    width: 36rpx;
    height: 36rpx;
    background: #FBD700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.check-icon::after {
    content: '';
    width: 16rpx;
    height: 10rpx;
    border-left: 3rpx solid #FFFFFF;
    border-bottom: 3rpx solid #FFFFFF;
    transform: rotate(-45deg);
}

/* 添加账户按钮 */
.add-account-btn {
    background-color: #FBD700;
    color: #FFFFFF;
    font-size: 24rpx;
    padding: 0rpx 34rpx;
    border-radius: 100rpx;
    transition: all 0.2s ease;
}

.add-account-btn:active {
    background-color: #F6BC00;
    transform: scale(0.95);
}

/* 提现按钮 */
.withdraw-submit-btn {
    background: linear-gradient(135deg, #FBD700 0%, #F6BC00 100%);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.withdraw-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: all 0.6s ease;
}

.withdraw-submit-btn:active {
    transform: scale(0.98);
    box-shadow: 0 8rpx 16rpx rgba(251, 215, 0, 0.2);
}

.withdraw-submit-btn:active::before {
    left: 100%;
}

/* 提现记录按钮 */
.withdraw-record-btn {
    transition: all 0.2s ease;
}

.withdraw-record-btn:active {
    opacity: 0.7;
}

/* 空状态页面 */
.empty-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 0 40rpx;
}

.empty-page .img {
    width: 240rpx;
    height: 240rpx;
    margin-bottom: 30rpx;
}

.empty-page .desc {
    font-size: 28rpx;
    color: var(--text-color-light6);
}

/* 适配安全区域 */
@media screen and (device-width: 375px) and (device-height: 812px) {
    .tab-bar-placeholder {
        padding-bottom: calc(constant(safe-area-inset-bottom) + 180rpx);
        padding-bottom: calc(env(safe-area-inset-bottom) + 180rpx);
    }
    
    .tab-bar {
        padding-bottom: calc(constant(safe-area-inset-bottom) + 40rpx);
        padding-bottom: calc(env(safe-area-inset-bottom) + 40rpx);
    }
}
// 在style部分添加
.withdraw-submit-btn {
    transition: all 0.3s ease;
    &:disabled {
        background-color: #ccc !important;
        opacity: 0.7;
    }
}
</style>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>
