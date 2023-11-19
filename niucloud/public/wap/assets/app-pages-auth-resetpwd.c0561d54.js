import{d as e,a as l,r as o,Z as r,i as a,j as s,w as d,k as t,K as m,L as p,n as i,a6 as u,e as n,x as c,q as b,t as f}from"./index-42b5dd66.js";import{_,a as g,b as h}from"./u-form.2259a935.js";import{_ as x}from"./sms-code.vue_vue_type_script_setup_true_lang.44a4ca12.js";import{_ as w}from"./u-button.611a1b81.js";import"./u-icon.d822f266.js";import"./_plugin-vue_export-helper.1b428a4d.js";import"./u-line.9c8e4680.js";import"./u-modal.b8d44c35.js";import"./u-loading-icon.677af9c0.js";import"./u-popup.7c4d4b2a.js";import"./u-transition.58dbc541.js";import"./u-safe-bottom.6809b39f.js";const y=e({__name:"resetpwd",setup(e){const y=l({mobile:"",mobile_code:"",mobile_key:"",password:"",confirm_password:""}),V=o(!1),j=o(null),P={password:{type:"string",required:!0,message:r("passwordPlaceholder"),trigger:["blur","change"]},confirm_password:[{type:"string",required:!0,message:r("confirmPasswordPlaceholder"),trigger:["blur","change"]},{validator:(e,l)=>l==y.password,message:r("confirmPasswordError"),trigger:["change","blur"]}],mobile:[{type:"string",required:!0,message:r("mobilePlaceholder"),trigger:["blur","change"]},{validator:(e,l)=>uni.$u.test.mobile(l),message:r("mobileError"),trigger:["change","blur"]}],mobile_code:{type:"string",required:!0,message:r("codePlaceholder"),trigger:["blur","change"]}},v=()=>{j.value.validate().then((()=>{V.value||(V.value=!0,u(y).then((e=>{n({url:"/app/pages/auth/login",mode:"redirectTo"})})).catch((()=>{V.value=!1})))}))};return(e,l)=>{const o=c,u=b(f("u-input"),_),n=b(f("u-form-item"),g),k=b(f("sms-code"),x),U=b(f("u-button"),w),q=b(f("u-form"),h);return a(),s(o,{class:"w-screen h-screen flex flex-col"},{default:d((()=>[t(o,{class:"flex-1"},{default:d((()=>[t(o,{class:"h-[100rpx]"}),t(o,{class:"px-[60rpx] pt-[100rpx] mb-[100rpx]"},{default:d((()=>[t(o,{class:"font-bold text-xl"},{default:d((()=>[m(p(i(r)("findPassword")),1)])),_:1})])),_:1}),t(o,{class:"px-[60rpx]"},{default:d((()=>[t(q,{labelPosition:"left",model:y,errorType:"toast",rules:P,ref_key:"formRef",ref:j},{default:d((()=>[t(o,{class:"mt-[30rpx]"},{default:d((()=>[t(n,{label:"",prop:"mobile","border-bottom":!0},{default:d((()=>[t(u,{modelValue:y.mobile,"onUpdate:modelValue":l[0]||(l[0]=e=>y.mobile=e),border:"none",clearable:"",placeholder:i(r)("mobilePlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),t(o,{class:"mt-[30rpx]"},{default:d((()=>[t(n,{label:"",prop:"code","border-bottom":!0},{default:d((()=>[t(u,{modelValue:y.mobile_code,"onUpdate:modelValue":l[2]||(l[2]=e=>y.mobile_code=e),border:"none",type:"password",clearable:"",placeholder:i(r)("codePlaceholder")},{suffix:d((()=>[t(k,{mobile:y.mobile,type:"find_pass",modelValue:y.mobile_key,"onUpdate:modelValue":l[1]||(l[1]=e=>y.mobile_key=e)},null,8,["mobile","modelValue"])])),_:1},8,["modelValue","placeholder"])])),_:1})])),_:1}),t(o,{class:"mt-[30rpx]"},{default:d((()=>[t(n,{label:"",prop:"password","border-bottom":!0},{default:d((()=>[t(u,{modelValue:y.password,"onUpdate:modelValue":l[3]||(l[3]=e=>y.password=e),border:"none",type:"password",clearable:"",placeholder:i(r)("passwordPlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),t(o,{class:"mt-[30rpx]"},{default:d((()=>[t(n,{label:"",prop:"confirm_password","border-bottom":!0},{default:d((()=>[t(u,{modelValue:y.confirm_password,"onUpdate:modelValue":l[4]||(l[4]=e=>y.confirm_password=e),border:"none",type:"password",clearable:"",placeholder:i(r)("confirmPasswordPlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),t(o,{class:"mt-[80rpx]"},{default:d((()=>[t(U,{type:"primary",loading:V.value,loadingText:i(r)("confirm"),onClick:v},{default:d((()=>[m(p(i(r)("confirm")),1)])),_:1},8,["loading","loadingText"])])),_:1})])),_:1},8,["model"])])),_:1})])),_:1})])),_:1})}}});export{y as default};
