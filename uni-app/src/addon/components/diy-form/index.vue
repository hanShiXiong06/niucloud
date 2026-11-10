<template>
    <view :style="themeColor()">
        <!-- 自定义组件渲染 -->
        <view v-if="formReady" class="diy-template-wrap">
            <diy-group ref="diyGroupRef" :data="diyFormData"/>
        </view>
        <!-- 目前只有自定义表单才展示错误信息，其他类型暂时不控制 -->
        <view class="flex flex-col" v-if="requestData.error && requestData.error.length > 0 && (definition || diy.data == 'DIY_FORM')">
            <view class="flex-1 flex flex-col items-center pt-[20rpx]" v-for="(item, index) in requestData.error.slice(0, 1)" :key="index">
                <text class="nc-iconfont nc-icon-tanhaoV6mm text-[#ccc] mb-[30rpx] !text-[100rpx]"></text>
                <view class="text-[38rpx] font-bold mt-3">{{ item.title }}</view>
                <view class="p-[30rpx] mt-[20rpx] w-full">
                    <view class="flex w-full">
                        <view class="w-[30%] text-[#999] text-left">{{ item.type }}</view>
                        <view class="w-[70%] text-left">{{ item.desc }}</view>
                    </view>
                </view>
            </view>
        </view>
    </view>
</template>
<script lang="ts" setup>
import { ref, reactive, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';
import { useDiyForm } from '@/hooks/useDiyForm'
import { deepClone, getValidTime } from '@/utils/common'
import diyGroup from '@/addon/components/diy/group/index.vue'

const props = defineProps(['form_id', 'relate_id', 'storage_name', 'form_border', 'definition', 'initial_values', 'cache_scope', 'cache_enabled', 'embedded', 'require_ready']);
const emit = defineEmits(['ready', 'error']);
const loadError = ref('');
const cacheKey = () => 'diyFormStorage_' + props.form_id + (props.cache_scope ? ':' + encodeURIComponent(String(props.cache_scope)) : '');

const diy = useDiyForm({
    form_id: () => props.form_id,
    needLogin: false, // 不检测登录，调用业务自行处理
    cache_scope: () => props.cache_scope,
    cache_enabled: () => props.cache_enabled,
    embedded: () => props.embedded,
    isActive: () => !disposed && !props.definition,
    onError: () => fail('补充资料加载失败，请重新读取后再保存')
})

const diyGroupRef = ref(null)
const formReady = ref(false)
let generation = 0, disposed = false
let stopFormWatch: (() => void) | undefined

const requestData = computed(() => {
    return props.definition ? { status: props.definition.status, error: loadError.value ? [{ title: loadError.value, type: '表单', desc: '请重新读取后再保存' }] : [] } : diy.requestData;
})

const diyFormData: any = reactive({})

function fail(message: string) {
    if (disposed) return
    formReady.value = false; loadError.value = message
    emit('error', message)
}
async function install(source: any, turn: number) {
        if (disposed || turn !== generation) return
        if (!source || Number(source.status) !== 1 || !Array.isArray(source.value)) { fail('补充资料表单暂不可用，请重新读取后再保存'); return }
        diyFormData.status = source.status;
        {
            diyFormData.title = source.title;
            diyFormData.global = deepClone(source.global || {});
            if (diyFormData.global) {
                if (diyFormData.global.topStatusBar) diyFormData.global.topStatusBar.isShow = false;
                if (diyFormData.global.bottomTabBar) diyFormData.global.bottomTabBar.isShow = false;
            }
            let value: any = [];
            if (props.form_border == 'none') {
                diyFormData.global.borderControl = false;
            }
            // 需要过滤 组件类型，筛选出来表单，排除表单提交组件
            ;source.value.forEach((item: any) => {
                if (item && item.componentType == 'diy_form' && item.componentName != 'FormSubmit' && !item.isHidden) {
                    value.push(item);
                }
            })
            diyFormData.value = deepClone(value).filter((item: any) => item && typeof item === 'object' && item.field);
            if (props.initial_values && typeof props.initial_values === 'object') {
                diyFormData.value.forEach((item: any) => {
                    if (Object.prototype.hasOwnProperty.call(props.initial_values, String(item.id))) {
                        item.field.value = deepClone(props.initial_values[String(item.id)]);
                        // Native input widgets model their value as text; in
                        // particular their legacy == '' check treats numeric 0
                        // as blank. Preserve its value using the widget's shape.
                        if (typeof item.field.value === 'number' && ['FormInput', 'FormTextarea', 'FormMobile', 'FormEmail', 'FormNumber'].includes(item.componentName)) item.field.value = String(item.field.value);
                        // A deliberately saved blank must not be replaced by
                        // a component's default on its own mounted refresh.
                        delete item.field.default;
                        if (Object.prototype.hasOwnProperty.call(item, 'defaultControl')) item.defaultControl = false;
                    }
                })
            }
            diyFormData.componentRefs = null;
            formReady.value = true
            await nextTick()
            if (disposed || turn !== generation) return
            diyGroupRef.value?.refresh()
            await nextTick()
            if (disposed || turn !== generation) return
            if (props.require_ready === true && diyFormData.value.length) {
                const componentRefs = diyGroupRef.value?.getFormRef?.()?.componentRefs;
                const expected = new Map<string, number>();
                for (const item of diyFormData.value) { const key = `diy${item.componentName}Ref`; expected.set(key, (expected.get(key) || 0) + 1) }
                const missing = !componentRefs || Array.from(expected).some(([key, count]) => {
                    const refs = Array.isArray(componentRefs[key]) ? componentRefs[key] : componentRefs[key] ? [componentRefs[key]] : [];
                    return refs.length < count || refs.some((item: any) => typeof item?.verify !== 'function');
                });
                if (missing) { fail('补充资料尚未显示完整，请重新读取'); return }
            }
            watchFormData();
            emit('ready', { form_id: Number(props.form_id), fields: diyFormData.value.length })
        }
}
function reload() {
    const turn = ++generation
    stopFormWatch?.(); formReady.value = false; loadError.value = ''; emit('ready', false)
    if (props.definition) { void install(props.definition, turn); return }
    const pending = diy.getData(() => {
        if (disposed || turn !== generation) return
        if (requestData.value?.error?.length) { fail('补充资料表单暂不可用，请重新读取后再保存'); return }
        void install(diy.data, turn)
    })
    if (pending && typeof pending.catch === 'function') pending.catch(() => { if (!disposed && turn === generation) fail('补充资料加载失败，请重新读取后再保存') })
}
onMounted(reload)
watch(() => [props.form_id, props.cache_scope, props.cache_enabled, props.definition, props.initial_values], reload)
onBeforeUnmount(() => { disposed = true; generation++; stopFormWatch?.() })

const watchFormData = () => {
    stopFormWatch?.()
    if (props.cache_enabled === false) return
    stopFormWatch = watch(
        () => diyFormData.value,
        (newValue, oldValue) => {
            if (newValue) {
                let formData: any = {
                    validTime: getValidTime(5), // 缓存数据有效期为5分钟
                    components: []
                };
                newValue.forEach((item: any) => {
                    // 只存表单组件
                    if (item.componentType == 'diy_form' && item.componentName != 'FormSubmit' && item.field.cache) {
                        // 只存储表单数据，压缩存储空间
                        let field = deepClone(item.field);
                        // 移除不需要存储的数据
                        delete field.remark; // 字段说明
                        delete field.detailComponent; // 用于详情展示
                        delete field.default; // 默认值
                        formData.components.push({
                            id: item.id,
                            componentName: item.componentName,
                            componentType: item.componentType,
                            componentTitle: item.componentTitle,
                            isHidden: item.isHidden,
                            field: field
                        })
                    }
                })
                if (formData.components.length) {
                    uni.setStorageSync(cacheKey(), formData)
                }
            }
        },
        { deep: true }
    )
}

const verify = () => {
    // Existing optional business forms keep their inactive-form skip behavior.
    // Callers requiring a current complete schema explicitly opt into fail-closed.
    if (!formReady.value || !diyFormData.status || !Array.isArray(diyFormData.value) || loadError.value || requestData.value?.error?.length) return props.require_ready !== true;
    let allPass = true; // 是否全部通过验证

    const componentRefs = diyGroupRef.value?.getFormRef?.()?.componentRefs;
    if (!componentRefs) return props.require_ready !== true;
    const checked = new Set();

    // 需要过滤 组件类型，筛选出来表单
    for (let i = 0; i < diyFormData.value.length; i++) {
        let item = diyFormData.value[i];
        const hasValue = item.field.value !== undefined && item.field.value !== null && item.field.value !== '' && (!Array.isArray(item.field.value) || item.field.value.length > 0);
        if (item.field.required || hasValue) {
            let refKey = `diy${ item.componentName }Ref`;
            let isBreak = false;
            if (!componentRefs[refKey]) { if (props.require_ready === true) return false; continue; }
            {
                const refs = Array.isArray(componentRefs[refKey]) ? componentRefs[refKey] : [componentRefs[refKey]];
                for (let k = 0; k < refs.length; k++) {
                    let compRef = refs[k];
                    if (checked.has(compRef)) continue;
                    checked.add(compRef);
                    if (typeof compRef?.verify !== 'function') return false;
                    let verify = compRef.verify(); // 验证表单组件数据
                    if (verify && !verify.code) {
                        isBreak = true;
                        uni.showToast({
                            title: verify.message,
                            icon: 'none'
                        });
                        break;
                    }

                }
                if (isBreak) {
                    allPass = false;
                    break;
                }
            }
        }
    }

    if (!allPass) return false;

    const data = {
        form_id: props.form_id,
        value: props.definition || props.initial_values ? { components: deepClone(diyFormData.value) } : uni.getStorageSync(cacheKey()),
        relate_id: props.relate_id || 0 // 关联业务id
    }
    if (props.storage_name && props.cache_enabled !== false) {
        uni.setStorageSync(props.storage_name, data)
    }
    return allPass;
}

// 获取数据
const getData = () => {
    return {
        form_id: props.form_id,
        value: diyFormData.value,
        relate_id: props.relate_id || 0 // 关联业务id
    }
}

const clearStorage = (keys: any = []) => {
    if (props.cache_enabled !== false) uni.removeStorageSync(cacheKey())
    if (props.storage_name && props.cache_enabled !== false) uni.removeStorageSync(props.storage_name)
    if (keys) {
        keys.forEach((key: any) => {
            uni.removeStorageSync(key)
        })
    }
}

// 监听页面隐藏
diy.onHide();

// 监听页面卸载
diy.onUnload();

// 监听滚动事件
// diy.onPageScroll()

defineExpose({
    verify,
    getData,
    clearStorage,
    reload,
    formReady
})

</script>

<style lang="scss">
.diy-template-wrap {
    /* #ifdef MP */
    .child-diy-template-wrap {
        ::v-deep .diy-group {
            > .draggable-element.top-fixed-diy {
                display: block !important;
            }
        }
    }

    /* #endif */
}
</style>
