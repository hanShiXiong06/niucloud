<template>
    <view class="coop-poster-mount"
        ><u-popup
            :show="show && allowed"
            mode="center"
            :round="16"
            :z-index="11000"
            :safe-area-inset-bottom="false"
            @close="close"
        >
            <view class="coop-poster" @touchmove.stop.prevent>
                <view class="coop-poster__header"
                    ><view
                        ><text
                            class="coop-poster__title"
                            >{{ scene === 'entity' ? '邀请同事加入' : '我的邀请海报' }}</text
                        ><text
                            class="coop-poster__subtitle"
                            >{{ scene === 'entity' ? '扫码申请，负责人确认后加入' : '扫码进入，邀请关系按平台规则确认' }}</text
                        ></view
                    ><button class="coop-poster__close" aria-label="关闭海报" @tap="close"
                        ><u-icon name="close" size="20" color="#71839a" /></button
                ></view>
                <view class="coop-poster__preview">
                    <image
                        v-if="state.url.value"
                        :key="state.url.value"
                        class="coop-poster__image"
                        :class="{ 'is-loading': !state.ready.value }"
                        :src="state.url.value"
                        :data-source="state.url.value"
                        mode="aspectFit"
                        :show-menu-by-longpress="true"
                        @load="imageLoaded"
                        @error="imageFailed"
                        @tap="state.preview"
                    />
                    <view v-if="state.loading.value" class="coop-poster__status" role="status"
                        ><u-loading-icon mode="circle" size="28" color="#4086df" /><text>正在生成邀请海报…</text
                        ><text class="coop-poster__hint">首次生成小程序码可能需要几秒</text></view
                    >
                    <view v-else-if="state.error.value" class="coop-poster__status" role="alert"
                        ><u-icon name="photo" size="34" color="#9aabc0" /><text>{{ state.error.value }}</text
                        ><button class="coop-poster__retry" @tap="generate">重新生成</button></view
                    >
                </view>
                <view
                    v-if="state.saveError.value"
                    class="coop-poster__error"
                    role="alert"
                    >{{ state.saveError.value }}</view
                >
                <!-- #ifdef MP-WEIXIN -->
                <button
                    v-if="state.needsSettings.value"
                    class="coop-poster__settings"
                    open-type="openSetting"
                    @opensetting="state.needsSettings.value = false"
                    >打开相册权限设置</button
                >
                <!-- #endif -->
                <view class="coop-poster__actions"
                    ><button class="coop-poster__cancel" @tap="close">关闭</button
                    ><view class="coop-poster__primary">
                        <!-- #ifdef H5 -->
                        <u-button
                            type="primary"
                            shape="circle"
                            :custom-style="cooperationButtonStyle"
                            :disabled="!state.ready.value"
                            text="查看海报，长按保存"
                            @click="state.preview"
                        />
                        <!-- #endif -->
                        <!-- #ifndef H5 -->
                        <u-button
                            type="primary"
                            shape="circle"
                            :custom-style="cooperationButtonStyle"
                            :loading="state.saving.value"
                            :disabled="!state.ready.value || state.saving.value"
                            text="保存到相册"
                            @click="state.save"
                        />
                        <!-- #endif -->
                    </view></view
                >
            </view>
        </u-popup></view
    >
</template>
<script setup lang="ts">
import { watch } from 'vue'
import { useCooperationPoster } from '../../composables/useCooperationPoster'
import type { CooperationPosterScene } from '../../api/poster'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
const props = withDefaults(defineProps<{ show: boolean; scene: CooperationPosterScene; allowed?: boolean }>(), { allowed: true })
const emit = defineEmits<{ (event: 'close'): void }>(), state = useCooperationPoster()
function close() { state.close(); emit('close') }
function generate() { if (props.show && props.allowed) void state.generate(props.scene) }
function imageSource(event: any) { return String(event?.currentTarget?.dataset?.source || event?.target?.dataset?.source || '') }
function imageLoaded(event: any) { state.loaded(imageSource(event)) }
function imageFailed(event: any) { state.imageFailed(imageSource(event)) }
watch(() => [props.show, props.allowed, props.scene], () => { state.close(); if (props.show && props.allowed) generate() }, { immediate: true })
</script>
<style scoped lang="scss">
/* u-popup's root has flex:1 even while hidden. A closed poster must not take
   half of a sibling task page; retain its transition inside a zero-height mount. */
.coop-poster-mount { flex:none; height:0; min-height:0; width:100%; overflow:visible; }
.coop-poster { width:calc(100vw - 32px);max-width:380px;box-sizing:border-box;background:#fff;border-radius:16px;overflow:hidden;color:#2b3e56; }.coop-poster__header { display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:18px 14px 14px 18px; }.coop-poster__header>view { flex:1;min-width:0; }.coop-poster__title { display:block;font-size:17px;line-height:25px;font-weight:650; }.coop-poster__subtitle { display:block;margin-top:5px;color:#7c8da3;font-size:11px;line-height:18px; }.coop-poster__close { width:36px;height:36px;flex:none;margin:-4px -4px 0 0;padding:0;display:flex;align-items:center;justify-content:center;background:transparent; }.coop-poster__close::after,.coop-poster__retry::after,.coop-poster__cancel::after,.coop-poster__settings::after { border:0; }.coop-poster__preview { position:relative;height:51vh;max-height:480px;min-height:180px;margin:0 18px;background:#f2f6fc;border-radius:10px;overflow:hidden; }.coop-poster__image { width:100%;height:100%;display:block; }.coop-poster__image.is-loading { opacity:0; }.coop-poster__status { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;box-sizing:border-box;text-align:center;font-size:13px;line-height:21px;color:#607b9c; }.coop-poster__hint { font-size:11px;color:#8c9cb0; }.coop-poster__retry { padding:0 18px;margin:0;background:#fff;border-radius:22px;line-height:40px;font-size:13px;color:var(--primary-color,#387ecf); }.coop-poster__actions { display:flex;align-items:center;gap:12px;padding:16px 18px 18px; }.coop-poster__cancel { flex:0 0 62px;padding:0;margin:0;min-height:44px;line-height:44px;background:#f1f5fa;border-radius:22px;color:#74869b;font-size:13px; }.coop-poster__primary { flex:1;min-width:0; }.coop-poster__error { padding:12px 18px 0;font-size:12px;line-height:20px;color:#a56b54; }.coop-poster__settings { margin:10px 18px 0;padding:0;line-height:38px;background:#f3f7fd;color:var(--primary-color,#387ecf);font-size:12px; }
@media (max-height:520px) { .coop-poster__header { padding-top:12px; }.coop-poster__preview { height:42vh;min-height:110px; }.coop-poster__actions { padding-top:10px;padding-bottom:12px; } }
.coop-poster { max-height:calc(100vh - 32px);display:flex;flex-direction:column; }.coop-poster__header,.coop-poster__actions,.coop-poster__settings { flex-shrink:0; }.coop-poster__preview { flex-shrink:1; }.coop-poster__error { max-height:64px;overflow-y:auto;flex-shrink:0; }
.coop-poster { max-height:calc(100vh - 32px - env(safe-area-inset-top) - env(safe-area-inset-bottom)); }
.coop-poster__preview { min-height:0; }
/* #ifdef H5 */
.coop-poster { max-height:calc(100dvh - 32px - env(safe-area-inset-top) - env(safe-area-inset-bottom)); }
/* #endif */
</style>
