import"./base-962c0c23.js";/* empty css                   */import{E as I}from"./el-overlay-60700377.js";/* empty css                  *//* empty css                     */import{E as L,b as O}from"./el-radio-bfd4b1ad.js";/* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-7525671c.js";import"./el-tooltip-58212670.js";import{_ as S}from"./index-f4731ae1.js";import{t as a}from"./index-105fbad0.js";import{e as j,a as T,g as $}from"./site-fd0c6646.js";import{q as G}from"./sys-4abedf95.js";import{E as M}from"./index-93f2c618.js";import{a as z,E as A}from"./index-61c777fa.js";import{a as H,E as J}from"./index-b933df38.js";import{E as K}from"./index-bba9e58c.js";import{v as Q}from"./directive-c0c3e9a3.js";import{d as W,r as c,M as X,c as Y,b as p,m as _,p as s,f as Z,q as t,v as f,x as g,u as r,L as ee,e as le,F as ae,t as oe,C as re}from"./runtime-core.esm-bundler-dc7a07d7.js";const te={class:"dialog-footer"},De=W({__name:"edit-user",emits:["complete"],setup(se,{expose:U,emit:R}){const m=c(!1),n=c(!1);let w="";const v={uid:0,username:"",head_img:"",real_name:"",password:"",confirm_password:"",status:1,role_ids:[],userrole:{}},l=X({...v}),V=c(),y=Y(()=>({username:[{required:l.uid==0,message:a("accountNumberPlaceholder"),trigger:"blur"}],real_name:[{required:!0,message:a("userRealNamePlaceholder"),trigger:"blur"}],role_ids:[{required:!0,message:a("userRolePlaceholder"),trigger:"blur"}],password:[{required:l.uid==0,message:a("passwordPlaceholder"),trigger:"blur"}],confirm_password:[{required:l.uid==0,message:a("confirmPasswordPlaceholder"),trigger:"blur"},{validator:(u,e,d)=>{e!=l.password?d(new Error(a("confirmPasswordError"))):d()},trigger:"blur"}]})),b=c([]);G().then(u=>{b.value=u.data,b.value.forEach(e=>{e.role_id=e.role_id.toString()})});const N=async u=>{if(n.value||!u)return;const e=l.uid?j:T;await u.validate(async d=>{d&&(n.value=!0,e(l).then(h=>{n.value=!1,m.value=!1,R("complete")}).catch(()=>{n.value=!1}))})};return U({showDialog:m,setFormData:async(u=null)=>{if(n.value=!0,Object.assign(l,v),w=a("addUser"),u){w=a("updateUser");const e=await(await $(u.uid)).data;e.role_ids=e.userrole.role_ids||[],Object.keys(l).forEach(d=>{e[d]!=null&&(l[d]=e[d])})}n.value=!1}}),(u,e)=>{const d=M,i=z,h=S,x=H,k=J,E=L,D=O,q=A,P=K,F=I,C=Q;return p(),_(F,{modelValue:m.value,"onUpdate:modelValue":e[9]||(e[9]=o=>m.value=o),title:r(w),width:"500px","destroy-on-close":!0},{footer:s(()=>[Z("span",te,[t(P,{onClick:e[7]||(e[7]=o=>m.value=!1)},{default:s(()=>[f(g(r(a)("cancel")),1)]),_:1}),t(P,{type:"primary",loading:n.value,onClick:e[8]||(e[8]=o=>N(V.value))},{default:s(()=>[f(g(r(a)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[ee((p(),_(q,{model:l,"label-width":"90px",ref_key:"formRef",ref:V,rules:r(y),class:"page-form"},{default:s(()=>[t(i,{label:r(a)("accountNumber"),prop:"username"},{default:s(()=>[t(d,{modelValue:l.username,"onUpdate:modelValue":e[0]||(e[0]=o=>l.username=o),placeholder:r(a)("accountNumberPlaceholder"),clearable:"",disabled:l.uid,class:"input-width",maxlength:"10","show-word-limit":""},null,8,["modelValue","placeholder","disabled"])]),_:1},8,["label"]),t(i,{label:r(a)("headImg")},{default:s(()=>[t(h,{modelValue:l.head_img,"onUpdate:modelValue":e[1]||(e[1]=o=>l.head_img=o)},null,8,["modelValue"])]),_:1},8,["label"]),t(i,{label:r(a)("userRealName"),prop:"real_name"},{default:s(()=>[t(d,{modelValue:l.real_name,"onUpdate:modelValue":e[2]||(e[2]=o=>l.real_name=o),placeholder:r(a)("userRealNamePlaceholder"),clearable:"",class:"input-width",maxlength:"10","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l.userrole.is_admin?re("",!0):(p(),_(i,{key:0,label:r(a)("userRoleName"),prop:"role_ids"},{default:s(()=>[t(k,{modelValue:l.role_ids,"onUpdate:modelValue":e[3]||(e[3]=o=>l.role_ids=o),placeholder:r(a)("userRolePlaceholder"),class:"input-width",multiple:"","collapse-tags":"","collapse-tags-tooltip":""},{default:s(()=>[(p(!0),le(ae,null,oe(b.value,(o,B)=>(p(),_(x,{label:o.role_name,value:o.role_id,key:B},null,8,["label","value"]))),128))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"])),t(i,{label:r(a)("password"),prop:"password"},{default:s(()=>[t(d,{modelValue:l.password,"onUpdate:modelValue":e[4]||(e[4]=o=>l.password=o),placeholder:r(a)("passwordPlaceholder"),type:"password","show-password":!0,clearable:"",class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(i,{label:r(a)("confirmPassword"),prop:"confirm_password"},{default:s(()=>[t(d,{modelValue:l.confirm_password,"onUpdate:modelValue":e[5]||(e[5]=o=>l.confirm_password=o),placeholder:r(a)("confirmPasswordPlaceholder"),type:"password","show-password":!0,clearable:"",class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(i,{label:r(a)("status")},{default:s(()=>[t(D,{modelValue:l.status,"onUpdate:modelValue":e[6]||(e[6]=o=>l.status=o)},{default:s(()=>[t(E,{label:1},{default:s(()=>[f(g(r(a)("statusUnlock")),1)]),_:1}),t(E,{label:0},{default:s(()=>[f(g(r(a)("lock")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[C,n.value]])]),_:1},8,["modelValue","title"])}}});export{De as _};