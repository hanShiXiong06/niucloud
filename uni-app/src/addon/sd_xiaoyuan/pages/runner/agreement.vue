<template>
    <view class="agreement-page">
        <view class="content">
            <view class="title">校园帮接单员服务协议</view>
            
            <view class="section">
                <view class="section-title">一、服务说明</view>
                <view class="section-content">
                    <text>1. 校园帮是一个校园跑腿服务平台，为校园内用户提供代取快递、代买物品、跑腿服务等便民服务。</text>
                    <text>2. 接单员是指在本平台注册并通过审核，为用户提供跑腿服务的服务提供者。</text>
                    <text>3. 接单员与平台之间为合作关系，非劳动雇佣关系。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">二、接单员资质要求</view>
                <view class="section-content">
                    <text>1. 接单员必须是本校在校学生、教职工或经学校认可的人员。</text>
                    <text>2. 接单员需提供真实有效的身份信息和学生证/工作证进行认证。</text>
                    <text>3. 接单员需年满18周岁，具有完全民事行为能力。</text>
                    <text>4. 接单员需遵守学校相关规定和国家法律法规。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">三、服务规范</view>
                <view class="section-content">
                    <text>1. 接单员应按时接单、取货、配送，确保服务质量。</text>
                    <text>2. 接单员应妥善保管用户物品，不得私自拆封、损坏或丢失。</text>
                    <text>3. 接单员应礼貌待人，不得与用户发生冲突。</text>
                    <text>4. 接单员应保护用户隐私，不得泄露用户个人信息。</text>
                    <text>5. 接单员不得接受违法违规的订单。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">四、收益与结算</view>
                <view class="section-content">
                    <text>1. 接单员完成订单后，可获得订单金额的80%作为服务收益。</text>
                    <text>2. 平台收取20%作为平台服务费。</text>
                    <text>3. 接单员可随时申请提现，提现金额将在审核通过后1-3个工作日内到账。</text>
                    <text>4. 提现可能收取一定手续费，具体以平台公示为准。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">五、违规处理</view>
                <view class="section-content">
                    <text>1. 接单员如有违规行为，平台有权根据情节轻重进行警告、暂停服务、永久封禁等处理。</text>
                    <text>2. 因接单员原因导致用户损失的，接单员应承担相应赔偿责任。</text>
                    <text>3. 接单员如有违法行为，平台将配合相关部门进行处理。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">六、免责声明</view>
                <view class="section-content">
                    <text>1. 平台仅提供信息撮合服务，不对接单员与用户之间的纠纷承担责任。</text>
                    <text>2. 因不可抗力导致的服务中断或损失，平台不承担责任。</text>
                    <text>3. 接单员在服务过程中发生的意外事故，由接单员自行承担责任。</text>
                </view>
            </view>
            
            <view class="section">
                <view class="section-title">七、协议修改</view>
                <view class="section-content">
                    <text>1. 平台有权根据需要修改本协议内容。</text>
                    <text>2. 协议修改后，平台将通过适当方式通知接单员。</text>
                    <text>3. 接单员继续使用平台服务，即视为同意修改后的协议。</text>
                </view>
            </view>
            
            <view class="footer">
                <text>本协议自接单员注册成功之日起生效。</text>
                <text>如有疑问，请联系平台客服。</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
</script>

<style lang="scss" scoped>
.agreement-page {
    min-height: 100vh;
    background: #f7f7f7;
    padding: 24rpx;
}

.content {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    
    .title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        text-align: center;
        margin-bottom: 40rpx;
    }
}

.section {
    margin-bottom: 40rpx;
    
    .section-title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
    }
    
    .section-content {
        text {
            display: block;
            font-size: 28rpx;
            color: #666;
            line-height: 1.8;
            margin-bottom: 12rpx;
        }
    }
}

.footer {
    margin-top: 60rpx;
    padding-top: 30rpx;
    border-top: 1rpx solid #f0f0f0;
    
    text {
        display: block;
        font-size: 26rpx;
        color: #999;
        text-align: center;
        margin-bottom: 8rpx;
    }
}
</style>
