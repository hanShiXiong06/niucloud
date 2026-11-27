<template>
    <view v-if="componentVisible" class="lead-profile-section" :style="componentStyle">
        <view v-if="frame.hasImage" class="lead-profile-section__mask" :style="maskStyle" />
        <view class="lead-profile-section__content">
            <view
                v-if="kind!=='benefits' && visibleFlag('showTitle') && settings.title"
                class="lead-profile-section__heading"
                ><text>{{ settings.title }}</text
                ><text
                    v-if="kind!=='promotion' && settings.subtitle"
                    class="lead-profile-section__subtitle"
                    >{{ settings.subtitle }}</text
                ></view
            ><text v-if="context.preview.value" class="lead-profile-section__demo" title="示例门店与金额仅供布局预览"
                >装修示例</text
            >
            <CoopMemberIdentity
                v-if="kind==='identity' && identityStyle==='member'"
                :profile="context.displayProfile.value"
                :member="context.displayMember.value"
                :appearance="resolvedAppearance"
                :preview="context.preview.value"
                @action="action($event)"
            />
            <CoopProfilePanel
                v-else
                :profile="context.displayProfile.value"
                :member="context.displayMember.value"
                :config="context.displayConfig.value"
                :providers="[]"
                display-only
                :section="kind"
                :appearance="resolvedAppearance"
                :busy="context.preview.value"
                @edit="action('profile')"
                @settings="action('settings')"
                @balance="action('balance')"
                @outgoing="action('outgoing')"
                @incoming="action('incoming')"
                @members="action('members')"
                @connection="action('connection')"
                @inventory="action('inventory')"
                @subscribe="action('subscribe')"
                @membership="action('membership')"
                @promotion="action('promotion', $event)"
            />
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed, watch } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'
import { useProfileDiyContext } from '../../composables/useProfileDiy'
import { cooperationDiyVars } from '../../utils/cooperation-diy-style'
import { cooperationDiyFrame } from '../../utils/cooperation-diy-frame'
import CoopProfilePanel from './CoopProfilePanel.vue'
import CoopMemberIdentity from './CoopMemberIdentity.vue'
import { profileIdentityStyle } from '../../utils/profile-identity'
const props = defineProps(['component', 'index', 'global', 'kind'])
const emit = defineEmits(['update:componentIsShow'])
const context = useProfileDiyContext(), store = useDiyStore()
const titles:Record<string,string>={identity:'我的门店',tools:'消息与偏好',benefits:'会员权益',promotion:'推广合作'}
const settings=computed(()=>({title:titles[props.kind]||'个人中心',subtitle:'',showTitle:props.kind==='identity'?0:1,...(store.mode==='decorate'?store.value[props.index]:props.component)}))
const visibleFlag=(key:string)=>![false,0,'0'].includes(settings.value[key])
// One existing DIY block, two mutually exclusive renderers. Saved store layouts
// are preserved; switching the editor style never duplicates member headers.
const identityStyle=computed(()=>context?.displayProfile.value.id ? profileIdentityStyle(settings.value.identityStyle) : 'member')
const legacyToolKeys=['showProfile','showCapability','showInventory','showConnection']
const availableToolKeys=computed(()=>['showNotice','showFeedback',...legacyToolKeys,'showMembers'].filter(key=>context?.displayConfig?.value?.erp_visible!==false||!['showInventory','showConnection'].includes(key)))
const componentVisible=computed(()=>!!context?.displayable.value&&(['identity','promotion'].includes(props.kind)||!!context?.displayProfile.value.id)&&(props.kind==='benefits'?visibleFlag('showMembership'):props.kind==='tools'?availableToolKeys.value.some(key=>settings.value[key]===undefined?legacyToolKeys.includes(key):visibleFlag(key)):true))
// The framework owns the schema and editor for backgrounds, images and radii.
// Keep these values on the outer component instead of repainting an inner card.
const frame = computed(() => cooperationDiyFrame(settings.value, img))
const componentStyle = computed(() => ({ ...cooperationDiyVars(settings.value), color:'var(--lead-diy-text)', ...frame.value.style }))
const maskStyle = computed(() => frame.value.mask)
const resolvedAppearance = computed(() => context?.preview.value ? { ...settings.value, showFeedback:0 } : settings.value)
function action(key:string,value='') { context?.action(key,value) }
watch(componentVisible,value=>emit('update:componentIsShow',value),{immediate:true})
</script>
<style scoped lang="scss">
.lead-profile-section { position: relative; overflow: hidden; }
.lead-profile-section__mask { position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none; }
.lead-profile-section__content { position: relative; }
.lead-profile-section__heading { display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:var(--lead-diy-content-padding,16px) var(--lead-diy-content-padding,16px) 0;font-size:var(--lead-diy-title-size,18px);font-weight:600;line-height:1.5;color:var(--lead-diy-title,#202124); }
.lead-profile-section__subtitle { font-size:var(--lead-diy-text-size,14px);font-weight:400;color:var(--lead-diy-subtle,#909399); }
.lead-profile-section__demo { display:block;text-align:right;padding:3px 12px;font-size:10px;font-weight:400;color:var(--lead-diy-subtle,#909399);background:transparent; }
</style>
