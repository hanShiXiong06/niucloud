<template>
    <view class="min-h-screen relative" :style="themeColor()" v-if="model">
        <!-- 背景渐变光晕 -->
        <!-- 背景层 -->
        <view class="bg-layer"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #f8fafc 0%, #f0f4ff 30%, #e0e7ff 50%, #f0f4ff 70%, #f8fafc 100%);">
        </view>
        <!-- 顶部光晕 -->
        <view class="glow-layer-1"
            style="position: fixed; top: -150rpx; left: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>
        <!-- 底部光晕 -->
        <view class="glow-layer-2"
            style="position: fixed; bottom: -150rpx; right: 20%; width: 500rpx; height: 500rpx; background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 65%); border-radius: 50%; z-index: 0; filter: blur(100rpx);">
        </view>

        <view class="relative p-4" style="position: relative; z-index: 10;">
            <!-- 小程序自定义导航栏 -->
            <!-- #ifdef MP -->
            <top-tabbar :data="topTabbarData" :customBack="goBack" />
            <!-- #endif -->
            <!-- 模型信息卡片 -->
            <view class="mb-4 rounded-xl overflow-hidden"
                style="background: linear-gradient(135deg, rgba(239, 246, 255, 0.6) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(245, 247, 250, 0.8) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                <!-- 模型信息主体 -->
                <view class="px-4 py-4 flex items-center">
                    <!-- 左侧模型图片 -->
                    <view class="rounded-[12rpx] overflow-hidden mr-3 flex-shrink-0 relative"
                        style="width: 88rpx; height: 88rpx; border: 3px solid rgba(59, 130, 246, 0.2); box-shadow: 0 12rpx 32rpx rgba(59, 130, 246, 0.15), 0 0 0 1px rgba(59, 130, 246, 0.08) inset; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(239, 246, 255, 0.6));">
                        <image :src="img(model.logo)" mode="aspectFill"
                            style="width: 100%; height: 100%; filter: brightness(1.05) saturate(1.1);" />
                        <!-- 装饰光晕 -->
                        <view class="absolute inset-0 rounded-[12rpx]"
                            style="background: radial-gradient(circle at 30% 30%, rgba(59, 130, 246, 0.1), transparent 60%); pointer-events: none;">
                        </view>
                    </view>
                    <!-- 右侧模型信息 -->
                    <view class="flex-1 flex flex-col justify-center min-w-0">
                        <view class="text-base font-bold mb-1.5" style="color: #0f172a; letter-spacing: 0.2rpx;">
                            {{ model.name }}
                        </view>
                        <view class="text-xs line-clamp-2 leading-relaxed" style="color: #475569; line-height: 1.6;">
                            {{ model.desc }}
                        </view>
                    </view>
                </view>
            </view>
            <!-- 提示词输入卡片 -->
            <view v-if="model.is_prompt == 1" class="mb-4 rounded-xl overflow-hidden"
                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 245, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">

                <!-- 标题区域 -->
                <view class="px-4 pt-4 pb-3">
                    <view class="flex items-center justify-between">
                        <view class="flex items-center gap-2">
                            <view class="w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background: linear-gradient(135deg, rgba(147, 197, 253, 0.4), rgba(196, 181, 253, 0.4)); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                <u-icon name="edit-pen" size="16" color="#3b82f6" />
                            </view>
                            <view class="flex flex-col justify-center min-w-0">
                                <view class="text-base font-bold" style="color: #1e293b;">提示词语</view>
                                <view class="text-xs mt-0.5" style="color: #94a3b8;">描述您想生成的内容</view>
                            </view>
                        </view>
                        <view class="px-2 py-1 rounded text-xs font-medium"
                            style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                            {{ formData.prompt.length }}/5000
                        </view>
                    </view>
                </view>

                <!-- 输入区域 -->
                <view class="px-4 pb-4">
                    <textarea v-model="formData.prompt" :placeholder="model.prompt" class="text-sm"
                        style="width: 100%; min-height: 240rpx; padding: 24rpx; border-radius: 24rpx; background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(59, 130, 246, 0.12); color: #1e293b; line-height: 1.6; box-shadow: inset 0 2rpx 8rpx rgba(59, 130, 246, 0.04); box-sizing: border-box; word-wrap: break-word;"
                        maxlength="5000" />

                    <!-- 操作按钮 -->
                    <view v-if="config" class="mt-3 flex gap-2">
                        <view @click="sendTextFn"
                            class="flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-xl transition-all active:scale-98"
                            :class="aiLoading ? 'opacity-60 pointer-events-none' : ''"
                            style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.3);">
                            <text class="text-sm text-white font-semibold">{{ aiLoading ? '润色中...' : 'AI润色' }}</text>
                            <text class="text-xs" style="color: rgba(255, 255, 255, 0.7);">({{ config.chat_point }}{{
                                config.alias_name }})</text>
                        </view>
                        <view @click="formData.prompt = ''"
                            class="w-[88rpx] py-3 rounded-xl flex items-center justify-center transition-all active:scale-98"
                            style="background: rgba(248, 250, 252, 0.8); border: 1px solid rgba(59, 130, 246, 0.12);">
                            <u-icon name="trash" size="18" color="#64748b" />
                        </view>
                    </view>
                </view>
            </view>
            <!-- 上传图片卡片 -->
            <view v-if="model.is_upload_image == 1" class="mb-4 rounded-xl overflow-hidden"
                style="background: linear-gradient(135deg, rgba(240, 253, 250, 0.6) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(249, 250, 251, 0.8) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">

                <!-- 标题区域 -->
                <view class="px-4 pt-4 pb-3">
                    <view class="flex items-center justify-between">
                        <view class="flex items-center gap-2">
                            <view v-if="model.demo_image"
                                class="w-8 h-8 rounded-lg flex items-center justify-center cursor-pointer active:opacity-70"
                                @click="previewDemoImage"
                                style="background: linear-gradient(135deg, rgba(147, 197, 253, 0.4), rgba(196, 181, 253, 0.4)); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1); transition: opacity 0.2s;">
                                <u-icon name="photo" size="16" color="#3b82f6" />
                            </view>
                            <view v-else class="w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background: linear-gradient(135deg, rgba(147, 197, 253, 0.4), rgba(196, 181, 253, 0.4)); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                <u-icon name="photo" size="16" color="#94a3b8" />
                            </view>
                            <view class="flex flex-col justify-center min-w-0 ml-2">
                                <view class="text-base font-bold" style="color: #1e293b;">上传图像</view>
                                <view class="text-xs mt-0.5" style="color: #94a3b8;">上传参考图片</view>
                            </view>
                        </view>

                        <view v-if="model.demo_image"
                            class="px-3 py-1.5 rounded-lg cursor-pointer active:opacity-80 flex items-center gap-1.5"
                            @click="previewDemoImage"
                            style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(59, 130, 246, 0.2); transition: all 0.2s;">
                            <u-icon name="eye" size="14" color="#3b82f6" />
                            <view class="text-sm font-medium" style="color: #3b82f6;">查看演示</view>
                        </view>
                    </view>
                </view>

                <!-- 上传区域 -->
                <view class="px-4 pb-4">
                    <upload-images v-model="formData.image_urls" :max-count="model.limit_image ? model.limit_image : 3"
                        :multiple="true" />
                </view>
            </view>
            <!-- 分辨率卡片 -->
            <view class="mb-4 rounded-xl overflow-hidden"
                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(238, 242, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">
                <!-- 标题区域 -->
                <view class="px-4 pt-4 pb-3">
                    <view class="flex items-center justify-between">
                        <view class="flex items-center gap-2">
                            <view class="w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background: linear-gradient(135deg, rgba(147, 197, 253, 0.4), rgba(196, 181, 253, 0.4)); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                <u-icon name="grid" size="16" color="#3b82f6" />
                            </view>
                            <view class="flex flex-col justify-center min-w-0 ml-2">
                                <view class="text-base font-bold" style="color: #1e293b;">分辨率</view>
                                <view class="text-xs mt-0.5" style="color: #94a3b8;">分辨率越高生成越慢</view>
                            </view>
                        </view>
                        <view class="px-2 py-1 rounded text-xs font-medium"
                            style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                            可选
                        </view>
                    </view>
                </view>

                <!-- 分辨率选择 -->
                <view class="px-4 pb-4">
                    <view class="grid grid-cols-3 gap-2">
                        <view v-for="(item, index) in resolutions" :key="index"
                            @click="formData.image_size = item.value"
                            class="py-3 px-2 rounded-2xl text-center transition-all active:scale-95"
                            :style="formData.image_size === item.value
                                ? 'background: rgba(255, 255, 255, 0.95); border: 2px solid #3b82f6; box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.15);'
                                : 'background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(226, 232, 240, 0.6); box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.02);'">
                            <view class="text-lg font-bold"
                                :style="formData.image_size === item.value ? 'color: #3b82f6;' : 'color: #1e293b;'">
                                {{ item.label }}
                            </view>
                        </view>
                    </view>

                    <!-- 提示信息 -->
                    <view v-if="formData.image_size == '4K' || formData.image_size == '2K'"
                        class="mt-3 px-3 py-2 rounded-xl flex items-center gap-2"
                        style="background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2);">
                        <u-icon name="info-circle" size="14" color="#f59e0b" />
                        <view class="text-xs" style="color: #f59e0b;">当前生成预计耗时 3-5 分钟</view>
                    </view>
                </view>
            </view>
            <!-- 生成比例卡片 -->
            <view class="mb-4 rounded-xl overflow-hidden" v-if="model.is_prompt == 1"
                style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(238, 242, 255, 0.7) 50%, rgba(255, 255, 255, 0.85) 100%); border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 2rpx 12rpx rgba(59, 130, 246, 0.06); backdrop-filter: blur(20px);">

                <!-- 标题区域 -->
                <view class="px-4 pt-4 pb-3">
                    <view class="flex items-center justify-between">
                        <view class="flex items-center gap-2">
                            <view class="w-8 h-8 rounded-lg flex items-center justify-center"
                                style="background: linear-gradient(135deg, rgba(147, 197, 253, 0.4), rgba(196, 181, 253, 0.4)); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                                <u-icon name="grid" size="16" color="#3b82f6" />
                            </view>
                            <view class="flex flex-col justify-center min-w-0 ml-2">
                                <view class="text-base font-bold" style="color: #1e293b;">生成尺寸</view>
                                <view class="text-xs mt-0.5" style="color: #94a3b8;">选择图片尺寸</view>
                            </view>
                        </view>
                        <view class="px-2 py-1 rounded text-xs font-medium"
                            style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                            可选
                        </view>
                    </view>
                </view>

                <!-- 比例选择区域 -->
                <view class="px-4 pb-4">
                    <!-- 第一行：3个比例 -->
                    <view class="grid grid-cols-3 gap-2 mb-2">
                        <view v-for="(item, index) in aspect.slice(0, 3)" :key="index"
                            @click="selectAspectRatio(item.value)"
                            class="py-3.5 px-2 rounded-2xl text-center transition-all active:scale-95"
                            :style="formData.aspect_ratio === item.value && !isCustomAspect
                                ? 'background: rgba(255, 255, 255, 0.95); border: 2px solid #3b82f6; box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.15);'
                                : 'background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(226, 232, 240, 0.6); box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.02);'">
                            <view class="text-lg font-bold mb-1" style="color: #1e293b;">
                                {{ item.label }}
                            </view>
                            <view class="text-xs"
                                :style="formData.aspect_ratio === item.value && !isCustomAspect ? 'color: #64748b;' : 'color: #94a3b8;'">
                                {{ item.desc }}
                            </view>
                        </view>
                    </view>

                    <!-- 第二行：9:16 海报图 + 16:9 电脑壁纸 + 自定义 -->
                    <view class="grid grid-cols-3 gap-2">
                        <!-- 9:16 海报图 -->
                        <view @click="selectAspectRatio('9:16')"
                            class="py-3.5 px-2 rounded-2xl text-center transition-all active:scale-95"
                            :style="formData.aspect_ratio === '9:16' && !isCustomAspect
                                ? 'background: rgba(255, 255, 255, 0.95); border: 2px solid #3b82f6; box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.15);'
                                : 'background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(226, 232, 240, 0.6); box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.02);'">
                            <view class="text-lg font-bold mb-1" style="color: #1e293b;">
                                9:16
                            </view>
                            <view class="text-xs"
                                :style="formData.aspect_ratio === '9:16' && !isCustomAspect ? 'color: #64748b;' : 'color: #94a3b8;'">
                                海报图
                            </view>
                        </view>

                        <!-- 16:9 电脑壁纸 -->
                        <view @click="selectAspectRatio('16:9')"
                            class="py-3.5 px-2 rounded-2xl text-center transition-all active:scale-95"
                            :style="formData.aspect_ratio === '16:9' && !isCustomAspect
                                ? 'background: rgba(255, 255, 255, 0.95); border: 2px solid #3b82f6; box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.15);'
                                : 'background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(226, 232, 240, 0.6); box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.02);'">
                            <view class="text-lg font-bold mb-1" style="color: #1e293b;">
                                16:9
                            </view>
                            <view class="text-xs"
                                :style="formData.aspect_ratio === '16:9' && !isCustomAspect ? 'color: #64748b;' : 'color: #94a3b8;'">
                                电脑壁纸
                            </view>
                        </view>

                        <!-- 自定义 -->
                        <view @click="toggleCustomAspect" class="py-3.5 px-2 rounded-2xl transition-all active:scale-95"
                            :style="isCustomAspect
                                ? 'background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(59, 130, 246, 0.05)); border: 2px solid #3b82f6; box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.15);'
                                : 'background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(226, 232, 240, 0.6); box-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.02);'">
                            <view class="text-center" :class="isCustomAspect ? 'mb-1.5' : ''">
                                <view class="text-base font-bold"
                                    :style="isCustomAspect ? 'color: #3b82f6;' : 'color: #1e293b;'">
                                    自定义
                                </view>
                            </view>
                            <view v-if="isCustomAspect" class="flex items-center justify-center gap-1.5">
                                <input v-model.number="customWidth" type="number" placeholder="1"
                                    class="py-1.5 px-2 rounded-lg text-sm text-center font-semibold"
                                    style="width: 65rpx; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(59, 130, 246, 0.25); color: #1e293b;"
                                    @input="updateCustomAspect" />
                                <view class="text-base font-bold" style="color: #3b82f6;">:</view>
                                <input v-model.number="customHeight" type="number" placeholder="1"
                                    class="py-1.5 px-2 rounded-lg text-sm text-center font-semibold"
                                    style="width: 65rpx; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(59, 130, 246, 0.25); color: #1e293b;"
                                    @input="updateCustomAspect" />
                            </view>
                        </view>
                    </view>
                </view>
            </view>

        </view>

        <view class="h-[200rpx]"></view>


        <!-- 底部操作栏 -->
        <view class="fixed bottom-0 left-0 right-0 safe-area-bottom m-3 rounded-xl" v-if="config"
            style="background: linear-gradient(to top, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.3)); backdrop-filter: blur(50px) saturate(180%); -webkit-backdrop-filter: blur(50px) saturate(180%); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 -8rpx 32rpx rgba(59, 130, 246, 0.06), 0 -2rpx 12rpx rgba(0, 0, 0, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.3); z-index: 100;">
            <view class="px-4 py-3 flex items-center gap-3">
                <!-- 左侧导航入口 -->
                <view class="flex items-center gap-2">
                    <view
                        class="w-[76rpx] h-[76rpx] p-1 rounded-xl flex flex-col items-center justify-center transition-all active:scale-95"
                        style="background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.4); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.5);"
                        @click="redirect({ url: '/app/pages/index/index' })">
                        <u-icon name="home" size="24" color="#3b82f6" />
                        <text class="text-[26rpx] font-bold mt-0.5" style="color: #475569;">首页</text>
                    </view>
                    <view
                        class="w-[76rpx] h-[76rpx] p-1 rounded-xl flex flex-col items-center justify-center transition-all active:scale-95"
                        style="background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.4); box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.5);"
                        @click="redirect({ url: '/addon/ai_image/pages/list' })">
                        <u-icon name="photo" size="24" color="#3b82f6" />
                        <text class="text-[26rpx] font-bold mt-0.5" style="color: #475569;">作品</text>
                    </view>
                </view>

                <!-- 右侧主按钮 -->
                <button hover-class="opacity-90" @click="handleSubmit" :disabled="create_loading"
                    class="flex-1 py-3 px-4 rounded-xl text-white flex items-center justify-center gap-2 transition-all"
                    :class="create_loading ? 'opacity-60 pointer-events-none' : 'active:scale-98'"
                    :style="create_loading
                        ? 'background: linear-gradient(135deg, #94a3b8, #64748b); box-shadow: 0 4rpx 12rpx rgba(148, 163, 184, 0.2);'
                        : 'background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.3);'">
                    <text class="text-sm font-bold">{{ create_loading ? '生成中...' : '一键生成' }}</text>
                    <text v-if="!create_loading" class="text-xs" style="color: rgba(255, 255, 255, 0.7);">
                        <text v-if="model.point > 0">(消耗{{ model.point }}{{ config.alias_name }})</text>
                        <text v-else>(免费)</text>
                    </text>
                </button>
            </view>
            <up-safe-bottom></up-safe-bottom>
        </view>
        <!-- 生成状态弹窗 -->
        <u-popup :show="create_loading" :round="20" mode="bottom">
            <view class="w-screen max-w-[600rpx] mx-auto rounded-2xl overflow-hidden"
                style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px);">

                <!-- 弹窗头部 -->
                <view class="px-6 pt-6 pb-4 border-b" style="border-color: rgba(59, 130, 246, 0.1);">
                    <view class="flex items-center justify-center mb-2">
                        <view class="flex items-center gap-2">
                            <view class="text-[32rpx] font-bold" style="color: #1e293b;">
                                {{ create_result && create_result.status != 0 ? '任务完成' : 'AI 生成中' }}
                            </view>
                        </view>
                    </view>
                </view>

                <!-- 弹窗内容 -->
                <view class="pt-3 pb-3">
                    <!-- 生成中状态 -->
                    <view v-if="!create_result || create_result.status == 0" class="text-center">
                        <view class="mb-6">
                            <!-- 行星运行系统 -->
                            <view class="planet-system">
                                <!-- 中心太阳 -->
                                <view class="planet-sun">
                                    <view class="sun-inner">
                                        <u-icon name="photo" size="32" color="#ffffff" />
                                    </view>
                                </view>

                                <!-- 第一轨道 -->
                                <view class="orbit orbit-1">
                                    <view class="planet planet-1"></view>
                                </view>

                                <!-- 第二轨道 -->
                                <view class="orbit orbit-2">
                                    <view class="planet planet-2"></view>
                                </view>

                                <!-- 第三轨道 -->
                                <view class="orbit orbit-3">
                                    <view class="planet planet-3"></view>
                                </view>
                            </view>

                            <view class="text-sm mt-6" style="color: #64748b; line-height: 1.6;">
                                预计需要 30-50 秒，请耐心等待<br />您也可以稍后到作品列表查看
                            </view>
                        </view>
                    </view>

                    <!-- 生成完成状态 -->
                    <view v-else class="text-center">
                        <!-- 生成成功 -->
                        <view v-if="create_result.status == 1">
                            <view class="mb-4 rounded-2xl overflow-hidden cursor-pointer"
                                style="border: 2px solid rgba(59, 130, 246, 0.2); box-shadow: 0 8rpx 24rpx rgba(59, 130, 246, 0.15);"
                                @click="previewImage(create_result.images)">
                                <image :src="create_result.images" mode="aspectFit"
                                    style="width: 100%; max-height: 500rpx; background: #f8fafc;" />
                            </view>
                            <view class="flex items-center justify-center gap-2 mb-2">
                                <u-icon name="checkmark-circle-fill" size="24" color="#10b981" />
                                <view class="text-lg font-bold" style="color: #10b981;">生成成功</view>
                            </view>
                            <view class="text-xs" style="color: #94a3b8;">点击图片可以预览大图</view>
                        </view>

                        <!-- 生成失败 -->
                        <view v-else-if="create_result.status == 2">
                            <view class="mb-6 flex items-center justify-center"
                                style="width: 260rpx; height: 260rpx; margin: 0 auto; background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(220, 38, 38, 0.05)); border: 2px dashed rgba(239, 68, 68, 0.3); border-radius: 32rpx;">
                                <u-icon name="close-circle-fill" size="72" color="#ef4444" />
                            </view>
                            <view class="flex items-center justify-center gap-2 mb-4">
                                <u-icon name="error-circle-fill" size="24" color="#ef4444" />
                                <view class="text-lg font-bold" style="color: #ef4444;">生成失败</view>
                            </view>
                            <view class="px-5 py-4 rounded-xl text-left"
                                style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.1);">
                                <view class="text-xs font-semibold mb-1" style="color: #ef4444;">失败原因：</view>
                                <view class="text-sm" style="color: #64748b; line-height: 1.6;">
                                    {{ create_result.msg || '生成过程中出现错误，请重试' }}
                                </view>
                            </view>
                        </view>
                    </view>

                    <!-- 底部按钮 -->
                    <view class="flex gap-3 mt-6">

                        <button @click="closePopup"
                            class="flex-1 py-3.5 rounded-xl font-semibold text-sm transition-all active:scale-95"
                            style="background: transparent; border: 2px solid rgba(59, 130, 246, 0.3); color: #3b82f6; box-shadow: 0 2rpx 8rpx rgba(59, 130, 246, 0.1);">
                            关闭
                        </button>
                        <button @click="redirect({ url: '/addon/ai_image/pages/list' })"
                            class="flex-1 py-3.5 rounded-xl font-semibold text-sm text-white transition-all active:scale-95"
                            style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4rpx 12rpx rgba(59, 130, 246, 0.3);">
                            我的作品
                        </button>
                    </view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onBeforeUnmount, watch } from 'vue'
import uploadImages from "@/addon/ai_image/pages/components/upload-img.vue";
import { redirect, img } from '@/utils/common'
import { topTabar } from '@/utils/topTabbar'
import { getModelInfo, getConfig, sendText, createImage, getImageInfo } from '@/addon/ai_image/api/aiimage';
import { onLoad } from '@dcloudio/uni-app';
/********* 自定义头部 - start ***********/
const topTabarObj = topTabar()
let topTabbarData = topTabarObj.setTopTabbarParam({
    title: '作品创作', topStatusBar: {
        textColor: '#000000',
    }
})
/********* 自定义头部 - end ***********/
const aspect = ref([
    { label: '1:1', value: '1:1', desc: '头像' },
    { label: '3:2', value: '3:2', desc: '文章配图' },
    { label: '4:3', value: '4:3', desc: '公众号配图' },
    { label: '9:16', value: '9:16', desc: '海报图' },
])
const isCustomAspect = ref(false)
const customWidth = ref<number | null>(null)
const customHeight = ref<number | null>(null)
const resolutions = ref([
    { label: '1K', value: '1K' },
    { label: '2K', value: '2K' },
    { label: '4K', value: '4K' },
])
const formData = ref({
    prompt: '',
    aspect_ratio: '',
    image_urls: [] as string[],
    model_id: '',
    image_size: '1K',
    custom_width: null as number | null,
    custom_height: null as number | null,
})
const create_id = ref('')
const create_loading = ref(false)

// 选择预设比例
const selectAspectRatio = (value: string) => {
    isCustomAspect.value = false
    formData.value.aspect_ratio = value
    formData.value.custom_width = null
    formData.value.custom_height = null
}

// 切换自定义比例
const toggleCustomAspect = () => {
    isCustomAspect.value = true
    formData.value.aspect_ratio = ''
    if (customWidth.value && customHeight.value) {
        updateCustomAspect()
    }
}

// 更新自定义比例
const updateCustomAspect = () => {
    if (customWidth.value && customHeight.value && customWidth.value > 0 && customHeight.value > 0) {
        formData.value.custom_width = customWidth.value
        formData.value.custom_height = customHeight.value
        formData.value.aspect_ratio = `${customWidth.value}:${customHeight.value}`
    }
}

const handleSubmit = () => {
    if (model.value.is_prompt == 1) {
        if (formData.value.prompt == '') {
            uni.showToast({ title: '请输入描述后再生成', icon: 'none' })
            return
        }
    }
    if (model.value.is_prompt == 0 && model.value.is_upload_image == 1) {
        if (formData.value.image_urls.length == 0) {
            uni.showToast({ title: '请上传图片后再生成', icon: 'none' })
            return
        }
    }
    create_loading.value = true
    createImage(formData.value).then((res: any) => {
        create_id.value = res.data.id
        //    getImageInfoFn()
    })
}
const create_result = ref<any>()
const getImageInfoFn = () => {
    getImageInfo(create_id.value).then((res: any) => {
        create_result.value = res.data
        if (create_result.value.status != 0) {
            uni.showToast({ title: '任务完成', icon: 'success' })
        } else {
            setTimeout(() => {
                getImageInfoFn()
            }, 2000)
        }
    })
}

// 关闭弹窗并清除结果
const closePopup = () => {
    create_loading.value = false
    create_result.value = null
}

// 预览图片
const previewImage = (url: string) => {
    closePopup()
    uni.previewImage({
        urls: [url],
        current: url
    })
}

// 预览模型示例图片
const previewDemoImage = () => {
    if (model.value?.demo_image) {
        uni.previewImage({
            urls: [img(model.value.demo_image)],
            current: img(model.value.demo_image)
        })
    }
}

const goBack = () => {
    uni.navigateBack()
}
const aiLoading = ref(false)
const sendTextFn = () => {
    if (formData.value.prompt == '') {
        uni.showToast({ title: '请输入描述后再生成', icon: 'none' })
        return
    }
    aiLoading.value = true
    sendText({
        prompt: formData.value.prompt,
        model_id: model.value.id
    }).then((res: any) => {
        formData.value.prompt = res.data
        aiLoading.value = false
    })
}
const model = ref<any>()
const config = ref<any>()
const getConfigFn = () => {
    getConfig().then((res: any) => {
        config.value = res.data
    })
}
onLoad((options: any) => {
    if (options?.model_id) {
        getModelInfo(options.model_id).then((res: any) => {
            model.value = res.data
            formData.value.model_id = res.data.id
        })
        getConfigFn()
    }
})
</script>
<style lang="scss" scoped>
/* 优化文字渲染 */
text,
textarea {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* 点击缩放效果 */
.active\:scale-98:active {
    transform: scale(0.98);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.active\:scale-95:active {
    transform: scale(0.95);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* textarea 样式优化 */
textarea {
    resize: none;
    outline: none;
}

textarea::placeholder {
    opacity: 0.5;
}

/* 网格布局 */
.grid {
    display: grid;
}

.grid-cols-3 {
    grid-template-columns: repeat(3, 1fr);
}

.gap-2 {
    gap: 8rpx;
}

/* 加载旋转动画 */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.loading-spin {
    animation: spin 1s linear infinite;
}

/* 呼吸动画 */
@keyframes breathing {

    0%,
    100% {
        opacity: 0.6;
        transform: scale(1);
    }

    50% {
        opacity: 1;
        transform: scale(1.1);
    }
}

.breathing {
    animation: breathing 2s ease-in-out infinite;
}

/* 按钮禁用状态 */
button:disabled {
    cursor: not-allowed;
}

/* 行星运行系统 */
.planet-system {
    position: relative;
    width: 280rpx;
    height: 280rpx;
    margin: 0 auto;
}

/* 中心太阳 */
.planet-sun {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80rpx;
    height: 80rpx;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    box-shadow: 0 0 30rpx rgba(59, 130, 246, 0.6),
        0 0 60rpx rgba(59, 130, 246, 0.4),
        inset 0 0 20rpx rgba(255, 255, 255, 0.3);
    animation: sun-pulse 2s ease-in-out infinite;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.sun-inner {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* 太阳脉动动画 */
@keyframes sun-pulse {

    0%,
    100% {
        box-shadow: 0 0 30rpx rgba(59, 130, 246, 0.6),
            0 0 60rpx rgba(59, 130, 246, 0.4),
            inset 0 0 20rpx rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%) scale(1);
    }

    50% {
        box-shadow: 0 0 40rpx rgba(59, 130, 246, 0.8),
            0 0 80rpx rgba(59, 130, 246, 0.5),
            inset 0 0 25rpx rgba(255, 255, 255, 0.4);
        transform: translate(-50%, -50%) scale(1.05);
    }
}

/* 轨道 */
.orbit {
    position: absolute;
    top: 50%;
    left: 50%;
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.orbit-1 {
    width: 140rpx;
    height: 140rpx;
    animation: rotate-orbit 8s linear infinite;
}

.orbit-2 {
    width: 200rpx;
    height: 200rpx;
    animation: rotate-orbit 12s linear infinite;
}

.orbit-3 {
    width: 260rpx;
    height: 260rpx;
    animation: rotate-orbit 16s linear infinite;
}

/* 轨道旋转动画 */
@keyframes rotate-orbit {
    from {
        transform: translate(-50%, -50%) rotate(0deg);
    }

    to {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

/* 行星 */
.planet {
    position: absolute;
    border-radius: 50%;
    box-shadow: 0 0 10rpx rgba(59, 130, 246, 0.5);
}

.planet-1 {
    width: 12rpx;
    height: 12rpx;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    animation: planet-glow-1 2s ease-in-out infinite;
}

.planet-2 {
    width: 16rpx;
    height: 16rpx;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    animation: planet-glow-2 2.5s ease-in-out infinite;
}

.planet-3 {
    width: 14rpx;
    height: 14rpx;
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    animation: planet-glow-3 3s ease-in-out infinite;
}

/* 行星发光动画 */
@keyframes planet-glow-1 {

    0%,
    100% {
        box-shadow: 0 0 10rpx rgba(59, 130, 246, 0.5);
    }

    50% {
        box-shadow: 0 0 20rpx rgba(59, 130, 246, 0.8);
    }
}

@keyframes planet-glow-2 {

    0%,
    100% {
        box-shadow: 0 0 12rpx rgba(139, 92, 246, 0.5);
    }

    50% {
        box-shadow: 0 0 24rpx rgba(139, 92, 246, 0.8);
    }
}

@keyframes planet-glow-3 {

    0%,
    100% {
        box-shadow: 0 0 11rpx rgba(6, 182, 212, 0.5);
    }

    50% {
        box-shadow: 0 0 22rpx rgba(6, 182, 212, 0.8);
    }
}
</style>
