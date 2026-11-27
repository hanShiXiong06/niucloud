<template>
    <view class="recycle-send-button-wrap" :style="wrapStyle">
        <view class="mask-layer" :style="maskLayer"></view>
        <view class="button-container" :style="containerStyle">
            <button 
                class="send-button"
                :class="buttonClass"
                :style="buttonStyle"
                :disabled="!isButtonEnabled"
                @click="handleButtonClick"
            >
                <!-- 左侧图标 -->
                <view 
                    v-if="showIcon && iconPosition === 'left'" 
                    class="button-icon button-icon-left"
                    :style="iconStyle"
                >
                    📦
                </view>
                
                <!-- 按钮文字 -->
                <text class="button-text" :style="textStyle">{{ buttonText }}</text>
                
                <!-- 右侧图标 -->
                <view 
                    v-if="showIcon && iconPosition === 'right'" 
                    class="button-icon button-icon-right"
                    :style="iconStyle"
                >
                    ➤
                </view>
            </button>
        </view>
    </view>
</template>

<script lang="ts" setup>
import { ref, computed } from 'vue';
import { img, getToken, redirect } from '@/utils/common';
import useDiyStore from '@/app/stores/diy';
import { useLogin } from "@/hooks/useLogin";

const props = defineProps({
    component: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    }
});

const diyStore = useDiyStore();

// 按钮配置计算属性
const buttonText = computed(() => props.component.buttonText || '立即发货');
const isButtonEnabled = computed(() => props.component.isEnabled !== false);
const buttonStyleType = computed(() => props.component.style || 'style-1');
const buttonWidth = computed(() => props.component.buttonWidth || 'auto');
const customWidth = computed(() => props.component.customWidth || 120);
const align = computed(() => props.component.align || 'center');

// 图标配置
const showIcon = computed(() => props.component.icon?.isShow || false);
const iconPosition = computed(() => props.component.icon?.position || 'left');
const iconSize = computed(() => props.component.icon?.size || 16);

// 样式配置
const buttonHeight = computed(() => props.component.buttonHeight || 40);
const borderRadius = computed(() => props.component.borderRadius || 4);
const backgroundColor = computed(() => props.component.backgroundColor || '#409EFF');
const borderColor = computed(() => props.component.borderColor || '#409EFF');
const borderWidth = computed(() => props.component.borderWidth || 2);
const fontSize = computed(() => props.component.fontSize || 14);
const fontWeight = computed(() => props.component.fontWeight || 'normal');
const textColor = computed(() => props.component.textColor || '#FFFFFF');

// 边距配置
const marginTop = computed(() => props.component.marginTop || 10);
const marginBottom = computed(() => props.component.marginBottom || 10);
const marginHorizontal = computed(() => props.component.marginHorizontal || 15);

// 容器样式
const wrapStyle = computed(() => {
    let style = 'position:relative;';
    
    // 背景色设置
    if (props.component.componentStartBgColor) {
        if (props.component.componentStartBgColor && props.component.componentEndBgColor) {
            style += `background:linear-gradient(${props.component.componentGradientAngle || 'to bottom'},${props.component.componentStartBgColor},${props.component.componentEndBgColor});`;
        } else {
            style += 'background-color:' + props.component.componentStartBgColor + ';';
        }
    }
    
    // 背景图设置
    if (props.component.componentBgUrl) {
        style += `background-image:url('${img(props.component.componentBgUrl)}');`;
        style += 'background-size: cover;background-repeat: no-repeat;';
    }
    
    // 组件圆角设置
    if (props.component.topRounded) {
        style += 'border-top-left-radius:' + props.component.topRounded * 2 + 'rpx;';
        style += 'border-top-right-radius:' + props.component.topRounded * 2 + 'rpx;';
    }
    
    if (props.component.bottomRounded) {
        style += 'border-bottom-left-radius:' + props.component.bottomRounded * 2 + 'rpx;';
        style += 'border-bottom-right-radius:' + props.component.bottomRounded * 2 + 'rpx;';
    }
    
    return style;
});

// 背景遮罩层
const maskLayer = computed(() => {
    let style = '';
    if (props.component.componentBgUrl) {
        style += 'position:absolute;top:0;width:100%;height:100%;';
        style += `background: rgba(0,0,0,${props.component.componentBgAlpha / 10});`;
        
        // 圆角同步设置
        if (props.component.topRounded) {
            style += 'border-top-left-radius:' + props.component.topRounded * 2 + 'rpx;';
            style += 'border-top-right-radius:' + props.component.topRounded * 2 + 'rpx;';
        }
        
        if (props.component.bottomRounded) {
            style += 'border-bottom-left-radius:' + props.component.bottomRounded * 2 + 'rpx;';
            style += 'border-bottom-right-radius:' + props.component.bottomRounded * 2 + 'rpx;';
        }
    }
    return style;
});

// 按钮容器样式
const containerStyle = computed(() => {
    let style = 'position:relative;z-index:1;';
    
    // 按钮对齐方式
    if (align.value === 'left') {
        style += 'text-align:left;';
    } else if (align.value === 'center') {
        style += 'text-align:center;';
    } else if (align.value === 'right') {
        style += 'text-align:right;';
    }
    
    // 容器边距
    style += `padding: ${marginTop.value * 2}rpx ${marginHorizontal.value * 2}rpx ${marginBottom.value * 2}rpx;`;
    
    return style;
});

// 按钮样式类
const buttonClass = computed(() => {
    const classes = ['send-button'];
    
    // 根据样式添加对应类名
    classes.push(`button-${buttonStyleType.value}`);
    
    // 根据宽度模式添加类名
    if (buttonWidth.value === 'full') {
        classes.push('button-full-width');
    } else if (buttonWidth.value === 'custom') {
        classes.push('button-custom-width');
    }
    
    // 禁用状态
    if (!isButtonEnabled.value) {
        classes.push('button-disabled');
    }
    
    return classes.join(' ');
});

// 按钮内联样式
const buttonStyle = computed(() => {
    let style = '';
    
    // 按钮尺寸
    style += `height: ${buttonHeight.value * 2}rpx;`;
    style += `border-radius: ${borderRadius.value * 2}rpx;`;
    
    // 按钮宽度
    if (buttonWidth.value === 'custom') {
        style += `width: ${customWidth.value * 2}rpx;`;
    } else if (buttonWidth.value === 'full') {
        style += 'width: 100%;';
    }
    
    // 根据样式设置颜色
    switch (buttonStyleType.value) {
        case 'style-1': // 默认按钮
            style += `background-color: ${backgroundColor.value};`;
            style += `color: ${textColor.value};`;
            style += 'border: none;';
            break;
        case 'style-2': // 渐变按钮
            if (backgroundColor.value.includes('gradient')) {
                style += `background: ${backgroundColor.value};`;
            } else {
                style += `background: linear-gradient(45deg, ${backgroundColor.value}, #67C23A);`;
            }
            style += `color: ${textColor.value};`;
            style += 'border: none;';
            break;
        case 'style-3': // 边框按钮
            style += 'background-color: transparent;';
            style += `color: ${textColor.value};`;
            style += `border: ${borderWidth.value * 2}rpx solid ${borderColor.value};`;
            break;
        case 'style-4': // 阴影按钮
            style += `background-color: ${backgroundColor.value};`;
            style += `color: ${textColor.value};`;
            style += 'border: none;';
            style += 'box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.15);';
            break;
        default:
            style += `background-color: ${backgroundColor.value};`;
            style += `color: ${textColor.value};`;
            style += 'border: none;';
    }
    
    return style;
});

// 文字样式
const textStyle = computed(() => {
    let style = '';
    style += `font-size: ${fontSize.value * 2}rpx;`;
    style += `font-weight: ${fontWeight.value};`;
    return style;
});

// 图标样式
const iconStyle = computed(() => {
    let style = '';
    style += `font-size: ${iconSize.value * 2}rpx;`;
    return style;
});

// 处理按钮点击
const handleButtonClick = () => {
    // 装修模式下不触发跳转
    if (diyStore.mode === 'decorate') {
        return;
    }
    
    // 检查按钮是否启用
    if (!isButtonEnabled.value) {
        return;
    }
    
    // 获取链接配置
    const linkConfig = props.component.link;
    
    if (linkConfig && linkConfig.name) {
        // 如果配置了自定义链接，使用配置的链接
        handleLinkJump(linkConfig);
    } else {
        // 默认跳转到发货页面
        handleDefaultJump();
    }
};

// 处理链接跳转
const handleLinkJump = (linkConfig: any) => {
    if (diyStore.mode === 'decorate') {
        return;
    }
    if (!getToken() && linkConfig.name !== 'DIY_LINK') {
        useLogin().setLoginBack({ url: linkConfig.url });
        return;
    }
    
    redirect({ url: linkConfig.url });
};

// 默认跳转逻辑
const handleDefaultJump = () => {
    if (diyStore.mode === 'decorate') {
        return;
    }
    const defaultUrl = '/addon/hsx_recycle/pages/order/order'; // 默认跳转到发货页面
    
    if (!getToken()) {
        useLogin().setLoginBack({ url: defaultUrl });
        return;
    }
    
    redirect({ url: defaultUrl });
};
</script>

<style lang="scss" scoped>
.recycle-send-button-wrap {
    position: relative;
}

.mask-layer {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
}

.button-container {
    position: relative;
    z-index: 1;
}

.send-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 160rpx;
    padding: 0 30rpx;
    border: none;
    outline: none;
    cursor: pointer;
    transition: all 0.3s ease;
    
    &.button-full-width {
        width: 100%;
    }
    
    &.button-custom-width {
        flex-shrink: 0;
    }
    
    &.button-disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    &:not(.button-disabled):active {
        transform: scale(0.98);
        opacity: 0.8;
    }
}

.button-text {
    flex: 1;
    text-align: center;
}

.button-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    
    &.button-icon-left {
        margin-right: 8rpx;
    }
    
    &.button-icon-right {
        margin-left: 8rpx;
    }
}

// 不同样式的按钮效果
.button-style-1 {
    // 默认样式在内联样式中处理
}

.button-style-2 {
    // 渐变样式在内联样式中处理
}

.button-style-3 {
    // 边框样式在内联样式中处理
}

.button-style-4 {
    // 阴影样式在内联样式中处理
}
</style>
