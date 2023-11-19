import{A as e,B as t,C as a,i as s,j as n,w as i,m as c,x as o,r,K as u,c as l,Y as h,Z as d,d as m,a as p,q as g,t as f,P as v,k as x,G as y,H as _,n as T,D as C,R as S,_ as b,W as k}from"./index-c8487b3e.js";import{_ as w}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as N}from"./u-input.2fdff0d8.js";import{_ as E}from"./u-modal.98f8c892.js";const G=w({name:"u-code",mixins:[t,a,{props:{seconds:{type:[String,Number],default:e.code.seconds},startText:{type:String,default:e.code.startText},changeText:{type:String,default:e.code.changeText},endText:{type:String,default:e.code.endText},keepRunning:{type:Boolean,default:e.code.keepRunning},uniqueKey:{type:String,default:e.code.uniqueKey}}}],data(){return{secNum:this.seconds,timer:null,canGetCode:!0}},mounted(){this.checkKeepRunning()},watch:{seconds:{immediate:!0,handler(e){this.secNum=e}}},methods:{checkKeepRunning(){let e=Number(uni.getStorageSync(this.uniqueKey+"_$uCountDownTimestamp"));if(!e)return this.changeEvent(this.startText);let t=Math.floor(+new Date/1e3);this.keepRunning&&e&&e>t?(this.secNum=e-t,uni.removeStorageSync(this.uniqueKey+"_$uCountDownTimestamp"),this.start()):this.changeEvent(this.startText)},start(){this.timer&&(clearInterval(this.timer),this.timer=null),this.$emit("start"),this.canGetCode=!1,this.changeEvent(this.changeText.replace(/x|X/,this.secNum)),this.setTimeToStorage(),this.timer=setInterval((()=>{--this.secNum?this.changeEvent(this.changeText.replace(/x|X/,this.secNum)):(clearInterval(this.timer),this.timer=null,this.changeEvent(this.endText),this.secNum=this.seconds,this.$emit("end"),this.canGetCode=!0)}),1e3)},reset(){this.canGetCode=!0,clearInterval(this.timer),this.secNum=this.seconds,this.changeEvent(this.endText)},changeEvent(e){this.$emit("change",e)},setTimeToStorage(){if(this.keepRunning&&this.timer&&this.secNum>0&&this.secNum<=this.seconds){let e=Math.floor(+new Date/1e3);uni.setStorage({key:this.uniqueKey+"_$uCountDownTimestamp",data:e+Number(this.secNum)})}}},beforeDestroy(){this.setTimeToStorage(),clearTimeout(this.timer),this.timer=null}},[["render",function(e,t,a,r,u,l){const h=o;return s(),n(h,{class:"u-code"},{default:i((()=>[c(" 此组件功能由js完成，无需写html逻辑 ")])),_:1})}],["__scopeId","data-v-198ccd63"]]);function R(e){const t=r("");return{image:t,refresh:async()=>{try{const a=await d();e.captcha_key=a.data.captcha_key,e.captcha_code="",t.value=a.data.img.replace(/\r\n/g,"")}catch(a){}}}}const $=m({__name:"sms-code",props:{mobile:String,type:String,modelValue:{type:String,default:""}},emits:["update:modelValue"],setup(e,{emit:t}){const a=e,n=l({get:()=>a.modelValue,set(e){t("update:modelValue",e)}}),c=r(null),d=function(e){const t=r(u("getSmsCode")),a="X"+u("smsCodeChangeText"),s=l((()=>!e.value||e.value.canGetCode));return{tips:t,seconds:90,canGetCode:s,send:async t=>{if(!s.value)return;e.value.start();let a=!1;return await h(t).then((t=>{1==t.code?a=t.data.key:(e.value.reset(),a=!1)})).catch((t=>{a=!1,e.value.reset()})),a},codeChange:e=>{t.value=e},changeText:a}}(c),m=r(!1),w=p({mobile:"",captcha_code:"",captcha_key:"",type:a.type}),$=R(w),K=async()=>{if(c.value.canGetCode){if(w.mobile=a.mobile,uni.$u.test.isEmpty(w.mobile))return void b({title:u("mobilePlaceholder"),icon:"none"});if(!uni.$u.test.mobile(w.mobile))return void b({title:u("mobileError"),icon:"none"});await $.refresh(),m.value=!0}},D=async()=>{if(uni.$u.test.isEmpty(w.captcha_code))return void b({title:u("captchaPlaceholder"),icon:"none"});const e=await d.send(w);e?(n.value=e,m.value=!1):!1===e&&$.refresh()};return(e,t)=>{const a=o,n=g(f("u-code"),G),r=g(f("u-input"),N),l=k,h=g(f("u-modal"),E);return s(),v(S,null,[x(a,{class:C({"text-primary":T(d).canGetCode.value,"text-gray-300":!T(d).canGetCode.value}),onClick:K},{default:i((()=>[y(_(T(d).tips.value),1)])),_:1},8,["class"]),x(n,{seconds:T(d).seconds,"change-text":T(d).changeText,ref_key:"smsRef",ref:c,onChange:T(d).codeChange},null,8,["seconds","change-text","onChange"]),x(h,{show:m.value,title:T(u)("captchaTitle"),"confirm-text":T(u)("confirm"),"cancel-text":T(u)("cancel"),"show-cancel-button":!0,onCancel:t[2]||(t[2]=e=>m.value=!1),onConfirm:D},{default:i((()=>[x(a,{class:"flex mt-[20rpx]"},{default:i((()=>[x(r,{placeholder:T(u)("captchaPlaceholder"),border:"surround",modelValue:w.captcha_code,"onUpdate:modelValue":t[0]||(t[0]=e=>w.captcha_code=e)},null,8,["placeholder","modelValue"]),x(l,{src:T($).image.value,class:"h-[76rpx] ml-[20rpx]",mode:"heightFix",onClick:t[1]||(t[1]=e=>T($).refresh())},null,8,["src"])])),_:1})])),_:1},8,["show","title","confirm-text","cancel-text"])],64)}}});export{$ as _,R as u};