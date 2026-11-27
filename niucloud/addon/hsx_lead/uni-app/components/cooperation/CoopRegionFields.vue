<template>
    <view class="coop-region-fields">
        <button class="region-trigger" :disabled="disabled" @tap="open"
            ><u-icon name="map" size="18" color="#5379a7" /><text
                :class="{ placeholder: !selectedText }"
                >{{ selectedText || '搜索省、市、区县' }}</text
            ><u-icon name="arrow-right" size="13" color="#929aa6"
        /></button>
        <u-popup
            :show="show"
            mode="bottom"
            :round="14"
            :z-index="regionZIndex"
            :safe-area-inset-bottom="false"
            @close="close"
        >
            <view class="region-panel" @touchmove.prevent.stop>
                <view class="region-head"
                    ><text>选择地区</text
                    ><button aria-label="关闭地区选择" @tap="close"
                        ><u-icon name="close" size="18" color="#697586" /></button
                ></view>
                <view class="region-search"
                    ><CooperationSearch
                        v-model="keyword"
                        placeholder="输入地区名称，如石家庄、长安区"
                        :show-action="true"
                        action-text="搜索"
                        @search="search"
                        @custom="search"
                        @clear="clearSearch"
                /></view>
                <scroll-view scroll-y class="region-results" @touchmove.stop>
                    <view v-if="searching" class="region-message"
                        ><u-loading-icon size="22" /><text>正在查找地区</text></view
                    >
                    <view v-else-if="error" class="region-message"
                        ><text>{{ error }}</text
                        ><button @tap="search">重新查询</button></view
                    >
                    <template v-else-if="keyword.trim()">
                        <button v-for="area in results" :key="area.id" class="region-result" @tap="selectResult(area)">
                            <view class="region-result__content"
                                ><text class="region-name">{{ area.name }}</text
                                ><text class="region-path">{{ area.region_text }}</text></view
                            >
                            <view class="region-result__tail"
                                ><text class="region-level">{{ ['','省份','城市','区县'][area.level] }}</text
                                ><u-icon name="arrow-right" size="12" color="#91a0b2"
                            /></view>
                        </button>
                        <view v-if="searched && !results.length" class="region-message"
                            >没有找到，试试城市或区县名称</view
                        >
                        <view v-if="results.length && total > results.length" class="region-note"
                            >还有其他结果，可输入更完整的名称缩小范围</view
                        >
                    </template>
                    <view v-else class="region-message"
                        ><u-icon name="map" size="30" color="#a5b8d1" /><text
                            >输入名称，直接找到客户所在地区</text
                        ></view
                    >
                    <view class="region-alternative"
                        ><button @tap="hierarchy = !hierarchy"
                            >{{ hierarchy ? '收起逐级选择' : '按省市区选择'
                            }}<u-icon :name="hierarchy ? 'arrow-up' : 'arrow-down'" size="12" color="#8392a4" /></button
                    ></view>
                    <view v-if="hierarchy" class="region-levels"
                        ><picker
                            v-for="(field,index) in fields"
                            :key="field"
                            :range="options(index)"
                            range-key="name"
                            :value="selectedIndex(index)"
                            :disabled="busy || (index > 0 && !draft[fields[index-1]])"
                            @change="choose(index,$event)"
                            ><view class="region-choice"
                                >{{ names[index] || placeholders[index]
                                }}<u-icon name="arrow-down" size="11" color="#929aa6" /></view></picker
                        ><view v-if="levelError" class="region-note" @tap="initialize"
                            >{{ levelError }}，点击重试</view
                        ></view
                    >
                </scroll-view>
                <view class="region-footer"
                    ><text v-if="minLevel === 3">请选择到区县，便于准确匹配门店</text
                    ><u-button
                        v-if="hierarchy"
                        :custom-style="cooperationButtonStyle"
                        type="primary"
                        shape="circle"
                        text="使用所选地区"
                        :disabled="busy || !draft[fields[minLevel-1]]"
                        @click="applyDraft"
                    /><button class="region-cancel" @tap="close">取消</button></view
                >
            </view>
        </u-popup>
    </view>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { getAreaListByPid } from '@/app/api/system'
import { searchCooperationRegions } from '../../api/regions'
import { coopError, type CoopRow } from '../../api/cooperation'
import CooperationSearch from './CooperationSearch.vue'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
const props = withDefaults(defineProps<{ modelValue: CoopRow; disabled?: boolean; minLevel?: number }>(), { minLevel: 1 })
let regionZIndex = 11000
// #ifdef H5
regionZIndex = 998
// #endif
const emit = defineEmits<{ (event: 'update:modelValue', value: CoopRow): void; (event: 'overlay', active: boolean): void }>()
const fields = ['province_id','city_id','district_id'], placeholders = ['选择省份','选择城市','选择区县']
const show = ref(false), keyword = ref(''), searching = ref(false), searched = ref(false), results = ref<CoopRow[]>([]), total = ref(0), error = ref(''), hierarchy = ref(false)
const lists = reactive<CoopRow[][]>([[],[],[]]), draft = reactive<CoopRow>({}), names = ref<string[]>(['','','']), busy = ref(false), levelError = ref('')
let sequence = 0, searchSequence = 0, disposed = false, timer: ReturnType<typeof setTimeout> | undefined
const selectedText = computed(() => String(props.modelValue.region_text || fields.map(key => props.modelValue[key.replace('_id','_name')]).filter(Boolean).join(' / ')))
function options(index: number) { return [{ id: 0, name: placeholders[index] }, ...lists[index]] }
function selectedIndex(index: number) { return Math.max(0,options(index).findIndex(row => Number(row.id) === Number(draft[fields[index]]))) }
async function read(pid: number) { const result: any = await getAreaListByPid(pid); return Array.isArray(result.data) ? result.data : [] }
async function initialize() { const seq = ++sequence; busy.value = true; levelError.value = ''; try { const rows: CoopRow[][] = [await read(0),[],[]]; if (draft.province_id) rows[1] = await read(Number(draft.province_id)); if (draft.city_id) rows[2] = await read(Number(draft.city_id)); if (disposed || seq !== sequence) return; rows.forEach((row,index) => lists[index] = row); names.value = fields.map((key,index) => rows[index].find(row => Number(row.id) === Number(draft[key]))?.name || '') } catch (cause) { if (!disposed && seq === sequence) levelError.value = coopError(cause,'地区读取失败') } finally { if (!disposed && seq === sequence) busy.value = false } }
function open() { if (props.disabled) return; Object.assign(draft,props.modelValue); show.value = true; keyword.value = ''; results.value = []; searched.value = false; hierarchy.value = false; error.value = ''; emit('overlay',true); void initialize() }
function close() { show.value = false; searchSequence++; searching.value = false; if (timer) clearTimeout(timer); emit('overlay',false) }
async function search() { if (timer) clearTimeout(timer); const value = keyword.value.trim(), seq = ++searchSequence; if (!value) { clearSearch(); return }; searching.value = true; error.value = ''; try { const result: any = await searchCooperationRegions(value); if (disposed || seq !== searchSequence || !show.value) return; results.value = Array.isArray(result.data?.data) ? result.data.data : []; total.value = Number(result.data?.total || 0); searched.value = true } catch (cause) { if (!disposed && seq === searchSequence) error.value = coopError(cause,'地区查询失败') } finally { if (!disposed && seq === searchSequence) searching.value = false } }
function clearSearch() { searchSequence++; searching.value = false; searched.value = false; results.value = []; error.value = '' }
async function choose(index: number,event: any) { const row = options(index)[Number(event.detail.value)]; if (!row || busy.value) return; draft[fields[index]] = Number(row.id); names.value[index] = Number(row.id) ? String(row.name) : ''; for (let next = index+1; next<3; next++) { draft[fields[next]] = 0; names.value[next] = ''; lists[next] = [] }; if (index < 2 && Number(row.id)) { const seq = ++sequence; busy.value = true; levelError.value = ''; try { const rows = await read(Number(row.id)); if (!disposed && seq === sequence) lists[index+1] = rows } catch (cause) { if (!disposed && seq === sequence) levelError.value = coopError(cause,'下级地区读取失败') } finally { if (!disposed && seq === sequence) busy.value = false } } }
function applyDraft() { if (busy.value || !draft[fields[props.minLevel-1]]) return; emit('update:modelValue',{ ...props.modelValue, ...Object.fromEntries(fields.map(key => [key,Number(draft[key] || 0)])), region_text:names.value.filter(Boolean).join(' / ') }); close() }
function selectResult(area: CoopRow) { if (area.level < props.minLevel) { Object.assign(draft,area); hierarchy.value = true; keyword.value = ''; void initialize(); return }; emit('update:modelValue',{ ...props.modelValue, ...Object.fromEntries(fields.map(key => [key,Number(area[key] || 0)])), region_text:area.region_text }); close() }
watch(keyword,value => { if (timer) clearTimeout(timer); searchSequence++; searching.value = false; results.value = []; searched.value = false; if (value.trim()) timer = setTimeout(search,280) })
watch(() => props.disabled,value => { if (value && show.value) close() })
onBeforeUnmount(() => { disposed = true; sequence++; searchSequence++; if (timer) clearTimeout(timer); if (show.value) emit('overlay',false) })
</script>
<style scoped lang="scss">
.coop-region-fields{width:100%}.region-trigger{margin:0;padding:22rpx 24rpx;display:flex;align-items:center;gap:14rpx;width:100%;min-height:88rpx;background:#eef4fb;border:1rpx solid #dbe6f5;border-radius:12rpx;text-align:left;font-size:27rpx;line-height:40rpx;color:#263b54}.region-trigger::after,.region-head button::after,.region-result::after,.region-alternative button::after,.region-cancel::after{border:0}.region-trigger>text{flex:1;min-width:0;white-space:normal}.placeholder{color:#71859e}.region-panel{height:76vh;display:flex;flex-direction:column;background:#fff;border-radius:28rpx 28rpx 0 0;overflow:hidden}.region-head{display:flex;align-items:center;justify-content:space-between;padding:26rpx 30rpx;font-size:32rpx;font-weight:600;color:#24364d}.region-head button{display:flex;align-items:center;justify-content:center;margin:0;padding:0;width:78rpx;height:78rpx;background:transparent}.region-search{padding:0 28rpx 20rpx}.region-results{flex:1;min-height:0;height:0}.region-result{margin:0;padding:24rpx 30rpx;display:flex;align-items:center;gap:14rpx;text-align:left;background:#fff;border-radius:0;border-bottom:1rpx solid #edf1f6;line-height:1.6}.region-result>view{min-width:0;flex:1}.region-name{display:block;font-size:28rpx;color:#24364d}.region-path{display:block;font-size:23rpx;color:#7b899d}.region-level{flex:none;font-size:22rpx;color:#6d86a7;background:#eef4fb;padding:3rpx 12rpx;border-radius:7rpx}.region-message{padding:36rpx 28rpx;display:flex;align-items:center;justify-content:center;gap:16rpx;flex-direction:column;color:#7b899d;font-size:26rpx;text-align:center}.region-note{padding:16rpx 28rpx;color:#78889c;font-size:23rpx}.region-alternative{padding:16rpx 28rpx}.region-alternative button{display:flex;gap:14rpx;justify-content:center;align-items:center;font-size:25rpx;background:#f6f8fb;color:#5779a5;padding:8rpx;margin:0}.region-levels{display:flex;flex-wrap:wrap;gap:12rpx;padding:8rpx 28rpx 24rpx}.region-levels>picker{flex:1;min-width:0}.region-choice{display:flex;justify-content:space-between;align-items:center;gap:6rpx;background:#f3f6fa;padding:22rpx 12rpx;font-size:24rpx;color:#526b89;border-radius:9rpx}.region-footer{padding:16rpx 28rpx calc(20rpx + env(safe-area-inset-bottom));border-top:1rpx solid #edf1f6;flex:none}.region-footer>text{display:block;text-align:center;color:#7b899d;font-size:23rpx;margin:0 0 16rpx}.region-cancel{font-size:26rpx;line-height:78rpx;background:transparent;color:#718299;margin:0}
.region-result {
    width: 100%;
    box-sizing: border-box;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
}
.region-result>.region-result__content {
    flex: 1;
    min-width: 0;
}
.region-result>.region-result__tail {
    display: flex;
    flex: 0 0 auto;
    flex-direction: row;
    align-items: center;
    gap: 12rpx;
    white-space: nowrap;
}
.region-trigger { min-height: 48px; padding: 12px; gap: 8px; font-size: 14px; line-height: 22px; }
.region-head { padding: 12px 16px; font-size: 17px; }
.region-head button { width: 44px; height: 44px; flex: none; }
.region-search { padding: 0 16px 12px; }
.region-result { min-height: 64px; padding: 12px 16px; }
.region-name { font-size: 15px; }.region-path { font-size: 12px; }
.region-alternative button,.region-message button { min-height: 44px; box-sizing: border-box; font-size: 13px; }
.region-choice { min-height: 44px; box-sizing: border-box; font-size: 13px; padding: 10px 8px; }
.region-cancel { min-height: 44px; line-height: 44px; font-size: 14px; }
.region-footer { padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); }
.region-footer>text { font-size: 12px; line-height: 1.6; margin-bottom: 8px; }
.region-panel { min-height:0; max-height:calc(100vh - 12px - env(safe-area-inset-top)); box-sizing:border-box; }
.region-head,.region-search { flex:none; }
/* #ifdef H5 */
.region-panel { height:76dvh; max-height:calc(100dvh - 12px - env(safe-area-inset-top)); }
/* #endif */
</style>
