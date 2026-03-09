<template>
    <view class="help-page">
        <view class="faq-section">
            <view class="section-title">常见问题</view>
            <view class="faq-item" v-for="(item, index) in faqList" :key="index" @click="toggleFaq(index)">
                <view class="faq-header">
                    <text class="faq-question">{{ item.question }}</text>
                    <u-icon :name="item.open ? 'arrow-up' : 'arrow-down'" size="16" color="#999"></u-icon>
                </view>
                <view class="faq-answer" v-if="item.open">
                    <text>{{ item.answer }}</text>
                </view>
            </view>
        </view>

        <view class="faq-section">
            <view class="section-title">接单流程</view>
            <view class="step-list">
                <view class="step-item" v-for="(step, idx) in steps" :key="idx">
                    <view class="step-num">{{ idx + 1 }}</view>
                    <view class="step-content">
                        <text class="step-title">{{ step.title }}</text>
                        <text class="step-desc">{{ step.desc }}</text>
                    </view>
                </view>
            </view>
        </view>

        <view class="faq-section">
            <view class="section-title">联系客服</view>
            <view class="contact-card">
                <text class="contact-desc">如有其他问题，请联系平台客服</text>
                <button class="contact-btn" @click="contactService">联系客服</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref } from 'vue'

const faqList = ref([
    {
        question: '如何开始接单？',
        answer: '在接单大厅中选择您想接的订单，点击"接单"按钮即可。接单前请确保您已开启在线状态。',
        open: false
    },
    {
        question: '接单后如何完成订单？',
        answer: '接单后按照订单要求完成任务，完成后在订单详情页点击"确认送达"并上传完成凭证图片即可。',
        open: false
    },
    {
        question: '收益什么时候到账？',
        answer: '订单完成后收益会即时到账到您的可提现余额中，您可以随时申请提现。',
        open: false
    },
    {
        question: '打赏收入如何计算？',
        answer: '用户打赏金额将全额发放给接单员，不收取任何手续费。打赏收入会即时到账。',
        open: false
    },
    {
        question: '如何提高接单评分？',
        answer: '按时完成订单、保持良好的服务态度、获得用户好评都可以提高您的评分。评分越高，获得订单的机会越多。',
        open: false
    },
    {
        question: '订单被取消怎么办？',
        answer: '如果用户在您接单前取消订单，不会影响您的任何数据。如果接单后用户取消，平台会根据实际情况进行处理。',
        open: false
    },
    {
        question: '如何修改接单范围？',
        answer: '在"接单设置"页面中，您可以选择愿意接的服务类型来调整接单范围。',
        open: false
    },
    {
        question: '提现多久到账？',
        answer: '提现申请提交后，一般1-3个工作日内到账。请确保您的提现账户信息正确。',
        open: false
    }
])

const steps = ref([
    { title: '开启在线状态', desc: '在接单员首页开启在线状态，开始接收订单推送' },
    { title: '选择订单接单', desc: '在接单大厅浏览可接订单，选择合适的订单点击接单' },
    { title: '前往取货', desc: '按照订单信息前往取货地点取货' },
    { title: '配送送达', desc: '将物品安全送达指定地点' },
    { title: '上传凭证完成', desc: '拍照上传完成凭证，确认订单完成，收益即时到账' }
])

const toggleFaq = (index: number) => {
    faqList.value[index].open = !faqList.value[index].open
}

const contactService = () => {
    uni.showModal({
        title: '联系客服',
        content: '如需帮助请联系平台客服微信或拨打客服电话',
        showCancel: false,
        confirmText: '我知道了'
    })
}
</script>

<style lang="scss" scoped>
.help-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 60rpx;
}

.faq-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.faq-item {
    border-bottom: 1rpx solid #f0f0f0;
    padding: 20rpx 0;

    &:last-child {
        border-bottom: none;
    }
}

.faq-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.faq-question {
    font-size: 28rpx;
    color: #333;
    flex: 1;
    margin-right: 16rpx;
}

.faq-answer {
    margin-top: 16rpx;
    padding: 16rpx;
    background: #f8fafc;
    border-radius: 8rpx;

    text {
        font-size: 26rpx;
        color: #666;
        line-height: 1.6;
    }
}

.step-list {
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.step-item {
    display: flex;
    align-items: flex-start;
    gap: 16rpx;
}

.step-num {
    width: 44rpx;
    height: 44rpx;
    border-radius: 50%;
    background: linear-gradient(135deg, #c0fe95, #88f78d);
    color: #333;
    font-size: 24rpx;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.step-content {
    flex: 1;

    .step-title {
        display: block;
        font-size: 28rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 6rpx;
    }

    .step-desc {
        display: block;
        font-size: 24rpx;
        color: #999;
        line-height: 1.5;
    }
}

.contact-card {
    text-align: center;
    padding: 20rpx 0;
}

.contact-desc {
    display: block;
    font-size: 26rpx;
    color: #666;
    margin-bottom: 24rpx;
}

.contact-btn {
    width: 60%;
    height: 80rpx;
    line-height: 80rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #333;
    font-size: 28rpx;
    font-weight: bold;
    border: none;
    border-radius: 40rpx;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
}
</style>
