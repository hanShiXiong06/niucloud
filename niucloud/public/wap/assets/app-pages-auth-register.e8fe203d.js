import{d as e,a as l,J as a,O as r,r as o,c as s,i as t,j as u,w as d,k as i,G as n,H as p,n as m,P as c,Q as b,R as g,m as _,l as f,v as h,U as x,V as y,N as v,x as w,q as V,t as k,W as j,I as P,K as q,D as U}from"./index-98826dc8.js";import{u as C,_ as T,a as A}from"./u-form.4859819c.js";import{u as R,_ as S}from"./sms-code.vue_vue_type_script_setup_true_lang.0d3cf828.js";import{_ as E}from"./app-link.vue_vue_type_script_setup_true_lang.4e7556e3.js";import{_ as B}from"./u-button.3ca081fb.js";import"./u-icon.f3535e52.js";import"./_plugin-vue_export-helper.1b428a4d.js";import"./u-line.04b866e6.js";import"./u-modal.ea30a17a.js";import"./u-loading-icon.9963a5a3.js";import"./u-popup.5ab022a0.js";import"./u-transition.b9a2700d.js";import"./u-safe-bottom.35e4ae97.js";const D=e({__name:"register",setup(e){const D=l({username:"",password:"",confirm_password:"",mobile:"",mobile_code:"",mobile_key:"",captcha_key:"",captcha_code:""});uni.getStorageSync("openid")&&Object.assign(D,{openid:uni.getStorageSync("openid")});const F=R(D);F.refresh();const L=a(),O=r(),G=o(!1),H=o(""),I=s((()=>{const e=[];return O.login.is_username&&e.push({type:"username",title:q("usernameRegister")}),O.login.is_mobile&&!O.login.is_bind_mobile&&e.push({type:"mobile",title:q("mobileRegister")}),H.value=e[0]?e[0].type:"",e})),J=s((()=>({username:{type:"string",required:"username"==H.value,message:q("usernamePlaceholder"),trigger:["blur","change"]},password:{type:"string",required:"username"==H.value,message:q("passwordPlaceholder"),trigger:["blur","change"]},confirm_password:[{type:"string",required:"username"==H.value,message:q("confirmPasswordPlaceholder"),trigger:["blur","change"]},{validator:(e,l)=>l==D.password,message:q("confirmPasswordError"),trigger:["change","blur"]}],mobile:[{type:"string",required:"mobile"==H.value||O.login.is_bind_mobile,message:q("mobilePlaceholder"),trigger:["blur","change"]},{validator:(e,l)=>"mobile"!=H.value&&!O.login.is_bind_mobile||uni.$u.test.mobile(l),message:q("mobileError"),trigger:["change","blur"]}],mobile_code:{type:"string",required:"mobile"==H.value||O.login.is_bind_mobile,message:q("codePlaceholder"),trigger:["blur","change"]},captcha_code:{type:"string",required:"username"==H.value,message:q("captchaPlaceholder"),trigger:["blur","change"]}}))),K=o(null),N=()=>{K.value.validate().then((()=>{if(G.value)return;G.value=!0;("username"==H.value?x:y)(D).then((e=>{L.setToken(e.data.token),v().handleLoginBack()})).catch((()=>{G.value=!1,F.refresh()}))}))};return(e,l)=>{const a=w,r=V(k("u-input"),C),o=V(k("u-form-item"),T),s=V(k("sms-code"),S),x=j,y=P,v=V(k("app-link"),E),R=V(k("u-button"),B),L=V(k("u-form"),A);return t(),u(a,{class:"w-screen h-screen flex flex-col"},{default:d((()=>[i(a,{class:"flex-1"},{default:d((()=>[i(a,{class:"h-[100rpx]"}),i(a,{class:"px-[60rpx] pt-[100rpx] mb-[100rpx]"},{default:d((()=>[i(a,{class:"font-bold text-xl"},{default:d((()=>[n(p(m(q)("register")),1)])),_:1})])),_:1}),m(I).length>1?(t(),u(a,{key:0,class:"px-[60rpx] text-sm flex mb-[50rpx] font-bold leading-none"},{default:d((()=>[(t(!0),c(g,null,b(m(I),((e,l)=>(t(),c(g,null,[i(a,{class:U({"text-gray-300":e.type!=H.value}),onClick:l=>H.value=e.type},{default:d((()=>[n(p(e.title),1)])),_:2},1032,["class","onClick"]),f(i(a,{class:"mx-[30rpx] border-solid border-0 border-r-[2px] border-gray-300"},null,512),[[h,0==l]])],64)))),256))])),_:1})):_("v-if",!0),i(a,{class:"px-[60rpx]"},{default:d((()=>[i(L,{labelPosition:"left",model:D,errorType:"toast",rules:m(J),ref_key:"formRef",ref:K},{default:d((()=>[f(i(a,null,{default:d((()=>[i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"username","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.username,"onUpdate:modelValue":l[0]||(l[0]=e=>D.username=e),border:"none",clearable:"",placeholder:m(q)("usernamePlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"password","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.password,"onUpdate:modelValue":l[1]||(l[1]=e=>D.password=e),border:"none",type:"password",clearable:"",placeholder:m(q)("passwordPlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"confirm_password","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.confirm_password,"onUpdate:modelValue":l[2]||(l[2]=e=>D.confirm_password=e),border:"none",type:"password",clearable:"",placeholder:m(q)("confirmPasswordPlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1})])),_:1},512),[[h,"username"==H.value]]),f(i(a,null,{default:d((()=>[i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"mobile","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.mobile,"onUpdate:modelValue":l[3]||(l[3]=e=>D.mobile=e),border:"none",clearable:"",placeholder:m(q)("mobilePlaceholder")},null,8,["modelValue","placeholder"])])),_:1})])),_:1}),i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"code","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.mobile_code,"onUpdate:modelValue":l[5]||(l[5]=e=>D.mobile_code=e),border:"none",type:"password",clearable:"",placeholder:m(q)("codePlaceholder")},{suffix:d((()=>[i(s,{mobile:D.mobile,type:"register",modelValue:D.mobile_key,"onUpdate:modelValue":l[4]||(l[4]=e=>D.mobile_key=e)},null,8,["mobile","modelValue"])])),_:1},8,["modelValue","placeholder"])])),_:1})])),_:1})])),_:1},512),[[h,"mobile"==H.value||m(O).login.is_bind_mobile]]),f(i(a,null,{default:d((()=>[i(a,{class:"mt-[30rpx]"},{default:d((()=>[i(o,{label:"",prop:"captcha_code","border-bottom":!0},{default:d((()=>[i(r,{modelValue:D.captcha_code,"onUpdate:modelValue":l[7]||(l[7]=e=>D.captcha_code=e),border:"none",clearable:"",placeholder:m(q)("captchaPlaceholder")},{suffix:d((()=>[i(x,{src:m(F).image.value,class:"h-[48rpx] ml-[20rpx]",mode:"heightFix",onClick:l[6]||(l[6]=e=>m(F).refresh())},null,8,["src"])])),_:1},8,["modelValue","placeholder"])])),_:1})])),_:1})])),_:1},512),[[h,"username"==H.value]]),i(a,{class:"flex text-xs justify-between mt-[20rpx] text-gray-400"},{default:d((()=>[i(v,{url:"/app/pages/auth/login"},{default:d((()=>[n(p(m(q)("haveAccount"))+"，",1),i(y,{class:"text-primary"},{default:d((()=>[n(p(m(q)("toLogin")),1)])),_:1})])),_:1})])),_:1}),i(a,{class:"mt-[80rpx]"},{default:d((()=>[i(R,{type:"primary",loading:G.value,loadingText:m(q)("registering"),onClick:N},{default:d((()=>[n(p(m(q)("register")),1)])),_:1},8,["loading","loadingText"])])),_:1})])),_:1},8,["model","rules"])])),_:1})])),_:1}),m(O).login.agreement_show?(t(),u(a,{key:0,class:"text-xs py-[50rpx] flex justify-center w-full"},{default:d((()=>[n(p(m(q)("registerAgreeTips"))+" ",1),i(v,{url:"/app/pages/auth/agreement?key=service"},{default:d((()=>[i(y,{class:"text-primary"},{default:d((()=>[n(p(m(q)("userAgreement")),1)])),_:1})])),_:1}),n(" "+p(m(q)("and"))+" ",1),i(v,{url:"/app/pages/auth/agreement?key=privacy"},{default:d((()=>[i(y,{class:"text-primary"},{default:d((()=>[n(p(m(q)("privacyAgreement")),1)])),_:1})])),_:1})])),_:1})):_("v-if",!0)])),_:1})}}});export{D as default};
