<template>
    <view class="income-page" :style="themeColor()">
        <CooperationNav title="我的收益" :back="hasPreviousPage" :actions="[{ key: 'orders', label: '付款单', ariaLabel: '我的付款单' }, { key: 'refresh', icon: 'reload', ariaLabel: '刷新收益' }]" @back="backCooperationTask" @action="navAction" />
        <CoopIncomePanel v-if="loggedIn" ref="panelRef" :active="active" :initial-type="initialType" :initial-status="initialStatus" @overlay="overlay = $event" />
        <view v-else class="income-page__login"><CoopState title="登录后查看收益" hint="普通会员也可以邀请朋友赚取佣金" icon="account" /><u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" text="登录" @click="login" /></view>
        <view class="income-page__tabbar"><FrameworkTabbar v-if="!overlay" addon="hsx_lead" /></view>
    </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onShow } from '@dcloudio/uni-app'
import { getToken } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import FrameworkTabbar from '@/components/tabbar/tabbar.vue'
import CoopIncomePanel from '../../components/cooperation/CoopIncomePanel.vue'
import CooperationNav from '../../components/cooperation/CooperationNav.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { backCooperationTask } from '../../utils/cooperation-navigation'
import { cooperationIncomeType } from '../../utils/cooperation-income'
const panelRef = ref<any>(null), loggedIn = ref(!!getToken()), active = ref(false), overlay = ref(false), hasPreviousPage = ref(false), initialType = ref('all'), initialStatus = ref('')
onLoad(options => { initialType.value = cooperationIncomeType(options?.income_type); initialStatus.value = String(options?.status || ''); hasPreviousPage.value = getCurrentPages().length > 1 })
onShow(() => { loggedIn.value = !!getToken(); active.value = true })
onHide(() => { active.value = false; overlay.value = false })
onPullDownRefresh(() => { refresh(); uni.stopPullDownRefresh() })
function refresh() { panelRef.value?.refresh?.() }
function navAction(key: string) { if (key === 'orders') uni.navigateTo({ url: '/addon/hsx_lead/pages/orders/index' }); else refresh() }
function login() { useLogin().setLoginBack({ url: '/addon/hsx_lead/pages/wallet/index', param: { income_type: initialType.value, status: initialStatus.value }, mode: 'redirectTo' }) }
</script>
<style scoped lang="scss">
.income-page { height:calc(100vh - var(--window-top,0px) - var(--window-bottom,0px));display:flex;flex-direction:column;overflow:hidden;background:#f5f6f8;min-height:0; }
.income-page__login { flex:1;min-height:0;padding:40px 24px;box-sizing:border-box; }.income-page__login>.u-button { margin-top:20px; }
.income-page__tabbar { flex:none;min-height:calc(54px + env(safe-area-inset-bottom)); }.income-page__tabbar :deep(.tab-bar-placeholder),.income-page__tabbar :deep(.u-tabbar__placeholder) { display:none; }
/* #ifdef H5 */
@supports(height:100dvh){.income-page{height:calc(100dvh - var(--window-top,0px) - var(--window-bottom,0px));}}
/* #endif */
</style>
