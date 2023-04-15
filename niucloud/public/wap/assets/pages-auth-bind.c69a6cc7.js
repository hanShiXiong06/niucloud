import{O as e,P as t,Q as a,k as l,l as i,e as o,f as r,w as s,h as n,I as c,R as u,n as h,S as d,x as p,y as b,m,G as f,d as k,q as C,c as g,r as x,a as y,p as _,H as S,j as v,B as D,T as z,U as $,V as B,N as G,E as V}from"./index-b8ec63bc.js";import{u as w}from"./u-input.0bd1cb2a.js";import{_ as P,a as j,b as I}from"./u-form.85110b0e.js";import{_ as A}from"./u-icon.45222ba8.js";import{_ as N}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as H}from"./app-link.vue_vue_type_script_setup_true_lang.f540e6c2.js";import{_ as L}from"./u-button.0fab1ad6.js";import"./u-line.17f02c8e.js";import"./u-modal.c86a68c4.js";import"./u-loading-icon.1f177a88.js";import"./u-popup.d867344c.js";import"./u-transition.eb56da8c.js";import"./u-safe-bottom.c00f6ea6.js";const T=N({name:"u-checkbox",mixins:[t,a,{props:{name:{type:[String,Number,Boolean],default:e.checkbox.name},shape:{type:String,default:e.checkbox.shape},size:{type:[String,Number],default:e.checkbox.size},checked:{type:Boolean,default:e.checkbox.checked},disabled:{type:[String,Boolean],default:e.checkbox.disabled},activeColor:{type:String,default:e.checkbox.activeColor},inactiveColor:{type:String,default:e.checkbox.inactiveColor},iconSize:{type:[String,Number],default:e.checkbox.iconSize},iconColor:{type:String,default:e.checkbox.iconColor},label:{type:[String,Number],default:e.checkbox.label},labelSize:{type:[String,Number],default:e.checkbox.labelSize},labelColor:{type:String,default:e.checkbox.labelColor},labelDisabled:{type:[String,Boolean],default:e.checkbox.labelDisabled}}}],data:()=>({isChecked:!1,parentData:{iconSize:12,labelDisabled:null,disabled:null,shape:"square",activeColor:null,inactiveColor:null,size:18,modelValue:null,iconColor:null,placement:"row",borderBottom:!1,iconPlacement:"left"}}),computed:{elDisabled(){return""!==this.disabled?this.disabled:null!==this.parentData.disabled&&this.parentData.disabled},elLabelDisabled(){return""!==this.labelDisabled?this.labelDisabled:null!==this.parentData.labelDisabled&&this.parentData.labelDisabled},elSize(){return this.size?this.size:this.parentData.size?this.parentData.size:21},elIconSize(){return this.iconSize?this.iconSize:this.parentData.iconSize?this.parentData.iconSize:12},elActiveColor(){return this.activeColor?this.activeColor:this.parentData.activeColor?this.parentData.activeColor:"#2979ff"},elInactiveColor(){return this.inactiveColor?this.inactiveColor:this.parentData.inactiveColor?this.parentData.inactiveColor:"#c8c9cc"},elLabelColor(){return this.labelColor?this.labelColor:this.parentData.labelColor?this.parentData.labelColor:"#606266"},elShape(){return this.shape?this.shape:this.parentData.shape?this.parentData.shape:"circle"},elLabelSize(){return uni.$u.addUnit(this.labelSize?this.labelSize:this.parentData.labelSize?this.parentData.labelSize:"15")},elIconColor(){const e=this.iconColor?this.iconColor:this.parentData.iconColor?this.parentData.iconColor:"#ffffff";return this.elDisabled?this.isChecked?this.elInactiveColor:"transparent":this.isChecked?e:"transparent"},iconClasses(){let e=[];return e.push("u-checkbox__icon-wrap--"+this.elShape),this.elDisabled&&e.push("u-checkbox__icon-wrap--disabled"),this.isChecked&&this.elDisabled&&e.push("u-checkbox__icon-wrap--disabled--checked"),e},iconWrapStyle(){const e={};return e.backgroundColor=this.isChecked&&!this.elDisabled?this.elActiveColor:"#ffffff",e.borderColor=this.isChecked&&!this.elDisabled?this.elActiveColor:this.elInactiveColor,e.width=uni.$u.addUnit(this.elSize),e.height=uni.$u.addUnit(this.elSize),"right"===this.parentData.iconPlacement&&(e.marginRight=0),e},checkboxStyle(){const e={};return this.parentData.borderBottom&&"row"===this.parentData.placement&&uni.$u.error("检测到您将borderBottom设置为true，需要同时将u-checkbox-group的placement设置为column才有效"),this.parentData.borderBottom&&"column"===this.parentData.placement&&(e.paddingBottom="8px"),uni.$u.deepMerge(e,uni.$u.addStyle(this.customStyle))}},mounted(){this.init()},methods:{init(){this.updateParentData(),this.parent||uni.$u.error("u-checkbox必须搭配u-checkbox-group组件使用");const e=this.parentData.modelValue;this.checked?this.isChecked=!0:uni.$u.test.array(e)&&(this.isChecked=e.some((e=>e===this.name)))},updateParentData(){this.getParentData("u-checkbox-group")},wrapperClickHandler(e){"right"===this.parentData.iconPlacement&&this.iconClickHandler(e)},iconClickHandler(e){this.preventEvent(e),this.elDisabled||this.setRadioCheckedStatus()},labelClickHandler(e){this.preventEvent(e),this.elLabelDisabled||this.elDisabled||this.setRadioCheckedStatus()},emitEvent(){this.$emit("change",this.isChecked),this.$nextTick((()=>{uni.$u.formValidate(this,"change")}))},setRadioCheckedStatus(){this.isChecked=!this.isChecked,this.emitEvent(),"function"==typeof this.parent.unCheckedOther&&this.parent.unCheckedOther(this)}},watch:{checked(){this.isChecked=this.checked}}},[["render",function(e,t,a,k,C,g){const x=l(i("u-icon"),A),y=m,_=f;return o(),r(y,{class:c(["u-checkbox",[`u-checkbox-label--${C.parentData.iconPlacement}`,C.parentData.borderBottom&&"column"===C.parentData.placement&&"u-border-bottom"]]),style:h([g.checkboxStyle]),onClick:u(g.wrapperClickHandler,["stop"])},{default:s((()=>[n(y,{class:c(["u-checkbox__icon-wrap",g.iconClasses]),onClick:u(g.iconClickHandler,["stop"]),style:h([g.iconWrapStyle])},{default:s((()=>[d(e.$slots,"icon",{},(()=>[n(x,{class:"u-checkbox__icon-wrap__icon",name:"checkbox-mark",size:g.elIconSize,color:g.elIconColor},null,8,["size","color"])]),!0)])),_:3},8,["onClick","class","style"]),n(_,{onClick:u(g.labelClickHandler,["stop"]),style:h({color:g.elDisabled?g.elInactiveColor:g.elLabelColor,fontSize:g.elLabelSize,lineHeight:g.elLabelSize})},{default:s((()=>[p(b(e.label),1)])),_:1},8,["onClick","style"])])),_:3},8,["style","onClick","class"])}],["__scopeId","data-v-fb07f37a"]]);const U=N({name:"u-checkbox-group",mixins:[t,a,{props:{name:{type:String,default:e.checkboxGroup.name},modelValue:{type:Array,default:e.checkboxGroup.value},shape:{type:String,default:e.checkboxGroup.shape},disabled:{type:Boolean,default:e.checkboxGroup.disabled},activeColor:{type:String,default:e.checkboxGroup.activeColor},inactiveColor:{type:String,default:e.checkboxGroup.inactiveColor},size:{type:[String,Number],default:e.checkboxGroup.size},placement:{type:String,default:e.checkboxGroup.placement},labelSize:{type:[String,Number],default:e.checkboxGroup.labelSize},labelColor:{type:[String],default:e.checkboxGroup.labelColor},labelDisabled:{type:Boolean,default:e.checkboxGroup.labelDisabled},iconColor:{type:String,default:e.checkboxGroup.iconColor},iconSize:{type:[String,Number],default:e.checkboxGroup.iconSize},iconPlacement:{type:String,default:e.checkboxGroup.iconPlacement},borderBottom:{type:Boolean,default:e.checkboxGroup.borderBottom}}}],computed:{parentData(){return[this.modelValue,this.disabled,this.inactiveColor,this.activeColor,this.size,this.labelDisabled,this.shape,this.iconSize,this.borderBottom,this.placement]},bemClass(){return this.bem("checkbox-group",["placement"])}},watch:{parentData:{handler(){this.children.length&&this.children.map((e=>{"function"==typeof e.init&&e.init()}))},deep:!0}},data:()=>({}),created(){this.children=[]},emits:["update:modelValue","change"],methods:{unCheckedOther(e){const t=[];this.children.map((e=>{e.isChecked&&t.push(e.name)})),this.$emit("change",t),this.$emit("update:modelValue",t)}}},[["render",function(e,t,a,l,i,n){const u=m;return o(),r(u,{class:c(["u-checkbox-group",n.bemClass])},{default:s((()=>[d(e.$slots,"default",{},void 0,!0)])),_:3},8,["class"])}],["__scopeId","data-v-f5bb36e2"]]),E=k({__name:"bind",setup(e){const t=C(),a=g((()=>t.info)),c=x(!1),u=x(!1),h=y({mobile:"",mobile_code:"",mobile_key:"",openid:_("openid")}),d={mobile:[{type:"string",required:!0,message:S("mobilePlaceholder"),trigger:["blur","change"]},{validator:(e,t)=>uni.$u.test.mobile(t),message:S("mobileError"),trigger:["change","blur"]}],mobile_code:{type:"string",required:!0,message:S("codePlaceholder"),trigger:["blur","change"]}},k=()=>{u.value=!u.value},A=x(null),N=()=>{A.value.validate().then((()=>{if(!u.value&&!a.value)return void z({title:`${S("pleaceAgree")}《${S("userAgreement")}》《${S("privacyAgreement")}》`,icon:"none"});if(c.value)return;c.value=!0;(a.value?$:B)(h).then((e=>{a.value?(t.getMemberInfo(),G({url:"/pages/member/personal",mode:"redirectTo"})):(t.setToken(e.data.token),V().handleLoginBack())})).catch((()=>{c.value=!1}))}))};return(e,t)=>{const C=m,g=l(i("u-input"),w),x=l(i("u-form-item"),P),y=l(i("sms-code"),j),_=l(i("u-checkbox"),T),z=l(i("u-checkbox-group"),U),$=f,B=l(i("app-link"),H),G=l(i("u-button"),L),V=l(i("u-form"),I);return o(),r(C,{class:"w-screen h-screen flex flex-col"},{default:s((()=>[n(C,{class:"flex-1"},{default:s((()=>[n(C,{class:"h-[100rpx]"}),n(C,{class:"px-[60rpx] pt-[100rpx] mb-[100rpx]"},{default:s((()=>[n(C,{class:"font-bold text-lg"},{default:s((()=>[p(b(v(S)("bindMobile")),1)])),_:1})])),_:1}),n(C,{class:"px-[60rpx]"},{default:s((()=>[n(V,{labelPosition:"left",model:h,errorType:"toast",rules:d,ref_key:"formRef",ref:A},{default:s((()=>[n(x,{label:"",prop:"mobile","border-bottom":!0},{default:s((()=>[n(g,{modelValue:h.mobile,"onUpdate:modelValue":t[0]||(t[0]=e=>h.mobile=e),border:"none",clearable:"",placeholder:v(S)("mobilePlaceholder")},null,8,["modelValue","placeholder"])])),_:1}),n(C,{class:"mt-[40rpx]"},{default:s((()=>[n(x,{label:"",prop:"mobile_code","border-bottom":!0},{default:s((()=>[n(g,{modelValue:h.mobile_code,"onUpdate:modelValue":t[2]||(t[2]=e=>h.mobile_code=e),border:"none",type:"password",clearable:"",placeholder:v(S)("codePlaceholder")},{suffix:s((()=>[n(y,{mobile:h.mobile,type:"bind_mobile",modelValue:h.mobile_key,"onUpdate:modelValue":t[1]||(t[1]=e=>h.mobile_key=e)},null,8,["mobile","modelValue"])])),_:1},8,["modelValue","placeholder"])])),_:1})])),_:1}),v(a)?D("",!0):(o(),r(C,{key:0,class:"flex items-start mt-[30rpx]"},{default:s((()=>[n(z,null,{default:s((()=>[n(_,{checked:u.value,shape:"shape",size:"14",onChange:k,customStyle:{marginTop:"4rpx"}},null,8,["checked"])])),_:1}),n(C,{class:"text-xs text-gray-400 flex flex-wrap"},{default:s((()=>[p(b(v(S)("agreeTips"))+" ",1),n(B,{url:"/pages/auth/agreement?key=service"},{default:s((()=>[n($,{class:"text-primary"},{default:s((()=>[p("《"+b(v(S)("userAgreement"))+"》",1)])),_:1})])),_:1}),n(B,{url:"/pages/auth/agreement?key=privacy"},{default:s((()=>[n($,{class:"text-primary"},{default:s((()=>[p("《"+b(v(S)("privacyAgreement"))+"》",1)])),_:1})])),_:1})])),_:1})])),_:1})),n(C,{class:"mt-[60rpx]"},{default:s((()=>[n(G,{type:"primary",loading:c.value,loadingText:v(S)("logining"),onClick:N},{default:s((()=>[p(b(v(S)("bind")),1)])),_:1},8,["loading","loadingText"])])),_:1})])),_:1},8,["model"])])),_:1})])),_:1})])),_:1})}}});export{E as default};
