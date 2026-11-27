<template>
    <div v-show="diyStore.editTab==='content'" class="content-wrap">
        <div class="edit-attr-item-wrap"
            ><h3 class="mb-[10px]">{{ definition.label }}</h3
            ><el-form label-width="100px" class="px-[10px]">
                <el-form-item v-if="kind==='identity'" label="展示版本"
                    ><el-radio-group v-model="diyStore.editComponent.identityStyle"
                        ><el-radio label="store">经典门店版</el-radio
                        ><el-radio label="member">会员中心版</el-radio></el-radio-group
                    ><p class="profile-editor-note"
                        >同一个组件切换展示，只显示选中的版本；设置入口沿用牛云的个人设置与退出登录。</p
                    ></el-form-item
                >
                <el-form-item label="区块标题"
                    ><el-input v-model.trim="diyStore.editComponent.title" maxlength="30" show-word-limit
                /></el-form-item>
                <el-form-item label="显示标题"
                    ><el-switch v-model="diyStore.editComponent.showTitle" :active-value="1" :inactive-value="0"
                /></el-form-item>
                <el-form-item label="补充文字"
                    ><el-input
                        v-model.trim="diyStore.editComponent.subtitle"
                        maxlength="100"
                        placeholder="选填，留空不显示"
                /></el-form-item>
                <el-form-item v-for="field in definition.texts" :key="field.key" :label="field.label"
                    ><el-input v-model.trim="diyStore.editComponent[field.key]" maxlength="30"
                /></el-form-item>
                <el-form-item v-if="kind!=='identity'" label="入口图片"
                    ><UploadImage v-model="diyStore.editComponent.entryImage" :limit="1" /><p
                        class="profile-editor-note"
                        >选填，留空使用统一图标；不会替换门店真实门头或等级标识。</p
                    ></el-form-item
                >
                <el-form-item v-if="kind==='tools'" label="通知入口图片"
                    ><UploadImage v-model="diyStore.editComponent.noticeImage" :limit="1"
                /></el-form-item>
                <el-form-item v-for="field in definition.switches" :key="field.key" :label="field.label"
                    ><el-switch v-model="diyStore.editComponent[field.key]" :active-value="1" :inactive-value="0"
                /></el-form-item>
                <el-collapse v-if="kind==='tools'" class="profile-editor-help"
                    ><el-collapse-item title="更多入口文案与素材" name="tools"
                        ><p class="profile-editor-note"
                            >普通菜单优先使用牛云“图文导航”修改。这里可按需打开额外入口，请避免与图文导航重复。</p
                        ><template v-for="field in toolEntries" :key="field.key"
                            ><el-form-item :label="field.label"
                                ><el-switch
                                    v-model="diyStore.editComponent[field.show]"
                                    :active-value="1"
                                    :inactive-value="0" /></el-form-item
                            ><template v-if="diyStore.editComponent[field.show]===1"
                                ><el-form-item label="入口文字"
                                    ><el-input
                                        v-model.trim="diyStore.editComponent[field.key]"
                                        maxlength="30" /></el-form-item
                                ><el-form-item label="入口图片"
                                    ><UploadImage
                                        v-model="diyStore.editComponent[field.image]"
                                        :limit="1" /></el-form-item></template></template></el-collapse-item
                ></el-collapse> </el-form
        ></div>
        <el-collapse class="profile-editor-help"
            ><el-collapse-item title="展示与业务规则" name="rules"
                ><p
                    >这里只调整文案、图标与外观。真实门店名称、等级、有效期、金额、权限和通知结果由业务返回；隐藏入口不会改变业务权限或已形成的状态。</p
                ></el-collapse-item
            ></el-collapse
        >
    </div>
    <div v-show="diyStore.editTab==='style'" class="style-wrap"
        ><DiyAppearanceFields :allow-border="false" /><slot name="style"
    /></div>
</template>
<script setup lang="ts">
import { computed,onMounted } from 'vue'
import UploadImage from '@/components/upload-image/index.vue'
import useDiyStore from '@/stores/modules/diy'
import DiyAppearanceFields from './DiyAppearanceFields.vue'
type ProfileSection='identity'|'tools'|'benefits'|'promotion'
const props=defineProps<{kind:ProfileSection;value?:Record<string,any>}>()
const diyStore=useDiyStore()
const appearanceDefaults={showTitle:1,primaryColor:'',panelColor:'',titleColor:'#202124',textColor:'#606266',subtleColor:'#909399',iconColor:'#333333',borderColor:'#e4e8ef',buttonColor:'',buttonTextColor:'#ffffff',titleSize:18,textSize:14,contentPadding:16,rowGap:12,itemRadius:8,buttonRadius:6,entryImage:'',subtitle:''}
const sections={
    identity:{name:'LeadProfileIdentity',label:'门店身份',defaults:{title:'我的门店',showTitle:0,identityStyle:'store',showRegion:1,showGrade:1,showMemberNo:1,showBalance:1,showSettings:1,showStats:1,buttonText:'门店资料',secondaryText:'门店成员',outgoingText:'找门店',incomingText:'找客户'},texts:[{key:'buttonText',label:'资料按钮'},{key:'secondaryText',label:'成员按钮'},{key:'outgoingText',label:'发单入口'},{key:'incomingText',label:'接单入口'}],switches:[{key:'showRegion',label:'门店地区'},{key:'showGrade',label:'商户等级'},{key:'showMemberNo',label:'用户编号'},{key:'showBalance',label:'账户余额'},{key:'showSettings',label:'设置入口'},{key:'showStats',label:'协作数据'}]},
    tools:{name:'LeadProfileTools',label:'消息与偏好',defaults:{title:'消息与偏好',showNotice:1,showFeedback:1,noticeText:'工单通知',feedbackText:'触感反馈',showProfile:0,showCapability:0,showInventory:0,showConnection:0,showMembers:0,profileText:'门店资料',capabilityText:'交付能力',inventoryText:'我的货盘',connectionText:'ERP连接',membersText:'门店成员',profileImage:'',capabilityImage:'',inventoryImage:'',connectionImage:'',membersImage:'',noticeImage:''},texts:[{key:'noticeText',label:'通知入口'},{key:'feedbackText',label:'触感设置'}],switches:[{key:'showNotice',label:'工单通知'},{key:'showFeedback',label:'触感反馈'}]},
    benefits:{name:'LeadProfileBenefits',label:'会员权益',defaults:{title:'会员权益',showMembership:1,buttonText:'查看会员权益',secondaryText:''},texts:[{key:'buttonText',label:'入口按钮'}],switches:[{key:'showMembership',label:'主体会员权益'}]},
    promotion:{name:'LeadProfilePromotion',label:'推广入口',defaults:{title:'推广合作',showInviter:1,buttonText:'推广中心',secondaryText:'服务商申请'},texts:[{key:'buttonText',label:'推广按钮'},{key:'secondaryText',label:'服务商按钮'}],switches:[{key:'showInviter',label:'邀请我的人'}]},
}
const toolEntries=[{key:'profileText',show:'showProfile',image:'profileImage',label:'门店资料'},{key:'capabilityText',show:'showCapability',image:'capabilityImage',label:'交付能力'},{key:'inventoryText',show:'showInventory',image:'inventoryImage',label:'门店库存'},{key:'connectionText',show:'showConnection',image:'connectionImage',label:'ERP连接'},{key:'membersText',show:'showMembers',image:'membersImage',label:'门店成员'}]
const definition=computed(()=>sections[props.kind])
onMounted(()=>{
    const component=diyStore.editComponent
    component.ignore=Array.isArray(component.ignore)?component.ignore:[]
    Object.entries({...appearanceDefaults,...definition.value.defaults}).forEach(([key,value])=>{if(component[key]===undefined||component[key]===null)component[key]=value})
    component.verify=(index:number)=>{
        const target=diyStore.value[index]
        if(!target||target.componentName!==definition.value.name)return{code:true,message:''}
        if(target.showTitle===1&&!String(target.title||'').trim())return{code:false,message:'请填写区块标题或关闭标题显示'}
        if(props.kind==='identity'&&!['store','member'].includes(target.identityStyle))return{code:false,message:'请选择会员信息展示版本'}
        const switches=[{key:'showTitle',label:'标题显示'},...definition.value.switches,...(props.kind==='tools'?toolEntries.map(field=>({key:field.show,label:field.label})):[])]
        for(const field of switches)if(![0,1].includes(target[field.key]))return{code:false,message:'请检查'+field.label+'的展示开关'}
        return{code:true,message:''}
    }
})
</script>
<style scoped>
.profile-editor-help{margin:12px 10px}.profile-editor-help p,.profile-editor-note{font-size:12px;line-height:1.8;color:var(--el-text-color-secondary);margin:6px 0 0}
</style>
