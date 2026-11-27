<template>
    <view class="account-notice-page" :style="themeColor()">
        <CooperationNav title="通知偏好" back @back="backCooperationTask" />
        <scroll-view scroll-y class="account-notice-scroll" :show-scrollbar="false">
            <view class="account-notice-content">
                <CoopState v-if="!state.loggedIn.value" title="登录后管理自己的提醒" hint="通知仅发送给相关账户本人" icon="bell" />
                <CoopState v-else-if="state.loading.value && !state.ready.value" loading loading-text="正在读取通知偏好…" />
                <CoopState v-else-if="state.error.value" :error="state.error.value" @retry="state.load" />
                <template v-else-if="state.ready.value">
                    <view class="account-notice-intro"><u-icon name="bell" size="24" color="var(--primary-color,#d93833)" /><view><text>重要变动，及时知晓</text><text>按场景选择你希望接收的提醒</text></view></view>
                    <view v-for="event in state.events.value" :key="event.event_key" class="account-notice-card">
                        <view class="account-notice-heading"><text>{{ event.name }}</text><button v-if="state.supported.value" :disabled="!state.canSubscribeEvent(event.event_key)" @tap="state.subscribe(event.event_key)">订阅此项</button></view>
                        <view v-for="channel in event.channels" :key="channel.channel" class="account-notice-channel"><view><text>{{ channelNames[channel.channel] }}</text><text>{{ !channel.configured ? '平台暂未开通' : channel.channel === 'weapp' ? '需微信订阅授权' : channel.channel === 'wechat' ? '需关注并绑定公众号' : '发送至已绑定手机号' }}</text></view><u-switch :model-value="channel.enabled" :disabled="state.loading.value || state.saving.value || state.readRequired.value || state.requesting.value" active-color="var(--primary-color,#d93833)" :aria-label="`${event.name}${channelNames[channel.channel]}`" @change="state.change(event.event_key,channel.channel,$event)" /></view>
                    </view>
                    <view v-if="state.saving.value" class="account-notice-feedback" role="status"><u-loading-icon size="15" /><text>正在保存偏好…</text></view>
                    <view v-if="state.saveError.value" class="account-notice-error" role="alert"><text>{{ state.saveError.value }}</text><button :disabled="state.loading.value || state.saving.value" @tap="state.load">重新读取并核对</button></view>
                    <view v-if="state.subscriptionMessage.value" class="account-notice-message" role="status">{{ state.subscriptionMessage.value }}</view>
                    <u-collapse :border="false" class="account-notice-help"><u-collapse-item title="关于提醒与微信订阅" name="notice-help"><view>偏好开启后，仍需平台开通对应渠道。微信小程序提醒还需要你主动确认订阅；拒绝、取消或额度用尽后，系统不会强制反复弹出。</view><view>公众号提醒需关注并绑定本站服务号。短信仅在你主动开启偏好后发送。</view><view>账户变动以账目记录为准，消息发送失败不会改变你的余额或佣金。</view></u-collapse-item></u-collapse>
                </template>
            </view>
        </scroll-view>
        <view v-if="state.ready.value" class="account-notice-footer"><u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" :text="!state.supported.value ? '请在微信小程序内订阅' : !state.eligible.value.length ? '暂无可订阅的微信提醒' : state.eligible.value.length>3 ? '分组订阅微信提醒' : '订阅微信提醒'" :loading="state.requesting.value" :disabled="!state.canSubscribe.value" @click="state.subscribe()" /><text>{{ state.eligible.value.length>3 ? '每次最多3项，也可在上方逐项订阅；偏好开关不代表已授权' : '偏好开关不代表已获得微信订阅授权' }}</text></view>
    </view>
</template>
<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { onHide, onShow } from '@dcloudio/uni-app'
import CooperationNav from '../../components/cooperation/CooperationNav.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import { useAccountNoticePreferences } from '../../composables/useAccountNoticePreferences'
import { backCooperationTask } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
const state=useAccountNoticePreferences()
const channelNames:Record<string,string>={weapp:'微信小程序',wechat:'微信公众号',sms:'短信'}
onShow(()=>{state.resume();void state.load()})
onHide(state.pause)
onBeforeUnmount(state.dispose)
</script>
<style scoped lang="scss">
.account-notice-heading{display:flex;align-items:center;justify-content:space-between;gap:20rpx}.account-notice-heading>button{margin:-10rpx 0;padding:0 12rpx;min-height:44px;border:0;background:transparent;color:var(--primary-color,#d93833);font-size:24rpx;font-weight:400;line-height:44px;flex:none}.account-notice-heading>button::after{border:0}.account-notice-heading>button[disabled]{color:#b4b4b4;background:transparent}
.account-notice-page{position:fixed;inset:0;display:flex;flex-direction:column;background:#f6f6f6;color:#202124;overflow:hidden}.account-notice-scroll{flex:1;height:0;min-height:0}.account-notice-content{padding:24rpx 24rpx 32rpx}.account-notice-intro{display:flex;align-items:center;gap:24rpx;padding:12rpx 8rpx 28rpx}.account-notice-intro>view{display:flex;flex-direction:column;gap:8rpx}.account-notice-intro text:first-child{font-size:34rpx;font-weight:600}.account-notice-intro text:last-child{font-size:24rpx;color:#858585}.account-notice-card{padding:0 28rpx;margin-bottom:20rpx;background:#fff;border:1rpx solid #eee;border-radius:18rpx}.account-notice-heading{padding:28rpx 0 22rpx;font-size:30rpx;font-weight:600;border-bottom:1rpx solid #eee}.account-notice-channel{display:flex;align-items:center;justify-content:space-between;gap:24rpx;min-height:116rpx;border-bottom:1rpx solid #f3f3f3}.account-notice-channel:last-child{border-bottom:0}.account-notice-channel>view{display:flex;flex-direction:column;gap:8rpx}.account-notice-channel text:first-child{font-size:28rpx}.account-notice-channel text:last-child{font-size:22rpx;color:#949494;line-height:1.5}.account-notice-feedback{display:flex;align-items:center;justify-content:center;gap:12rpx;font-size:24rpx;color:#888;padding:16rpx}.account-notice-error,.account-notice-message{padding:24rpx;border-radius:12rpx;font-size:24rpx;line-height:1.8;margin-bottom:20rpx;background:#fff}.account-notice-error{color:#c4473f;background:#fff3f1}.account-notice-error>button{margin:16rpx 0 0;background:#fff;border:1rpx solid #ead6d2;color:#a43f35;font-size:24rpx;line-height:72rpx;border-radius:12rpx}.account-notice-error>button::after{border:0}.account-notice-message{color:#666;border:1rpx solid #eee}.account-notice-help{display:block;background:#fff;border-radius:16rpx}.account-notice-help view{font-size:24rpx;line-height:1.9;color:#888;margin-bottom:12rpx}.account-notice-footer{flex:none;background:#fff;padding:24rpx 28rpx calc(20rpx + env(safe-area-inset-bottom));border-top:1rpx solid #eee;display:flex;flex-direction:column;align-items:stretch;gap:16rpx}.account-notice-footer>text{text-align:center;font-size:22rpx;color:#999;line-height:1.5}
</style>
