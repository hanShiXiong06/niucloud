import{B as e,C as r,b0 as l,i as t,j as o,w as s,F as a,d,r as u,o as n,P as p,n as i,k as f,m as x,G as c,H as m,R as _,e as b,x as g,q as y,t as h,I as v,y as j,K as w}from"./index-98826dc8.js";import{_ as k}from"./u-icon.f3535e52.js";import{_ as S}from"./u--image.6ab34ad7.js";import{p as C,u as F,b as V,a as I,_ as B}from"./u-form.4859819c.js";import{_ as P}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as z}from"./u-button.3ca081fb.js";import{_ as T}from"./u-modal.ea30a17a.js";import{_ as $}from"./u-loading-page.336cca7c.js";import{b as E,d as A,e as W,r as O}from"./refund.2e113ea5.js";import{l as D}from"./logistics-tracking.7f92acbb.js";import"./u-image.0f87688c.js";import"./u-transition.b9a2700d.js";import"./u-line.04b866e6.js";import"./u-loading-icon.9963a5a3.js";import"./u-popup.5ab022a0.js";import"./u-safe-bottom.35e4ae97.js";import"./u-tabs.fcf0ccab.js";import"./u-badge.8fcbc170.js";import"./u--text.c7d330c4.js";import"./order.f5c4f493.js";/* empty css                                                                       */const K=P({name:"u--input",mixins:[e,C,r],components:{uvInput:F}},[["render",function(e,r,d,u,n,p){const i=l("uvInput");return t(),o(i,{modelValue:e.modelValue,"onUpdate:modelValue":r[0]||(r[0]=r=>e.$emit("update:modelValue",r)),type:e.type,fixed:e.fixed,disabled:e.disabled,disabledColor:e.disabledColor,clearable:e.clearable,password:e.password,maxlength:e.maxlength,placeholder:e.placeholder,placeholderClass:e.placeholderClass,placeholderStyle:e.placeholderStyle,showWordLimit:e.showWordLimit,confirmType:e.confirmType,confirmHold:e.confirmHold,holdKeyboard:e.holdKeyboard,focus:e.focus,autoBlur:e.autoBlur,disableDefaultPadding:e.disableDefaultPadding,cursor:e.cursor,cursorSpacing:e.cursorSpacing,selectionStart:e.selectionStart,selectionEnd:e.selectionEnd,adjustPosition:e.adjustPosition,inputAlign:e.inputAlign,fontSize:e.fontSize,color:e.color,prefixIcon:e.prefixIcon,suffixIcon:e.suffixIcon,suffixIconStyle:e.suffixIconStyle,prefixIconStyle:e.prefixIconStyle,border:e.border,readonly:e.readonly,shape:e.shape,customStyle:e.customStyle,formatter:e.formatter,ignoreCompositionEvent:e.ignoreCompositionEvent},{default:s((()=>[a(e.$slots,"prefix",{slot:"prefix"}),a(e.$slots,"suffix",{slot:"suffix"})])),_:3},8,["modelValue","type","fixed","disabled","disabledColor","clearable","password","maxlength","placeholder","placeholderClass","placeholderStyle","showWordLimit","confirmType","confirmHold","holdKeyboard","focus","autoBlur","disableDefaultPadding","cursor","cursorSpacing","selectionStart","selectionEnd","adjustPosition","inputAlign","fontSize","color","prefixIcon","suffixIcon","suffixIconStyle","prefixIconStyle","border","readonly","shape","customStyle","formatter","ignoreCompositionEvent"])}]]);const R=P({name:"u--form",mixins:[e,V,r],components:{uvForm:I},created(){this.children=[]},methods:{setRules(e){this.$refs.uForm.setRules(e)},validate(){return this.$refs.uForm.validate()},validateField(e,r){return this.$refs.uForm.validateField(e,r)},resetFields(){return this.$refs.uForm.resetFields()},clearValidate(e){return this.$refs.uForm.clearValidate(e)},setMpData(){this.$refs.uForm.children=this.children}}},[["render",function(e,r,d,u,n,p){const i=l("uvForm");return t(),o(i,{ref:"uForm",model:e.model,rules:e.rules,errorType:e.errorType,borderBottom:e.borderBottom,labelPosition:e.labelPosition,labelWidth:e.labelWidth,labelAlign:e.labelAlign,labelStyle:e.labelStyle,customStyle:e.customStyle},{default:s((()=>[a(e.$slots,"default")])),_:3},8,["model","rules","errorType","borderBottom","labelPosition","labelWidth","labelAlign","labelStyle","customStyle"])}]]),H=P(d({__name:"detail",setup(e){let r=u({}),l=u(!0),a=u(""),d=u(""),C=u(!1);const F=u({express_number:"",express_company:"",remark:""}),V={express_number:{type:"string",required:!0,message:"请输入物流单号",trigger:["blur","change"]},express_company:{type:"string",required:!0,message:"请输入物流公司",trigger:["blur","change"]}};n((e=>{a.value=e.order_refund_no,d.value=e.type,C.value=e.is_edit_delivery,I(a.value)}));const I=e=>{l.value=!0,E(e).then((e=>{r.value=e.data,C.value&&r.value.delivery&&(F.value.express_number=r.value.delivery.express_number,F.value.express_company=r.value.delivery.express_company,F.value.remark=r.value.delivery.remark),l.value=!1})).catch((()=>{l.value=!1}))},P=e=>{b({url:"/shop/pages/goods/detail",param:{goods_id:e}})};let H=u(!1),U="";const q=()=>{A(U).then((e=>{H.value=!1,I(a.value)})).catch((()=>{H.value=!1}))},L=()=>{H.value=!1};let N=u();const G=()=>{N.value.validate().then((e=>{let l={delivery:F.value,order_refund_no:r.value.order_refund_no};(C.value?W(l):O(l)).then((e=>{setTimeout((()=>{b({url:"/shop/pages/refund/list"})}),500)})).catch((()=>{}))}))};return(e,u)=>{const n=g,C=y(h("u-icon"),k),I=y(h("u--image"),S),E=v,A=y(h("u--input"),K),W=y(h("u-form-item"),B),O=y(h("u--form"),R),M=y(h("u-button"),z),X=y(h("u-modal"),T),J=y(h("u-loading-page"),$);return t(),p(_,null,[i(l)?x("v-if",!0):(t(),o(n,{key:0,class:"bg-[#f8f8f8] min-h-screen overflow-hidden"},{default:s((()=>["logistics"!=i(d)?(t(),o(n,{key:0,class:"pb-[200rpx]"},{default:s((()=>[i(r).status_name?(t(),o(n,{key:0,class:"flex status-item text-[32rpx] bg-primary h-[170rpx]"},{default:s((()=>[f(n,{class:"ml-[50rpx]"},{default:s((()=>[-1!=["1","2","4","6","7"].indexOf(i(r).status)?(t(),p("img",{key:0,class:"w-[70rpx] h-[70rpx] mt-[45rpx]",src:i(j)("addon/shop/payment.png")},null,8,["src"])):x("v-if",!0),-1!=["8"].indexOf(i(r).status)?(t(),p("img",{key:1,class:"w-[70rpx] h-[70rpx] mt-[45rpx]",src:i(j)("addon/shop/complete.png")},null,8,["src"])):x("v-if",!0),-1!=["3","5","-1"].indexOf(i(r).status)?(t(),p("img",{key:2,class:"w-[70rpx] h-[70rpx] mt-[45rpx]",src:i(j)("addon/shop/close.png")},null,8,["src"])):x("v-if",!0)])),_:1}),f(n,{class:"ml-[20rpx] text-[#fff] mt-[50rpx] text-[40rpx]"},{default:s((()=>[c(m(i(r).status_name),1)])),_:1})])),_:1})):x("v-if",!0),f(n,{class:"bg-[#fff] mx-[30rpx] p-[30rpx] rounded-[10rpx] flex justify-between flex-wrap mt-[30rpx]"},{default:s((()=>[f(n,{class:"w-[160rpx] h-[160rpx] flex-2",onClick:u[0]||(u[0]=e=>P(i(r).order_goods.goods_id))},{default:s((()=>[f(I,{class:"rounded-[10rpx] overflow-hidden",width:"160rpx",height:"160rpx",src:i(j)(i(r).order_goods.sku_image?i(r).order_goods.sku_image:""),model:"aspectFill"},{error:s((()=>[f(C,{name:"photo",color:"#999",size:"50"})])),_:1},8,["src"])])),_:1}),f(n,{class:"ml-[20rpx] flex flex-1 flex-col justify-between"},{default:s((()=>[f(n,null,{default:s((()=>[f(E,{class:"text-[28rpx] text-item leading-[40rpx]"},{default:s((()=>[c(m(i(r).order_goods.goods_name),1)])),_:1}),i(r).order_goods.sku_name?(t(),o(n,{key:0,class:"flex"},{default:s((()=>[f(E,{class:"block text-[20rpx] text-item mt-[10rpx] text-[#ccc] bg-[#f5f5f5] px-[16rpx] py-[6rpx] rounded-[999rpx]"},{default:s((()=>[c(m(i(r).order_goods.sku_name),1)])),_:1})])),_:1})):x("v-if",!0)])),_:1}),f(n,{class:"flex justify-between"},{default:s((()=>[f(E,{class:"text-right text-[28rpx] text-[var(--price-text-color)] price-font"},{default:s((()=>[c("￥"+m(i(r).order_goods.price),1)])),_:1}),f(E,{class:"text-right text-sm"},{default:s((()=>[f(E,{class:"text-[26rpx]"},{default:s((()=>[c("x")])),_:1}),c(m(i(r).order_goods.num),1)])),_:1})])),_:1})])),_:1})])),_:1}),f(n,{class:"bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-[10rpx]"},{default:s((()=>[f(n,{class:"flex justify-between text-[28rpx] pt-[20rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("refundType")),1)])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).refund_type_name),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] pt-[20rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("refundCause")),1)])),_:1}),f(n,{class:"w-[400rpx] multi-hidden text-right leading-[1.2]"},{default:s((()=>[c(m(i(r).reason||"--"),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] pt-[20rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("refundNo")),1)])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).order_refund_no),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] pt-[20rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("createTime")),1)])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).create_time),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] pt-[20rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("createExplain")),1)])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).remark),1)])),_:1})])),_:1})])),_:1}),f(n,{class:"bg-[#fff] mx-[30rpx] p-[30rpx] mt-[30rpx] rounded-[10rpx]"},{default:s((()=>[f(n,{class:"flex justify-between text-[28rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]"},{default:s((()=>[f(n,null,{default:s((()=>[c(m(i(w)("record")),1)])),_:1}),f(n,{class:"flex items-center",onClick:u[1]||(u[1]=e=>i(b)({url:"/shop/pages/refund/log",param:{order_refund_no:i(a)}}))},{default:s((()=>[f(E,null,{default:s((()=>[c(m(i(w)("check")),1)])),_:1}),f(E,{class:"iconfont iconxiangyoujiantou text-xs"})])),_:1})])),_:1})])),_:1}),f(n,{class:"flex tab-bar justify-between items-center bg-[#fff] fixed left-0 right-0 bottom-0 min-h-[100rpx] px-1 flex-wrap"},{default:s((()=>[f(n,{class:"flex ml-[30rpx] w-[70rpx] flex-col justify-center items-center",onClick:u[2]||(u[2]=e=>{i(b)({url:"/shop/pages/index"})})},{default:s((()=>[f(E,{class:"iconfont iconshouye text-[32rpx]"}),f(E,{class:"text-xs mt-1"},{default:s((()=>[c(m(i(w)("index")),1)])),_:1})])),_:1}),f(n,{class:"flex justify-end mr-[30rpx]"},{default:s((()=>[-1==["6","7","8","-1"].indexOf(i(r).status)?(t(),o(n,{key:0,class:"inline-block text-[26rpx] leading-[56rpx] px-[30rpx] border-[3rpx] border-solid border-[#999] rounded-full ml-[20rpx]",onClick:u[3]||(u[3]=e=>{return l=i(r),U=l.order_refund_no,void(H.value=!0);var l})},{default:s((()=>[c(m(i(w)("refundApply")),1)])),_:1})):x("v-if",!0)])),_:1})])),_:1})])),_:1})):(t(),o(n,{key:1},{default:s((()=>[f(n,{class:"bg-[#fff] mx-[30rpx] p-[30rpx] rounded-[10rpx] flex justify-between flex-wrap mt-[30rpx]"},{default:s((()=>[f(n,{class:"w-[160rpx] h-[160rpx] flex-2",onClick:u[4]||(u[4]=e=>P(i(r).order_goods.goods_id))},{default:s((()=>[f(I,{class:"rounded-[10rpx] overflow-hidden",width:"160rpx",height:"160rpx",src:i(j)(i(r).order_goods.sku_image?i(r).order_goods.sku_image:""),model:"aspectFill"},{error:s((()=>[f(C,{name:"photo",color:"#999",size:"50"})])),_:1},8,["src"])])),_:1}),f(n,{class:"ml-[20rpx] flex flex-1 flex-col justify-between"},{default:s((()=>[f(n,null,{default:s((()=>[f(E,{class:"text-[28rpx] text-item leading-[40rpx]"},{default:s((()=>[c(m(i(r).order_goods.goods_name),1)])),_:1}),i(r).order_goods.sku_name?(t(),o(n,{key:0,class:"flex"},{default:s((()=>[f(E,{class:"block text-[20rpx] text-item mt-[10rpx] text-[#ccc] bg-[#f5f5f5] px-[16rpx] py-[6rpx] rounded-[999rpx]"},{default:s((()=>[c(m(i(r).order_goods.sku_name),1)])),_:1})])),_:1})):x("v-if",!0)])),_:1}),f(n,{class:"flex justify-between"},{default:s((()=>[f(E,{class:"text-right text-[28rpx] text-[var(--price-text-color)] price-font"},{default:s((()=>[c("￥"+m(i(r).order_goods.price),1)])),_:1}),f(E,{class:"text-right text-sm"},{default:s((()=>[f(E,{class:"text-[26rpx]"},{default:s((()=>[c("x")])),_:1}),c(m(i(r).order_goods.num),1)])),_:1})])),_:1})])),_:1})])),_:1}),f(n,{class:"bg-[#fff] mx-[30rpx] p-[30rpx] rounded-[10rpx] mt-[30rpx]"},{default:s((()=>[f(n,{class:"flex justify-between text-[28rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1]"},{default:s((()=>[f(n,null,{default:s((()=>[c("联系人")])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).contact_name),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c("手机号")])),_:1}),f(n,null,{default:s((()=>[c(m(i(r).mobile),1)])),_:1})])),_:1}),f(n,{class:"flex justify-between text-[28rpx] border-top-[2rpx] border-[solid] border-[#f1f1f1] mt-[40rpx]"},{default:s((()=>[f(n,null,{default:s((()=>[c("退货地址")])),_:1}),f(n,{class:"w-[460rpx] text-sm"},{default:s((()=>[c(m(i(r).refund_address.full_address||"--"),1)])),_:1})])),_:1})])),_:1}),f(n,{class:"bg-[#fff] mx-[30rpx] px-[30rpx] py-[10rpx] rounded-[10rpx] mt-[30rpx]"},{default:s((()=>[f(O,{labelPosition:"left",model:F.value,rules:V,ref_key:"deliveryForm",ref:N,labelWidth:"140rpx",labelStyle:{fontSize:"28rpx"}},{default:s((()=>[f(W,{label:"物流公司",prop:"express_company",borderBottom:"true",ref:"item1"},{default:s((()=>[f(A,{pl:"",border:"none",modelValue:F.value.express_company,"onUpdate:modelValue":u[5]||(u[5]=e=>F.value.express_company=e),placeholder:"请输入物流公司",placeholderClass:"text-sm",fontSize:"28rpx"},null,8,["modelValue"])])),_:1},512),f(W,{label:"物流单号",prop:"express_number",borderBottom:"true",ref:"item1"},{default:s((()=>[f(A,{border:"none",placeholder:"请输入物流单号",modelValue:F.value.express_number,"onUpdate:modelValue":u[6]||(u[6]=e=>F.value.express_number=e),placeholderClass:"text-sm",fontSize:"28rpx"},null,8,["modelValue"])])),_:1},512),f(W,{label:"物流说明",borderBottom:"",ref:"item1"},{default:s((()=>[f(A,{border:"none",placeholder:"选填",modelValue:F.value.remark,"onUpdate:modelValue":u[7]||(u[7]=e=>F.value.remark=e),placeholderClass:"text-sm",fontSize:"28rpx"},null,8,["modelValue"])])),_:1},512)])),_:1},8,["model"])])),_:1}),f(n,{class:"mx-[30rpx]"},{default:s((()=>[f(M,{class:"mt-[30rpx]",type:"primary",shape:"circle",onClick:G},{default:s((()=>[c("提交")])),_:1})])),_:1})])),_:1})),f(D,{ref:"materialRef"},null,512),f(X,{show:i(H),content:i(w)("cancelRefundContent"),showCancelButton:!0,closeOnClickOverlay:!0,onCancel:L,onConfirm:q},null,8,["show","content"])])),_:1})),f(J,{"bg-color":"rgb(248,248,248)",loading:i(l),fontSize:"16",color:"#333"},null,8,["loading"])],64)}}}),[["__scopeId","data-v-c003e505"]]);export{H as default};