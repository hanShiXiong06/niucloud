/* empty css             *//* empty css                   *//* empty css                  */import"./el-overlay-f7f710bd.js";/* empty css                  *//* empty css                     */import{E as I,b as U}from"./el-radio-c9a1047c.js";/* empty css                        *//* empty css                 */import{t as o}from"./index-5f4ce139.js";import{u as P,a as S,b as j}from"./article-a302dda6.js";import{E as q}from"./index-95693143.js";import{a as L,E as M}from"./index-624573cc.js";import{E as O}from"./index-24c7fcee.js";import{E as T}from"./index-4862d1b3.js";import{E as k}from"./index-4683bff4.js";import{v as G}from"./directive-a07a10ed.js";import{d as $,r as g,M as z,c as H,b as y,m as V,p as r,f as J,q as n,i as h,v as p,x as f,u as l,L as K}from"./runtime-core.esm-bundler-7c3fd514.js";const Q={class:"dialog-footer"},ge=$({__name:"edit-category",emits:["complete"],setup(W,{expose:C,emit:x}){let c="",s=g(!1);const m=g(!0),v={category_id:"",name:"",sort:"",is_show:1},a=z({...v}),b=g(),D=H(()=>({name:[{required:!0,message:o("namePlaceholder"),trigger:"blur"},{validator:(d,e,t)=>{e.length>20&&t(new Error(o("nameMax"))),t()},trigger:"blur"}],sort:[{validator:(d,e,t)=>{(e===""||isNaN(e))&&t(new Error(o("sortNumber"))),parseInt(e)>1e4&&t(new Error(o("sortBetween"))),t()},trigger:"blur"}]})),N=async d=>{if(m.value||!d)return;let e=a.category_id?P:S;await d.validate(async t=>{t&&(m.value=!0,e(a).then(_=>{m.value=!1,s.value=!1,x("complete")}).catch(_=>{m.value=!1,s.value=!1}))})};return C({showDialog:s,setFormData:async(d=null)=>{if(m.value=!0,Object.assign(a,v),c=o("addArticleCategory"),d){c=o("updateArticleCategory");const e=await(await j(d.category_id)).data;Object.keys(a).forEach(t=>{e[t]!=null&&(a[t]=e[t])})}m.value=!1}}),(d,e)=>{const t=q,u=L,_=O,w=I,R=U,A=M,E=T,B=k,F=G;return y(),V(B,{modelValue:l(s),"onUpdate:modelValue":e[5]||(e[5]=i=>h(s)?s.value=i:s=i),title:l(c),width:"500px","destroy-on-close":!0},{footer:r(()=>[J("span",Q,[n(E,{onClick:e[3]||(e[3]=i=>h(s)?s.value=!1:s=!1)},{default:r(()=>[p(f(l(o)("cancel")),1)]),_:1}),n(E,{type:"primary",loading:m.value,onClick:e[4]||(e[4]=i=>N(b.value))},{default:r(()=>[p(f(l(o)("confirm")),1)]),_:1},8,["loading"])])]),default:r(()=>[K((y(),V(A,{model:a,"label-width":"90px",ref_key:"formRef",ref:b,rules:l(D),class:"page-form"},{default:r(()=>[n(u,{label:l(o)("name"),prop:"name"},{default:r(()=>[n(t,{modelValue:a.name,"onUpdate:modelValue":e[0]||(e[0]=i=>a.name=i),clearable:"",placeholder:l(o)("namePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),n(u,{label:l(o)("sort"),prop:"sort"},{default:r(()=>[n(_,{modelValue:a.sort,"onUpdate:modelValue":e[1]||(e[1]=i=>a.sort=i),min:0},null,8,["modelValue"])]),_:1},8,["label"]),n(u,{label:l(o)("isShow")},{default:r(()=>[n(R,{modelValue:a.is_show,"onUpdate:modelValue":e[2]||(e[2]=i=>a.is_show=i),placeholder:l(o)("isShowPlaceholder")},{default:r(()=>[n(w,{label:1},{default:r(()=>[p(f(l(o)("show")),1)]),_:1}),n(w,{label:0},{default:r(()=>[p(f(l(o)("hidden")),1)]),_:1})]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[F,m.value]])]),_:1},8,["modelValue","title"])}}});export{ge as _};
