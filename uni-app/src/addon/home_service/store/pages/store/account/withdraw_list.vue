<template>
  <view class="withdraw-list-page">
    <!-- 页面标题 -->
    <view class="header">
      <text class="title-text">{{ getTitle() }}</text>
    </view>
    
    <!-- 内容区域 -->
    <view class="content">
      <!-- 账户列表 -->
      <template v-if="accountList.length > 0">
        <view class="account-list">
          <u-swipe-action-item v-for="(item, index) in accountList" :key="index" :options="accountOptions" @click="swipeClick(index)" class="mb-[24rpx]">
            <view class="account-item" @click="handleClick(item)">
              <view class="account-icon">
                <image class="icon-image" :src="getAccountIcon(item.account_type)" mode="widthFix" />
              </view>
              <view class="account-info">
                <view class="account-name">{{ getAccountTypeName(item.account_type) }}</view>
                <view class="account-detail">
                  <text v-if="item.account_type == 'bank'">
                    {{ t('endNumber') }} {{ item.account_no.substring(item.account_no.length - 4) }}{{ t('bankCard') }}
                  </text>
                  <text v-else>{{ formatAccountNumber(item.account_no) }}</text>
                </view>
              </view>
              <view class="account-actions">
                <text class="edit-icon nc-iconfont nc-icon-xiugaiV6xx" @click.stop="editAccount(item)"></text>
              </view>
            </view>
          </u-swipe-action-item>
        </view>
      </template>
      
      <!-- 加载状态 -->
      <view v-else-if="!loading" class="loading-state">
        <image class="loading-icon" :src="img('static/resource/images/member/loading.gif')" mode="aspectFit" />
      </view>
      
      <!-- 空状态 -->
      <view v-else class="empty-state">
        <image class="empty-icon" :src="img('static/resource/images/member/empty_account.png')" mode="widthFix" />
        <text class="empty-title">{{ getEmptyTitle() }}</text>
        <text class="empty-desc">{{ t('noAccountDesc') }}</text>
      </view>
    </view>
    
    <!-- 添加账户按钮 - 参考store_form.vue实现，固定在底部 -->
    <view class="add-button-container">
      <button hover-class="none" class="add-account-button" @click="addAccount">
        <text class="add-icon nc-iconfont nc-icon-jiahaoV6xx"></text>
        <text class="add-text">{{ getAddButtonText() }}</text>
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { redirect, img } from '@/utils/common'
import { getCashOutAccountList, deleteCashoutAccount } from '@/addon/home_service/store/api/account'
import { onPageScroll, onReachBottom, onLoad } from '@dcloudio/uni-app'
import { t } from '@/locale'

// 定义 AnyObject 类型
interface AnyObject {
    [key: string]: any
}

const accountList = ref<Array<any>>([])
const loading = ref(false)
const accountType = ref('bank')
const mode = ref('get')

onLoad((data: any) => {
    data.type && (accountType.value = data.type)
    data.mode && (mode.value = data.mode)
    
    // 初始化加载数据
    loadData()
})

const loadData = () => {
    loading.value = false;
    let data = {
        page: 1,
        limit: 20,
        account_type: accountType.value
    };

    getCashOutAccountList(data).then((res: any) => {
        accountList.value = res.data.data || []
        loading.value = true
    }).catch(() => {
        loading.value = true
    })
}

const editAccount = (item: any) => {
    redirect({
        url: '/addon/home_service/store/pages/store/account/withdraw_edit',
        param: { id: item.account_id, type: item.account_type, mode: mode.value }
    })
}

const addAccount = () => {
    redirect({
        url: '/addon/home_service/store/pages/store/account/withdraw_edit',
        param: { type: accountType.value, mode: mode.value }
    })
}

const accountOptions = ref([
    {
        text: t('delete'),
        style: {
            backgroundColor: '#F56C6C'
        }
    }
])

const swipeClick = (index: any) => {
    const data = accountList.value[index]
    deleteCashoutAccount(data.account_id).then(() => {
        accountList.value.splice(index, 1)
    })
}

const handleClick = (data: AnyObject) => {
    if (mode.value == 'get') {
        redirect({
            url: '/addon/home_service/store/pages/store/account/withdraw_edit',
            param: { id: data.account_id, type: accountType.value, mode: mode.value },
            mode: 'redirectTo'
        })
    } else {
        redirect({
            url: '/addon/home_service/store/pages/store/account/withdraw',
            param: { account_id: data.account_id, type: accountType.value },
            mode: 'redirectTo'
        })
    }
}

// 获取账户图标
const getAccountIcon = (type: string) => {
    switch (type) {
        case 'bank':
            return img('static/resource/images/member/apply_withdrawal/bank-icon.png')
        case 'wechat_code':
            return img('static/resource/images/member/apply_withdrawal/wechat_code.png')
        case 'alipay':
            return img('static/resource/images/member/apply_withdrawal/alipay-icon.png')
        default:
            return ''
    }
}

// 格式化账号信息
const formatAccountNumber = (account: string) => {
    if (account.length <= 4) return account
    const start = account.substring(0, 2)
    const end = account.substring(account.length - 4)
    const middle = '*'.repeat(account.length - 6)
    return `${start}${middle}${end}`
}

// 获取页面标题
const getTitle = () => {
    switch (accountType.value) {
        case 'bank':
            return t('bankCardList')
        case 'wechat_code':
            return t('wechatCodeList')
        case 'alipay':
            return t('alipayAccountList')
        default:
            return ''
    }
}

// 获取账户类型名称
const getAccountTypeName = (type: string) => {
    switch (type) {
        case 'bank':
            return t('bankCard')
        case 'wechat_code':
            return t('wechatCode')
        case 'alipay':
            return t('alipayAccountNo')
        default:
            return ''
    }
}

// 获取空状态标题
const getEmptyTitle = () => {
    switch (accountType.value) {
        case 'bank':
            return t('noBankCard')
        case 'wechat_code':
            return t('noWechatCode')
        case 'alipay':
            return t('noAlipayAccount')
        default:
            return ''
    }
}

// 获取添加按钮文本
const getAddButtonText = () => {
    switch (accountType.value) {
        case 'bank':
            return t('addBankCard')
        case 'wechat_code':
            return t('addWechatCode')
        case 'alipay':
            return t('addAlipayAccount')
        default:
            return ''
    }
}
</script>

<style lang="scss">
@import '@/addon/home_service/store/style/index.scss';
</style>

<style lang="scss" scoped>
.withdraw-list-page {
    min-height: 100vh;
    background-color: #F5F7FA;
    padding-bottom: 120rpx; /* 为底部按钮留出空间 */
}

/* 页面标题 */
.header {
    background: linear-gradient(135deg, #3E82FC 0%, #2F59D9 100%);
    padding: 50rpx 40rpx 30rpx;
    border-radius: 0 0 40rpx 40rpx;
}

.title-text {
    font-size: 40rpx;
    font-weight: 600;
    color: #FFFFFF;
}

/* 内容区域 */
.content {
    padding: 30rpx 40rpx;
    min-height: 500rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* 账户列表 */
.account-list {
    width: 100%;
}

/* 账户项 */
.account-item {
    display: flex;
    align-items: center;
    padding: 30rpx;
    background: #FFFFFF;
    border-radius: 24rpx;
    // margin-bottom: 24rpx;
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.06);
}

/* 账户图标 */
.account-icon {
    width: 80rpx;
    height: 80rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #F5F7FA;
    border-radius: 16rpx;
    overflow: hidden;
}

.icon-image {
    max-width: 70%;
    max-height: 70%;
}

/* 账户信息 */
.account-info {
    flex: 1;
    margin-left: 20rpx;
}

.account-name {
    font-size: 28rpx;
    font-weight: 500;
    color: #333333;
    margin-bottom: 8rpx;
}

.account-detail {
    font-size: 24rpx;
    color: #999999;
    line-height: 34rpx;
}

/* 账户操作 */
.account-actions {
    display: flex;
    align-items: center;
}

.edit-icon {
    font-size: 28rpx;
    color: #999999;
    padding: 10rpx;
}

/* 空状态 */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
}

.empty-icon {
    width: 200rpx;
    height: 200rpx;
    margin-bottom: 30rpx;
}

.empty-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #333333;
    margin-bottom: 15rpx;
}

.empty-desc {
    font-size: 26rpx;
    color: #999999;
}

/* 加载状态 */
.loading-state {
    margin: 100rpx 0;
}

.loading-icon {
    width: 80rpx;
    height: 80rpx;
}

/* 添加账户按钮容器 - 参考store_form.vue的底部按钮实现 */
.add-button-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #FFFFFF;
    padding: 20rpx 40rpx;
    box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.06);
    /* 安全区域适配 */
    padding-bottom: calc(20rpx + constant(safe-area-inset-bottom));
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
}

/* 添加账户按钮 - 参考store_form.vue的按钮样式 */
.add-account-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    background: var(--store-bg-one, #3E82FC);
    color: #FFFFFF;
    height: 80rpx;
    line-height: 80rpx;
    border-radius: 40rpx;
    font-size: 28rpx;
    font-weight: 500;
}

.add-icon {
    font-size: 28rpx;
    margin-right: 10rpx;
}

.add-text {
    font-size: 28rpx;
    font-weight: 500;
}

/* 滑动删除样式 */
:deep(.u-swipe-action-item__right__button__wrapper) {
    padding: 0 30rpx !important;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.u-swipe-action-item__right__button__wrapper__text) {
    font-size: 28rpx !important;
    color: #FFFFFF !important;
    font-weight: 500;
}
</style>