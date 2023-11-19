import{d as e,r as a,a as l,c as t,o,al as n,i as r,F as c,k as u,w as d,G as s,K as p,L as m,n as i,m as b,j as _,aY as f,aZ as h,e as x,a_ as y,x as k,q as g,t as v,ao as V,Z as j}from"./index-42b5dd66.js";import{_ as P,a as A,b as T}from"./u-form.2259a935.js";import{_ as N}from"./u-button.611a1b81.js";import{_ as R}from"./u-modal.b8d44c35.js";import"./u-icon.d822f266.js";import"./_plugin-vue_export-helper.1b428a4d.js";import"./u-line.9c8e4680.js";import"./u-loading-icon.677af9c0.js";import"./u-popup.7c4d4b2a.js";import"./u-transition.58dbc541.js";import"./u-safe-bottom.6809b39f.js";const C=e({__name:"account_edit",setup(e){const C=a(!1),w=a(null),U=a("get"),q=a(!1),B=l({account_id:0,account_type:"bank",bank_name:"",realname:"",account_no:""}),Z=t((()=>({realname:{type:"string",required:!0,message:"bank"==B.account_type?j("bankRealnamePlaceholder"):j("alipayRealnamePlaceholder"),trigger:["blur","change"]},bank_name:{type:"string",required:"bank"==B.account_type,message:j("bankNamePlaceholder"),trigger:["blur","change"]},account_no:{type:"string",required:!0,message:"bank"==B.account_type?j("bankAccountNoPlaceholder"):j("alipayAccountNoPlaceholder"),trigger:["blur","change"]}})));o((e=>{e.type&&(B.account_type=e.type),e.mode&&(U.value=e.mode),e.id&&(B.account_id=e.id,n({account_id:e.id}).then((e=>{e.data&&Object.keys(B).forEach((a=>{null!=e.data[a]&&(B[a]=e.data[a])}))})))}));const W=()=>{const e=B.account_id?f:h;w.value.validate().then((()=>{C.value||(C.value=!0,e(B).then((e=>{"get"==U.value?x({url:"/app/pages/member/account",param:{type:B.account_type,mode:U.value}}):x({url:"/app/pages/member/apply_cash_out",param:{account_id:e.data,type:B.account_type}})})).catch((()=>{C.value=!1})))}))},Y=()=>{y(B.account_id).then((()=>{x({url:"/app/pages/member/account",mode:"redirectTo"})}))};return(e,a)=>{const l=k,t=g(v("u-input"),P),o=g(v("u-form-item"),A),n=g(v("u-form"),T),f=g(v("u-button"),N),h=V,x=g(v("u-modal"),R);return r(),c(s,null,[u(h,{"scroll-y":"true",class:"w-screen h-screen bg-page"},{default:d((()=>[u(l,{class:"h-[30rpx]"}),u(l,{class:"p-[30rpx] bg-white mx-[32rpx] rounded"},{default:d((()=>["bank"==B.account_type?(r(),c(s,{key:0},[u(l,{class:"text-center text-base font-bold mt-[50rpx]"},{default:d((()=>[p(m(i(j)("addBankCard")),1)])),_:1}),u(l,{class:"text-center text-sm mt-[10rpx]"},{default:d((()=>[p(m(i(j)("addBankCardTips")),1)])),_:1}),u(l,{class:"mt-[50rpx]"},{default:d((()=>[u(n,{labelPosition:"left",model:B,labelWidth:"200rpx",errorType:"toast",rules:i(Z),ref_key:"formRef",ref:w},{default:d((()=>[u(l,{class:"mt-[10rpx]"},{default:d((()=>[u(o,{label:i(j)("bankRealname"),prop:"realname","border-bottom":!0},{default:d((()=>[u(t,{modelValue:B.realname,"onUpdate:modelValue":a[0]||(a[0]=e=>B.realname=e),border:"none",clearable:"",placeholder:i(j)("bankRealnamePlaceholder")},null,8,["modelValue","placeholder"])])),_:1},8,["label"])])),_:1}),u(l,{class:"mt-[10rpx]"},{default:d((()=>[u(o,{label:i(j)("bankName"),prop:"bank_name","border-bottom":!0},{default:d((()=>[u(t,{modelValue:B.bank_name,"onUpdate:modelValue":a[1]||(a[1]=e=>B.bank_name=e),border:"none",clearable:"",placeholder:i(j)("bankNamePlaceholder")},null,8,["modelValue","placeholder"])])),_:1},8,["label"])])),_:1}),u(l,{class:"mt-[10rpx]"},{default:d((()=>[u(o,{label:i(j)("bankAccountNo"),prop:"account_no","border-bottom":!0},{default:d((()=>[u(t,{modelValue:B.account_no,"onUpdate:modelValue":a[2]||(a[2]=e=>B.account_no=e),border:"none",clearable:"",placeholder:i(j)("bankAccountNoPlaceholder")},null,8,["modelValue","placeholder"])])),_:1},8,["label"])])),_:1})])),_:1},8,["model","rules"])])),_:1})],64)):b("v-if",!0),"alipay"==B.account_type?(r(),c(s,{key:1},[u(l,{class:"text-center text-base font-bold mt-[50rpx]"},{default:d((()=>[p(m(i(j)("addAlipayAccount")),1)])),_:1}),u(l,{class:"text-center text-sm mt-[10rpx]"},{default:d((()=>[p(m(i(j)("addAlipayAccountTips")),1)])),_:1}),u(l,{class:"mt-[50rpx]"},{default:d((()=>[u(n,{labelPosition:"left",model:B,labelWidth:"200rpx",errorType:"toast",rules:i(Z),ref_key:"formRef",ref:w},{default:d((()=>[u(l,{class:"mt-[10rpx]"},{default:d((()=>[u(o,{label:i(j)("alipayRealname"),prop:"realname","border-bottom":!0},{default:d((()=>[u(t,{modelValue:B.realname,"onUpdate:modelValue":a[3]||(a[3]=e=>B.realname=e),border:"none",clearable:"",placeholder:i(j)("alipayRealnamePlaceholder")},null,8,["modelValue","placeholder"])])),_:1},8,["label"])])),_:1}),u(l,{class:"mt-[10rpx]"},{default:d((()=>[u(o,{label:i(j)("alipayAccountNo"),prop:"account_no","border-bottom":!0},{default:d((()=>[u(t,{modelValue:B.account_no,"onUpdate:modelValue":a[4]||(a[4]=e=>B.account_no=e),border:"none",clearable:"",placeholder:i(j)("alipayAccountNoPlaceholder")},null,8,["modelValue","placeholder"])])),_:1},8,["label"])])),_:1})])),_:1},8,["model","rules"])])),_:1})],64)):b("v-if",!0),u(l,{class:"mt-[100rpx]"},{default:d((()=>[u(f,{text:i(j)("save"),type:"primary",shape:"circle",loading:C.value,onClick:W},null,8,["text","loading"]),B.account_id?(r(),_(l,{key:0,class:"mt-[30rpx]"},{default:d((()=>[u(f,{text:i(j)("delete"),type:"primary",shape:"circle",plain:!0,loading:C.value,onClick:a[5]||(a[5]=e=>q.value=!0)},null,8,["text","loading"])])),_:1})):b("v-if",!0)])),_:1})])),_:1})])),_:1}),u(x,{show:q.value,content:i(j)("deleteConfirm"),confirmText:i(j)("confirm"),cancelText:i(j)("cancel"),showCancelButton:!0,onConfirm:Y,onCancel:a[6]||(a[6]=e=>q.value=!1)},null,8,["show","content","confirmText","cancelText"])],64)}}});export{C as default};
