import{y as e,z as t,A as a,h as s,i as n,t as i,r as c,J as o,c as r,a3 as u,a4 as l,d as h,a as d,p as m,q as p,M as g,j as f,w as v,D as x,E as y,l as T,F as _,O as C,a5 as S,a1 as b}from"./index-15eacd31.js";import{_ as k}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as w}from"./u-input.4212a359.js";import{_ as N}from"./u-modal.755a4b61.js";const E=k({name:"u-code",mixins:[t,a,{props:{seconds:{type:[String,Number],default:e.code.seconds},startText:{type:String,default:e.code.startText},changeText:{type:String,default:e.code.changeText},endText:{type:String,default:e.code.endText},keepRunning:{type:Boolean,default:e.code.keepRunning},uniqueKey:{type:String,default:e.code.uniqueKey}}}],data(){return{secNum:this.seconds,timer:null,canGetCode:!0}},mounted(){this.checkKeepRunning()},watch:{seconds:{immediate:!0,handler(e){this.secNum=e}}},methods:{checkKeepRunning(){let e=Number(uni.getStorageSync(this.uniqueKey+"_$uCountDownTimestamp"));if(!e)return this.changeEvent(this.startText);let t=Math.floor(+new Date/1e3);this.keepRunning&&e&&e>t?(this.secNum=e-t,uni.removeStorageSync(this.uniqueKey+"_$uCountDownTimestamp"),this.start()):this.changeEvent(this.startText)},start(){this.timer&&(clearInterval(this.timer),this.timer=null),this.$emit("start"),this.canGetCode=!1,this.changeEvent(this.changeText.replace(/x|X/,this.secNum)),this.setTimeToStorage(),this.timer=setInterval((()=>{--this.secNum?this.changeEvent(this.changeText.replace(/x|X/,this.secNum)):(clearInterval(this.timer),this.timer=null,this.changeEvent(this.endText),this.secNum=this.seconds,this.$emit("end"),this.canGetCode=!0)}),1e3)},reset(){this.canGetCode=!0,clearInterval(this.timer),this.secNum=this.seconds,this.changeEvent(this.endText)},changeEvent(e){this.$emit("change",e)},setTimeToStorage(){if(this.keepRunning&&this.timer&&this.secNum>0&&this.secNum<=this.seconds){let e=Math.floor(+new Date/1e3);uni.setStorage({key:this.uniqueKey+"_$uCountDownTimestamp",data:e+Number(this.secNum)})}}},beforeDestroy(){this.setTimeToStorage(),clearTimeout(this.timer),this.timer=null}},[["render",function(e,t,a,c,o,r){const u=i;return s(),n(u,{class:"u-code"})}],["__scopeId","data-v-198ccd63"]]);function G(e){const t=c("");return{image:t,refresh:async()=>{try{const a=await l();e.captcha_key=a.data.captcha_key,e.captcha_code="",t.value=a.data.img.replace(/\r\n/g,"")}catch(a){}}}}const $=h({__name:"sms-code",props:{mobile:String,type:String,modelValue:{type:String,default:""}},emits:["update:modelValue"],setup(e,{emit:t}){const a=e,n=r({get:()=>a.modelValue,set(e){t("update:modelValue",e)}}),l=c(null),h=function(e){const t=c(o("getSmsCode")),a="X"+o("smsCodeChangeText"),s=r((()=>!e.value||e.value.canGetCode));return{tips:t,seconds:90,canGetCode:s,send:async t=>{if(!s.value)return;e.value.start();let a=!1;return await u(t).then((t=>{1==t.code?a=t.data.key:(e.value.reset(),a=!1)})).catch((t=>{a=!1,e.value.reset()})),a},codeChange:e=>{t.value=e},changeText:a}}(l),k=c(!1),$=d({mobile:"",captcha_code:"",captcha_key:"",type:a.type}),D=G($),K=async()=>{if(l.value.canGetCode){if($.mobile=a.mobile,uni.$u.test.isEmpty($.mobile))return void S({title:o("mobilePlaceholder"),icon:"none"});if(!uni.$u.test.mobile($.mobile))return void S({title:o("mobileError"),icon:"none"});await D.refresh(),k.value=!0}},R=async()=>{if(uni.$u.test.isEmpty($.captcha_code))return void S({title:o("captchaPlaceholder"),icon:"none"});const e=await h.send($);e?(n.value=e,k.value=!1):!1===e&&D.refresh()};return(e,t)=>{const a=i,n=m(p("u-code"),E),c=m(p("u-input"),w),r=b,u=m(p("u-modal"),N);return s(),g(C,null,[f(a,{class:_({"text-primary":T(h).canGetCode.value,"text-gray-300":!T(h).canGetCode.value}),onClick:K},{default:v((()=>[x(y(T(h).tips.value),1)])),_:1},8,["class"]),f(n,{seconds:T(h).seconds,"change-text":T(h).changeText,ref_key:"smsRef",ref:l,onChange:T(h).codeChange},null,8,["seconds","change-text","onChange"]),f(u,{show:k.value,title:T(o)("captchaTitle"),"confirm-text":T(o)("confirm"),"cancel-text":T(o)("cancel"),"show-cancel-button":!0,onCancel:t[2]||(t[2]=e=>k.value=!1),onConfirm:R},{default:v((()=>[f(a,{class:"flex mt-[20rpx]"},{default:v((()=>[f(c,{placeholder:T(o)("captchaPlaceholder"),border:"surround",modelValue:$.captcha_code,"onUpdate:modelValue":t[0]||(t[0]=e=>$.captcha_code=e)},null,8,["placeholder","modelValue"]),f(r,{src:T(D).image.value,class:"h-[76rpx] ml-[20rpx]",mode:"heightFix",onClick:t[1]||(t[1]=e=>T(D).refresh())},null,8,["src"])])),_:1})])),_:1},8,["show","title","confirm-text","cancel-text"])],64)}}});export{$ as _,G as u};
