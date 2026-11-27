<template>
    <view class="lead-entry" :class="['is-'+layout,{'with-motion':shown(settings.animate)}]" :style="entryStyle">
        <view v-if="frame.hasImage" class="lead-entry-mask" :style="frame.mask" />
        <view class="lead-entry-content">
            <view v-if="shown(settings.showTitle)&&settings.title" class="lead-entry-intro"
                ><text class="lead-entry-title">{{ settings.title }}</text
                ><text v-if="settings.subtitle" class="lead-entry-eyebrow">{{ settings.subtitle }}</text></view
            >
            <view class="lead-entry-actions">
                <CoopRoleEntryCard
                    v-for="item in roleEntries"
                    :key="item.key"
                    class="lead-entry-role"
                    :role="item.key"
                    :appearance="item.appearance"
                    :layout="item.appearance.layoutPreset || layout"
                    :preview="store.mode==='decorate'"
                />
            </view>
            <CoopBusinessNavigation
                v-if="shown(settings.showBusinesses)"
                ref="businessRef"
                class="lead-entry-businesses"
                :appearance="businessAppearance"
                embedded
            />
            <view v-if="shown(settings.showTools)" class="lead-entry-tools"
                ><button hover-class="lead-entry-action--pressed" @tap="go('profile')"
                    ><CoopBusinessIcon
                        name="store"
                        size="small"
                        :color="settings.iconColor" /><text>{{ settings.profileText }}</text
                    ><u-icon name="arrow-right" size="10" :color="settings.subtleColor" /></button
                ><button hover-class="lead-entry-action--pressed" @tap="go('wallet')"
                    ><CoopBusinessIcon
                        name="wallet"
                        size="small"
                        :color="settings.iconColor" /><text>{{ settings.walletText }}</text
                    ><u-icon name="arrow-right" size="10" :color="settings.subtleColor" /></button
            ></view>
        </view>
    </view>
</template>
<script setup lang="ts">
import {computed,onMounted,ref} from 'vue'
import useDiyStore from '@/app/stores/diy'
import {img} from '@/utils/common'
import CoopBusinessIcon from '../../cooperation/CoopBusinessIcon.vue'
import CoopBusinessNavigation from '../../cooperation/CoopBusinessNavigation.vue'
import CoopRoleEntryCard from '../../cooperation/CoopRoleEntryCard.vue'
import {cooperationDiyVars} from '../../../utils/cooperation-diy-style'
import { cooperationDiyFrame } from '../../../utils/cooperation-diy-frame'
import { cooperationRoleAppearance } from '../../../utils/cooperation-role-entry'
import {interactionFeedback} from '../../../utils/interaction-feedback'
const props=defineProps(['component','index','global']),emit=defineEmits(['update:componentIsShow'])
const store=useDiyStore(),businessRef=ref<any>(null)
const defaults={title:'商户协作',subtitle:'',showTitle:0,layoutPreset:'classic',usePresetImages:1,animate:1,showBusinesses:0,showTools:0,storeTitle:'找门店',customerTitle:'找客户',storeSubtitle:'发布客户需求，找门店交付',customerSubtitle:'查看交付给我的客户',storeActionText:'去找门店',customerActionText:'查看客户',businessTitle:'服务业务',profileText:'我的门店',walletText:'协作收益',storeImage:'',customerImage:'',titleColor:'#202124',textColor:'#606266',subtleColor:'#909399',iconColor:'#333333'}
const settings=computed(()=>({...defaults,...(store.mode==='decorate'?store.value[props.index]:props.component)}))
const shown=(value:unknown)=>![false,0,'0'].includes(value as any)
const layout=computed(()=>['classic','compact','clean','soft'].includes(settings.value.layoutPreset)?settings.value.layoutPreset:'classic')
const frame = computed(() => cooperationDiyFrame(settings.value, img))
const entryStyle = computed(() => ({ ...cooperationDiyVars(settings.value), ...frame.value.style }))
const businessAppearance=computed(()=>({...settings.value,title:settings.value.businessTitle,showTitle:settings.value.businessTitle?1:0,subtitle:'',layoutPreset:'compact'}))
const roleEntries=computed(()=>[
    {key:'stores',appearance:cooperationRoleAppearance(settings.value,'stores')},
    {key:'customers',appearance:cooperationRoleAppearance(settings.value,'customers')},
])
function go(target:string){if(store.mode==='decorate')return;const routes:Record<string,string>={stores:'stores/index',customers:'workbench/index',profile:'profile/index',wallet:'wallet/index'};if(!routes[target])return;interactionFeedback('navigate');uni.navigateTo({url:'/addon/hsx_lead/pages/'+routes[target]})}
onMounted(()=>emit('update:componentIsShow',true))
function refresh(){return businessRef.value?.refresh?.()}
defineExpose({refresh})
</script>
<style scoped lang="scss">
.lead-entry { padding:var(--lead-diy-content-padding,16px);color:var(--lead-diy-text,#606266);background:transparent; }
.lead-entry-mask { pointer-events:none; }
.lead-entry-content { position:relative; }
.lead-entry-intro { margin-bottom:16px; }
.lead-entry-title { display:block;font-size:var(--lead-diy-title-size,18px);font-weight:650;line-height:1.5;color:var(--lead-diy-title,#202124); }
.lead-entry-eyebrow { display:block;margin-top:4px;font-size:12px;line-height:1.6;color:var(--lead-diy-subtle,#909399); }
.lead-entry-actions { display:flex;flex-direction:column;gap:var(--lead-diy-row-gap,12px); }
.lead-entry-role { min-width:0; }
.lead-entry-tools button::after { border:0; }
.lead-entry-action--pressed { opacity:.65; }
.lead-entry-businesses { display:block;margin-top:20px; }
.lead-entry-tools { display:flex;gap:16px;border-top:1px solid #edf0f2;margin-top:16px;padding-top:12px; }
.lead-entry-tools button { flex:1;min-width:0;margin:0;padding:4px 0;display:flex;align-items:center;gap:8px;background:transparent;color:var(--lead-diy-text,#606266);font-size:var(--lead-diy-text-size,14px);line-height:22px;text-align:left;min-height:44px; }
.lead-entry-tools button>text { flex:1;min-width:0;overflow-wrap:anywhere; }
.lead-entry.is-compact .lead-entry-actions,.lead-entry.is-soft .lead-entry-actions { flex-direction:row; }
.lead-entry.is-compact .lead-entry-role,.lead-entry.is-soft .lead-entry-role { flex:1; }
</style>
