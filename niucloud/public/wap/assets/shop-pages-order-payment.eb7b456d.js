import{A as e,B as l,C as t,q as a,t as r,i as s,j as o,w as d,k as u,D as i,E as n,p as c,m as p,G as f,H as _,x,I as m,d as v,r as y,$ as b,c as h,n as g,l as k,P as w,R as C,Q as j,v as S,a7 as V,y as $,bn as z,aG as U,e as I,_ as A,a6 as H,aH as R,ar as D,a2 as B}from"./index-19cdd642.js";import{_ as F}from"./u-icon.608fbfc2.js";import{_ as K}from"./u--image.925f4ba8.js";import{_ as O}from"./u-transition.862f512b.js";import{_ as T}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as q}from"./u-tabbar.41e257d5.js";import{_ as E}from"./pay.39358680.js";import{e as N,f as L,h as G,i as P}from"./order.dabff7ae.js";import{_ as W}from"./u-tabs.07abd6a5.js";import{_ as Q}from"./u-button.16c348c3.js";import{_ as J}from"./u-popup.9c625195.js";import{_ as M}from"./u-loading-icon.448cb1c8.js";import{_ as X}from"./u-empty.946c9418.js";import{_ as Y,u as Z}from"./u-form.9db86430.js";import{_ as ee}from"./u-input.595594e2.js";import"./u-image.28b481c5.js";import"./u-safe-bottom.c40e3bad.js";import"./pay.cf1e9cdb.js";import"./wechat.e4f076c9.js";import"./u-badge.23b944d1.js";import"./u-line.1d59416b.js";const le=T({name:"u-alert",mixins:[l,t,{props:{title:{type:String,default:e.alert.title},type:{type:String,default:e.alert.type},description:{type:String,default:e.alert.description},closable:{type:Boolean,default:e.alert.closable},showIcon:{type:Boolean,default:e.alert.showIcon},effect:{type:String,default:e.alert.effect},center:{type:Boolean,default:e.alert.center},fontSize:{type:[String,Number],default:e.alert.fontSize}}}],data:()=>({show:!0}),computed:{iconColor(){return"light"===this.effect?this.type:"#fff"},iconName(){switch(this.type){case"success":return"checkmark-circle-fill";case"error":return"close-circle-fill";case"warning":default:return"error-circle-fill";case"info":return"info-circle-fill";case"primary":return"more-circle-fill"}}},methods:{clickHandler(){this.$emit("click")},closeHandler(){this.show=!1}}},[["render",function(e,l,t,v,y,b){const h=a(r("u-icon"),F),g=x,k=m,w=a(r("u-transition"),O);return s(),o(w,{mode:"fade",show:y.show},{default:d((()=>[u(g,{class:i(["u-alert",[`u-alert--${e.type}--${e.effect}`]]),onClick:n(b.clickHandler,["stop"]),style:c([e.$u.addStyle(e.customStyle)])},{default:d((()=>[e.showIcon?(s(),o(g,{key:0,class:"u-alert__icon"},{default:d((()=>[u(h,{name:b.iconName,size:"18",color:b.iconColor},null,8,["name","color"])])),_:1})):p("v-if",!0),u(g,{class:"u-alert__content",style:c([{paddingRight:e.closable?"20px":0}])},{default:d((()=>[e.title?(s(),o(k,{key:0,class:i(["u-alert__content__title",["dark"===e.effect?"u-alert__text--dark":`u-alert__text--${e.type}--light`]]),style:c([{fontSize:e.$u.addUnit(e.fontSize),textAlign:e.center?"center":"left"}])},{default:d((()=>[f(_(e.title),1)])),_:1},8,["style","class"])):p("v-if",!0),e.description?(s(),o(k,{key:1,class:i(["u-alert__content__desc",["dark"===e.effect?"u-alert__text--dark":`u-alert__text--${e.type}--light`]]),style:c([{fontSize:e.$u.addUnit(e.fontSize),textAlign:e.center?"center":"left"}])},{default:d((()=>[f(_(e.description),1)])),_:1},8,["style","class"])):p("v-if",!0)])),_:1},8,["style"]),e.closable?(s(),o(g,{key:1,class:"u-alert__close",onClick:n(b.closeHandler,["stop"])},{default:d((()=>[u(h,{name:"close",color:b.iconColor,size:"15"},null,8,["color"])])),_:1},8,["onClick"])):p("v-if",!0)])),_:1},8,["class","onClick","style"])])),_:1},8,["show"])}],["__scopeId","data-v-c81451cb"]]),te=v({__name:"select-coupon",props:{orderKey:{type:String,default:""}},emits:["confirm"],setup(e,{expose:l,emit:t}){const n=e,c=y(0),v=y([]),$=y([]),z=y(!1),U=y(null);b((()=>n.orderKey),(()=>{n.orderKey&&!v.value.length&&N({order_key:n.orderKey}).then((({data:e})=>{const l=[],a=[];e.length&&e.forEach((e=>{e.is_normal?l.push(e):a.push(e)})),$.value=a,v.value=l,l.length&&(U.value=l[0],t("confirm",U.value))})).catch()}),{immediate:!0});const I=h((()=>[{name:`可用优惠券（${v.value.length}）`,key:"normal"},{name:`不可用优惠券（${$.value.length}）`,key:"disabled"}])),A=e=>{c.value=e.index},H=()=>{t("confirm",U.value),z.value=!1};return l({open:()=>{z.value=!0},couponList:v}),(e,l)=>{const t=x,n=a(r("u-tabs"),W),y=m,b=V,h=a(r("u-button"),Q),R=a(r("u-popup"),J);return s(),o(R,{show:z.value,onClose:l[0]||(l[0]=e=>z.value=!1),mode:"bottom",round:10,closeable:!0},{default:d((()=>[u(t,{class:"text-center p-[30rpx]"},{default:d((()=>[f("请选择优惠券")])),_:1}),e.type?p("v-if",!0):(s(),o(t,{key:0,class:"border-0 !border-b !border-[#eee] border-solid"},{default:d((()=>[u(n,{list:g(I),onClick:A,current:c.value,itemStyle:"width:50%;height:88rpx;box-sizing: border-box;"},null,8,["list","current"])])),_:1})),u(b,{"scroll-y":"true",class:"h-[50vh]"},{default:d((()=>[k(u(t,{class:"p-[30rpx] pt-0 text-sm"},{default:d((()=>[(s(!0),w(C,null,j(v.value,(e=>(s(),o(t,{class:i(["mt-[30rpx] p-[30rpx] border-1 !border-[#eee] border-solid rounded-[20rpx]",{"!border-primary bg-primary-light":U.value&&U.value.id==e.id}]),onClick:l=>{return t=e,void(U.value?U.value=U.value.id!=t.id?t:null:U.value=t);var t}},{default:d((()=>[u(t,{class:i(["flex border-0 !border-b !border-[#eee] border-dashed pb-[20rpx]",{"!border-primary":U.value&&U.value.id==e.id}])},{default:d((()=>[u(t,{class:"flex-1 w-0"},{default:d((()=>[u(t,{class:"text-base font-bold"},{default:d((()=>[f(_(e.title),1)])),_:2},1024),e.min_condition_money>0?(s(),o(t,{key:0},{default:d((()=>[f("满"+_(e.min_condition_money)+"可用",1)])),_:2},1024)):(s(),o(t,{key:1},{default:d((()=>[f("无门槛券")])),_:1}))])),_:2},1024),u(t,{class:"font-bold text-base price-font"},{default:d((()=>[u(y,{class:"text-xs"},{default:d((()=>[f("￥")])),_:1}),f(_(e.price),1)])),_:2},1024)])),_:2},1032,["class"]),u(t,{class:"pt-[20rpx] text-xs"},{default:d((()=>[f(_(e.create_time)+" ~ "+_(e.expire_time)+"期间有效",1)])),_:2},1024)])),_:2},1032,["class","onClick"])))),256))])),_:1},512),[[S,0==c.value]]),k(u(t,{class:"p-[30rpx] pt-0 text-sm"},{default:d((()=>[(s(!0),w(C,null,j($.value,(e=>(s(),o(t,{class:"mt-[30rpx] p-[30rpx] border-1 !border-[#eee] border-solid rounded-[20rpx] bg-[#f5f5f5]"},{default:d((()=>[u(t,{class:"flex border-0 !border-b !border-[#ddd] border-dashed pb-[20rpx]"},{default:d((()=>[u(t,{class:"flex-1 w-0"},{default:d((()=>[u(t,{class:"text-base font-bold"},{default:d((()=>[f(_(e.title),1)])),_:2},1024),e.min_condition_money>0?(s(),o(t,{key:0},{default:d((()=>[f("满"+_(e.min_condition_money)+"可用",1)])),_:2},1024)):(s(),o(t,{key:1},{default:d((()=>[f("无门槛券")])),_:1}))])),_:2},1024),u(t,{class:"font-bold text-base price-font"},{default:d((()=>[u(y,{class:"text-xs"},{default:d((()=>[f("￥")])),_:1}),f(_(e.price),1)])),_:2},1024)])),_:2},1024),u(t,{class:"pt-[20rpx] text-xs"},{default:d((()=>[f(_(e.create_time)+" ~ "+_(e.expire_time)+"期间有效",1)])),_:2},1024),u(t,{class:"text-xs pt-[10rpx] flex"},{default:d((()=>[f(" 不可用原因："+_(e.error),1)])),_:2},1024)])),_:2},1024)))),256))])),_:1},512),[[S,1==c.value]])])),_:1}),u(t,{class:"p-[30rpx]"},{default:d((()=>[u(h,{type:"primary",shape:"circle",onClick:H},{default:d((()=>[f("确认")])),_:1})])),_:1})])),_:1},8,["show"])}}}),ae=v({__name:"select-store",emits:["confirm"],setup(e,{expose:l,emit:t}){const n=y(!1),c=y(!1),v=y(!0),b=y([]),h=y(null),k=()=>{t("confirm",h.value),n.value=!1};return l({open:()=>{if(!c.value){c.value=!0;const e={};z({success:l=>{e.lat=l.latitude,e.lng=l.longitude}}),setTimeout((()=>{L({latlng:e}).then((({data:e})=>{b.value=e,v.value=!1})).catch((()=>{v.value=!1}))}),1500)}n.value=!0}}),(e,l)=>{const t=x,c=m,y=a(r("u-loading-icon"),M),S=a(r("u-empty"),X),z=V,U=a(r("u-button"),Q),I=a(r("u-popup"),J);return s(),o(I,{show:n.value,onClose:l[0]||(l[0]=e=>n.value=!1),mode:"bottom",round:10,closeable:!0},{default:d((()=>[u(t,{class:"text-center p-[30rpx]"},{default:d((()=>[f("请选择自提点")])),_:1}),u(z,{"scroll-y":"true",class:"h-[50vh]"},{default:d((()=>[u(t,{class:"p-[30rpx] pt-0 text-sm"},{default:d((()=>[(s(!0),w(C,null,j(b.value,(e=>(s(),o(t,{class:i(["mt-[30rpx] p-[30rpx] border-1 !border-[#eee] border-solid rounded-[20rpx]",{"!border-primary bg-primary-light":h.value&&h.value.store_id==e.store_id}]),onClick:l=>{return t=e,void(h.value?h.value=h.value.store_id!=t.store_id?t:null:h.value=t);var t}},{default:d((()=>[u(t,{class:"font-bold flex"},{default:d((()=>[u(t,{class:"flex-1 w-0"},{default:d((()=>[u(c,null,{default:d((()=>[f(_(e.store_name),1)])),_:2},1024),u(c,{class:"text-[26rpx] ml-[20rpx]"},{default:d((()=>[f(_(e.store_mobile),1)])),_:2},1024)])),_:2},1024),e.distance?(s(),o(t,{key:0},{default:d((()=>[u(c,{class:"text-red text-xs font-normal"},{default:d((()=>[f(_(e.distance)+"m",1)])),_:2},1024)])),_:2},1024)):p("v-if",!0)])),_:2},1024),u(t,{class:"mt-[16rpx] text-[26rpx]"},{default:d((()=>[f(_(e.full_address),1)])),_:2},1024),u(t,{class:"mt-[16rpx] text-[26rpx]"},{default:d((()=>[f("营业时间："+_(e.trade_time),1)])),_:2},1024)])),_:2},1032,["class","onClick"])))),256))])),_:1}),v.value?(s(),o(t,{key:0,class:"h-[50vh] flex items-center flex-col justify-center"},{default:d((()=>[u(y,{vertical:!0})])),_:1})):p("v-if",!0),v.value||b.value.length?p("v-if",!0):(s(),o(t,{key:1,class:"h-[50vh] flex items-center flex-col justify-center"},{default:d((()=>[u(S,{text:"没有可选择的自提点",icon:g($)("static/resource/images/empty.png")},null,8,["icon"])])),_:1}))])),_:1}),u(t,{class:"p-[30rpx]"},{default:d((()=>[u(U,{type:"primary",shape:"circle",onClick:k},{default:d((()=>[f("确认")])),_:1})])),_:1})])),_:1},8,["show"])}}});const re=v({__name:"invoice",emits:["confirm"],setup(e,{expose:l,emit:t}){const n=y(!1),c=y({is_invoice:2,invoice_content:[]}),p=y(!1),v=y(!1),b=y({header_type:1,header_name:"",type:"",name:"",tax_number:"",telephone:"",address:"",bank_name:"",bank_card_number:""}),$=h((()=>1==c.value.is_invoice));U.get("shop/config/invoice").then((({data:e})=>{c.value=e,e.invoice_content.length&&(b.value.name=e.invoice_content[0])})).catch();const z=y(null),I=h((()=>({header_name:{type:"string",required:p.value,message:"请输入发票抬头",trigger:["blur","change"]},tax_number:{type:"string",required:p.value&&2==b.value.header_type,message:"请输入纳税人识别号",trigger:["blur","change"]}}))),A=()=>{z.value.validate().then((()=>{const e=p.value?b.value:{};t("confirm",e),n.value=!1}))};return l({open:()=>{n.value=!0},invoiceOpen:$}),(e,l)=>{const t=x,y=a(r("u-form-item"),Y),h=a(r("u-input"),ee),$=m,U=a(r("u-form"),Z),H=V,R=a(r("u-button"),Q),D=a(r("u-popup"),J);return s(),o(D,{show:n.value,onClose:l[11]||(l[11]=e=>n.value=!1),mode:"bottom",round:10,closeable:!0},{default:d((()=>[u(t,{class:"text-center p-[30rpx]"},{default:d((()=>[f("请填写发票信息")])),_:1}),u(H,{"scroll-y":"true",class:"h-[50vh]"},{default:d((()=>[u(t,{class:"p-[30rpx] pt-0 text-sm"},{default:d((()=>[u(U,{labelPosition:"left",model:b.value,labelWidth:"200rpx",errorType:"toast",rules:g(I),ref_key:"formRef",ref:z},{default:d((()=>[u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"需要发票","border-bottom":!0},{default:d((()=>[u(t,{class:"flex"},{default:d((()=>[u(t,{class:i(["rounded px-[30rpx] py-[10rpx] mr-[20rpx] border-1 !border-[#eee] border-solid text-sm",{"bg-primary text-white !border-primary":!p.value}]),onClick:l[0]||(l[0]=e=>p.value=!1)},{default:d((()=>[f("不需要")])),_:1},8,["class"]),u(t,{class:i(["rounded px-[30rpx] py-[10rpx] border-1 !border-[#eee] border-solid text-sm",{"bg-primary text-white !border-primary":p.value}]),onClick:l[1]||(l[1]=e=>p.value=!0)},{default:d((()=>[f("需要")])),_:1},8,["class"])])),_:1})])),_:1})])),_:1}),k(u(t,null,{default:d((()=>[u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"抬头类型","border-bottom":!0},{default:d((()=>[u(t,{class:"flex"},{default:d((()=>[u(t,{class:i(["rounded px-[30rpx] py-[10rpx] mr-[20rpx] border-1 !border-[#eee] border-solid text-sm",{"bg-primary text-white !border-primary":1==b.value.header_type}]),onClick:l[2]||(l[2]=e=>b.value.header_type=1)},{default:d((()=>[f("个人")])),_:1},8,["class"]),u(t,{class:i(["rounded px-[30rpx] py-[10rpx] border-1 !border-[#eee] border-solid text-sm",{"bg-primary text-white !border-primary":2==b.value.header_type}]),onClick:l[3]||(l[3]=e=>b.value.header_type=2)},{default:d((()=>[f("企业")])),_:1},8,["class"])])),_:1})])),_:1})])),_:1}),u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"发票内容",prop:"header_name","border-bottom":!0},{default:d((()=>[u(t,{class:"flex"},{default:d((()=>[(s(!0),w(C,null,j(c.value.invoice_content,(e=>(s(),o(t,{class:i(["rounded px-[30rpx] py-[10rpx] mr-[20rpx] border-1 !border-[#eee] border-solid text-sm",{"bg-primary text-white !border-primary":b.value.name==e}]),onClick:l=>b.value.name=e},{default:d((()=>[f(_(e),1)])),_:2},1032,["class","onClick"])))),256))])),_:1})])),_:1})])),_:1}),u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"发票抬头",prop:"header_name","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.header_name,"onUpdate:modelValue":l[4]||(l[4]=e=>b.value.header_name=e),border:"none",clearable:"",placeholder:"请输入发票抬头"},null,8,["modelValue"])])),_:1})])),_:1}),k(u(t,null,{default:d((()=>[u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"纳税人识别号",prop:"tax_number","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.tax_number,"onUpdate:modelValue":l[5]||(l[5]=e=>b.value.tax_number=e),border:"none",clearable:"",placeholder:"请输入纳税人识别号"},null,8,["modelValue"])])),_:1})])),_:1}),u(t,{class:"py-[20rpx] flex items-end"},{default:d((()=>[u($,{class:"text-[30rpx]"},{default:d((()=>[f("更多选填内容")])),_:1}),u($,{class:"text-xs text-gray-subtitle ml-[10rpx]"},{default:d((()=>[f("注册地址、电话、开户银行及账号")])),_:1}),u(t,{class:"text-xs text-right flex-1",onClick:l[6]||(l[6]=e=>v.value=!v.value)},{default:d((()=>[u($,null,{default:d((()=>[f(_(v.value?"收起":"展开"),1)])),_:1}),u($,{class:i(["text-xs iconfont text-gray-subtitle ml-[5rpx]",v.value?"iconjiantoushang":"iconxialajiantouxiao"])},null,8,["class"])])),_:1})])),_:1}),k(u(t,null,{default:d((()=>[u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"注册地址","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.address,"onUpdate:modelValue":l[7]||(l[7]=e=>b.value.address=e),border:"none",clearable:"",placeholder:"(选填)请输入企业注册地址"},null,8,["modelValue"])])),_:1})])),_:1}),u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"注册电话","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.telephone,"onUpdate:modelValue":l[8]||(l[8]=e=>b.value.telephone=e),border:"none",clearable:"",placeholder:"(选填)请输入企业注册电话"},null,8,["modelValue"])])),_:1})])),_:1}),u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"开户银行","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.bank_name,"onUpdate:modelValue":l[9]||(l[9]=e=>b.value.bank_name=e),border:"none",clearable:"",placeholder:"(选填)请输入企业开户银行"},null,8,["modelValue"])])),_:1})])),_:1}),u(t,{class:"mt-[10rpx]"},{default:d((()=>[u(y,{label:"银行账号","border-bottom":!0},{default:d((()=>[u(h,{modelValue:b.value.bank_card_number,"onUpdate:modelValue":l[10]||(l[10]=e=>b.value.bank_card_number=e),border:"none",clearable:"",placeholder:"(选填)请输入企业开户银行账号"},null,8,["modelValue"])])),_:1})])),_:1})])),_:1},512),[[S,v.value]])])),_:1},512),[[S,2==b.value.header_type]])])),_:1},512),[[S,p.value]])])),_:1},8,["model","rules"])])),_:1})])),_:1}),u(t,{class:"p-[30rpx]"},{default:d((()=>[u(R,{type:"primary",shape:"circle",onClick:A},{default:d((()=>[f("确认")])),_:1})])),_:1})])),_:1},8,["show"])}}}),se=T(v({__name:"payment",setup(e){const l=y({order_key:"",member_remark:"",discount:{},invoice:{},delivery:{delivery_type:""}}),t=y(null),n=y(),c=y(),v=y(),b=y(),k=y(!1),S=y(0),z=y([]);uni.getStorageSync("orderCreateData")&&Object.assign(l.value,uni.getStorageSync("orderCreateData"));const U=uni.getStorageSync("selectAddressCallback");U&&(l.value.order_key="",l.value.delivery.delivery_type=U.delivery,l.value.delivery.take_address_id=U.address_id,uni.removeStorage({key:"selectAddressCallback"}));const O=()=>{G(l.value).then((({data:e})=>{t.value=e,l.value.order_key=e.order_key,z.value=Object.values(t.value.delivery.delivery_type_list),U&&(S.value=z.value.findIndex((e=>e.key===t.value.delivery.delivery_type))),!l.value.delivery.delivery_type&&e.delivery.delivery_type&&(l.value.delivery.delivery_type=e.delivery.delivery_type)})).catch()};O();let T=0;const N=()=>{L()&&!k.value&&(k.value=!0,P(l.value).then((({data:e})=>{var l;T=e.order_id,0==t.value.basic.order_money?I({url:"/shop/pages/order/detail",param:{order_id:T},mode:"redirectTo"}):null==(l=v.value)||l.open(e.trade_type,e.order_id,`/shop/pages/order/detail?order_id=${e.order_id}`)})).catch((()=>{k.value=!1})))},L=()=>{const e=l.value;if(t.value.basic.has_goods_types.includes("real")){if(["express","local_delivery"].includes(e.delivery.delivery_type)&&!t.value.delivery.take_address)return A({title:"请选择收货地址",icon:"none"}),!1;if("store"==e.delivery.delivery_type&&!e.delivery.take_store_id)return A({title:"请选择自提点",icon:"none"}),!1}return!0},W=()=>{I({url:"/shop/pages/order/detail",param:{order_id:T},mode:"redirectTo"})},Q=()=>{uni.setStorage({key:"selectAddressCallback",data:{back:"/shop/pages/order/payment",delivery:l.value.delivery.delivery_type},success(){I({url:"/app/pages/member/address",param:{type:"local_delivery"==l.value.delivery.delivery_type?"location_address":"address"}})}})},J=h((()=>{var e;return(null==(e=n.value)?void 0:e.couponList)||[]})),M=e=>{l.value.discount.coupon_id=e?e.id:0,O()},X=e=>{l.value.delivery.take_store_id=e.store_id||0,O()},Y=e=>{l.value.invoice=e};return(e,y)=>{const h=m,U=x,I=a(r("u-icon"),F),A=a(r("u--image"),K),T=a(r("u-alert"),le),L=H,G=R,P=a(r("u-tabbar"),q),Z=a(r("pay"),E),ee=V;return t.value?(s(),o(ee,{key:0,"scroll-y":"true",class:"bg-page h-screen"},{default:d((()=>[u(U,{class:"py-[20rpx] px-[24rpx]",style:{background:"linear-gradient(var(--primary-color) 0%, var(--primary-color-disabled) 7%, #F4F4F6 10%)"}},{default:d((()=>[p(" 配送方式 "),t.value.basic.has_goods_types.includes("real")?(s(),o(U,{key:0,class:"mb-[20rpx] rounded-[16rpx] bg-white"},{default:d((()=>[u(U,{class:"flex items-center rounded-tl-[16rpx] rounded-tr-[16rpx] overflow-hidden w-full"},{default:d((()=>[(s(!0),w(C,null,j(z.value,((e,t)=>(s(),o(U,{class:i(["flex-1",{"bg-[#fff]":t===S.value-1||t===S.value+1,"bg-[var(--primary-color-disabled)]":!(t===S.value-1||t===S.value+1)}]),key:t},{default:d((()=>[u(U,{class:i(["h-[74rpx] text-center leading-[74rpx] text-[30rpx] font-500",{"bg-[var(--primary-color-disabled)]":t===S.value-1||t===S.value+1,"rounded-br-[16rpx]":t===S.value-1,"rounded-tr-[16rpx] rounded-tl-[16rpx] bg-[#fff] text-[var(--primary-color)]":t===S.value,"rounded-bl-[16rpx]":t===S.value+1}]),onClick:a=>((e,t)=>{l.value.delivery.delivery_type!=e&&(S.value=t,l.value.order_key="",l.value.delivery.delivery_type=e,l.value.delivery.take_address_id=0,O())})(e.key,t)},{default:d((()=>[u(h,null,{default:d((()=>[f(_(e.name),1)])),_:2},1024)])),_:2},1032,["class","onClick"]),p(' <u-button :text="item.name" type="primary" v-if="createData.delivery.delivery_type == item.key" @click="switchDeliveryType(item.key)"></u-button>\r\n                    <u-button :text="item.name" v-else></u-button> ')])),_:2},1032,["class"])))),128))])),_:1}),u(U,{class:"p-[24rpx]"},{default:d((()=>[p(" 收货地址 "),["express","local_delivery"].includes(l.value.delivery.delivery_type)?(s(),o(U,{key:0,class:"flex items-center pt-[24rpx] pb-[10rpx]",onClick:Q},{default:d((()=>[u(U,{class:"flex-1 w-0"},{default:d((()=>[e.$u.test.isEmpty(t.value.delivery.take_address)?(s(),o(U,{key:1,class:"text-[26rpx]"},{default:d((()=>[f(" 添加收货地址 ")])),_:1})):(s(),o(U,{key:0},{default:d((()=>[u(U,{class:"font-500 text-[30rpx] mb-[10rpx]"},{default:d((()=>[f(_(t.value.delivery.take_address.name)+" ",1),u(h,{class:"text-[30rpx]"},{default:d((()=>[f(_(g(D)(t.value.delivery.take_address.mobile)),1)])),_:1})])),_:1}),u(U,{class:"text-[26rpx] text-gray-subtitle mt-[10rpx]"},{default:d((()=>[f(_(t.value.delivery.take_address.full_address),1)])),_:1})])),_:1}))])),_:1}),u(h,{class:"iconfont iconxiangyoujiantou text-[26rpx] text-gray-subtitle"})])),_:1})):p("v-if",!0),p(" 自提点 "),"store"==l.value.delivery.delivery_type?(s(),o(U,{key:1,class:"flex items-center pt-[24rpx] pb-[10rpx]",onClick:y[0]||(y[0]=e=>c.value.open())},{default:d((()=>[u(U,{class:"flex-1 w-0"},{default:d((()=>[e.$u.test.isEmpty(t.value.delivery.take_store)?(s(),o(U,{key:1,class:"text-[26rpx]"},{default:d((()=>[f(" 请选择自提点 ")])),_:1})):(s(),o(U,{key:0},{default:d((()=>[u(U,{class:"font-500 text-[30rpx] mb-[10rpx]"},{default:d((()=>[f(_(t.value.delivery.take_store.store_name)+" ",1),u(h,{class:"text-[26rpx]"},{default:d((()=>[f(_(t.value.delivery.take_store.store_mobile),1)])),_:1})])),_:1}),u(U,{class:"text-[26rpx] text-gray-subtitle mt-[10rpx]"},{default:d((()=>[f(_(t.value.delivery.take_store.full_address),1)])),_:1}),u(U,{class:"text-[26rpx] text-gray-subtitle mt-[16rpx]"},{default:d((()=>[f("营业时间："+_(t.value.delivery.take_store.trade_time),1)])),_:1})])),_:1}))])),_:1}),u(h,{class:"iconfont iconxiangyoujiantou text-[26rpx] text-gray-subtitle"})])),_:1})):p("v-if",!0)])),_:1})])),_:1})):p("v-if",!0),u(U,{class:"mb-[20rpx] px-[24rpx] rounded-md bg-white"},{default:d((()=>[(s(!0),w(C,null,j(t.value.goods_data,((e,l)=>(s(),o(U,{class:"flex py-[30rpx] border-0 !border-b !border-[#f5f5f5] border-solid"},{default:d((()=>[u(A,{width:"168rpx",height:"168rpx",src:g($)(e.sku_image),model:"aspectFill"},{error:d((()=>[u(I,{name:"photo",color:"#999",size:"50"})])),_:2},1032,["src"]),u(U,{class:"flex flex-1 w-0 flex-col justify-between ml-[20rpx]"},{default:d((()=>[u(U,null,{default:d((()=>[u(U,{class:"text-ellipsis text-[#333] text-[26rpx] leading-normal font-500"},{default:d((()=>[f(_(e.goods.goods_name),1)])),_:2},1024),u(U,{class:"mt-[10rpx] text-[26rpx] text-gray-subtitle"},{default:d((()=>[f(_(e.sku_name),1)])),_:2},1024)])),_:2},1024),e.not_support_delivery?(s(),o(T,{key:0,type:"error",description:"该商品不支持当前所选配送方式",fontSize:"12"})):p("v-if",!0),u(U,{class:"flex justify-between"},{default:d((()=>[u(U,{class:"text-[var(--price-text-color)] font-500 price-font"},{default:d((()=>[u(h,{class:"text-xs"},{default:d((()=>[f("￥")])),_:1}),u(h,null,{default:d((()=>[f(_(g(B)(e.price)),1)])),_:2},1024)])),_:2},1024),u(U,{class:"font-500 text-sm"},{default:d((()=>[u(h,{class:"text-[26rpx]"},{default:d((()=>[f("x")])),_:1}),f(_(e.num),1)])),_:2},1024)])),_:2},1024)])),_:2},1024)])),_:2},1024)))),256))])),_:1}),p(" 买家留言 "),u(U,{class:"mb-[20rpx] p-[24rpx] rounded-md bg-white flex"},{default:d((()=>[u(U,{class:"text-[28rpx] font-500 w-[150rpx]"},{default:d((()=>[f("买家留言")])),_:1}),u(U,{class:"flex-1"},{default:d((()=>[u(L,{type:"text",modelValue:l.value.member_remark,"onUpdate:modelValue":y[1]||(y[1]=e=>l.value.member_remark=e),class:"text-right text-[28rpx]",placeholder:"请输入留言信息给卖家"},null,8,["modelValue"])])),_:1})])),_:1}),u(U,{class:"rounded-md bg-white mb-[24rpx] overflow-hidden"},{default:d((()=>[p(" 优惠券 "),g(J).length?(s(),o(U,{key:0,class:"p-[24rpx] flex items-center",onClick:y[2]||(y[2]=e=>n.value.open())},{default:d((()=>[u(U,{class:"text-[28rpx] font-500 w-[150rpx]"},{default:d((()=>[f("优惠券")])),_:1}),u(U,{class:"flex-1 w-0 text-right"},{default:d((()=>[t.value.discount&&t.value.discount.coupon?(s(),o(h,{key:0,class:"text-red font-500 text-[28rpx]"},{default:d((()=>[f(_(t.value.discount.coupon.title),1)])),_:1})):(s(),o(h,{key:1,class:"text-[28rpx] text-gray-subtitle"},{default:d((()=>[f("请选择优惠券")])),_:1}))])),_:1}),u(h,{class:"iconfont iconxiangyoujiantou text-[28rpx] text-gray-subtitle"})])),_:1})):p("v-if",!0),p(" 发票 "),b.value&&b.value.invoiceOpen?(s(),o(U,{key:1,class:"p-[24rpx] flex items-center",onClick:y[3]||(y[3]=e=>b.value.open())},{default:d((()=>[u(U,{class:"text-[28rpx] font-500 w-[150rpx]"},{default:d((()=>[f("发票信息")])),_:1}),u(U,{class:"flex-1 w-0 text-right truncate"},{default:d((()=>[u(h,{class:"text-[28rpx] text-gray-subtitle"},{default:d((()=>[f(_(l.value.invoice.header_name||"不需要发票"),1)])),_:1})])),_:1}),u(h,{class:"iconfont iconxiangyoujiantou text-[28rpx] text-gray-subtitle"})])),_:1})):p("v-if",!0)])),_:1}),u(U,{class:"mt-0 p-[24rpx] rounded-md bg-white"},{default:d((()=>[u(U,{class:"flex font-500 py-[10rpx]"},{default:d((()=>[u(U,{class:"text-[28rpx] w-[150rpx]"},{default:d((()=>[f("商品金额")])),_:1}),u(U,{class:"flex-1 w-0 text-right text-[var(--price-text-color)] price-font"},{default:d((()=>[u(h,{class:"text-[24rpx]"},{default:d((()=>[f("￥")])),_:1}),u(h,null,{default:d((()=>[f(_(g(B)(t.value.basic.goods_money)),1)])),_:1})])),_:1})])),_:1}),t.value.basic.delivery_money?(s(),o(U,{key:0,class:"flex font-500 py-[10rpx]"},{default:d((()=>[u(U,{class:"text-[28rpx] w-[150rpx]"},{default:d((()=>[f("运费")])),_:1}),u(U,{class:"flex-1 w-0 text-right text-[var(--price-text-color)] price-font"},{default:d((()=>[u(h,{class:"text-[24rpx]"},{default:d((()=>[f("￥")])),_:1}),u(h,null,{default:d((()=>[f(_(g(B)(t.value.basic.delivery_money)),1)])),_:1})])),_:1})])),_:1})):p("v-if",!0),t.value.basic.discount_money?(s(),o(U,{key:1,class:"flex font-500 py-[10rpx]"},{default:d((()=>[u(U,{class:"text-[28rpx] w-[150rpx]"},{default:d((()=>[f("优惠金额")])),_:1}),u(U,{class:"flex-1 w-0 text-right text-[var(--price-text-color)] price-font"},{default:d((()=>[u(h,{class:"text-[24rpx]"},{default:d((()=>[f("-￥")])),_:1}),u(h,null,{default:d((()=>[f(_(g(B)(t.value.basic.discount_money)),1)])),_:1})])),_:1})])),_:1})):p("v-if",!0)])),_:1})])),_:1}),u(P,{fixed:!0,placeholder:!0,safeAreaInsetBottom:!0},{default:d((()=>[u(U,{class:"flex-1 flex items-center justify-between"},{default:d((()=>[u(U,{class:"whitespace-nowrap px-[30rpx] text-color font-600 leading-[45rpx]"},{default:d((()=>[u(h,{class:"text-[#333333] text-[26rpx]"},{default:d((()=>[f("合计：")])),_:1}),u(h,{class:"text-[24rpx] font-500 text-[var(--price-text-color)] price-font"},{default:d((()=>[f("￥")])),_:1}),u(h,{class:"text-[34rpx] mr-[10rpx] font-500 text-[var(--price-text-color)] price-font"},{default:d((()=>[f(_(g(B)(t.value.basic.order_money)),1)])),_:1})])),_:1}),u(G,{class:"!w-[204rpx] !h-[80rpx] text-[32rpx] mr-[30rpx] leading-[80rpx] rounded-full text-white bg-[var(--primary-color)] remove-border",loading:k.value,onClick:N},{default:d((()=>[f("提交订单")])),_:1},8,["loading"])])),_:1})])),_:1}),p(" 选择优惠券 "),u(g(te),{"order-key":l.value.order_key,ref_key:"couponRef",ref:n,onConfirm:M},null,8,["order-key"]),p(" 选择自提点 "),u(g(ae),{ref_key:"storeRef",ref:c,onConfirm:X},null,512),p(" 发票 "),u(g(re),{ref_key:"invoiceRef",ref:b,onConfirm:Y},null,512),u(Z,{ref_key:"payRef",ref:v,onClose:W},null,512)])),_:1})):p("v-if",!0)}}}),[["__scopeId","data-v-8f07edbf"]]);export{se as default};
