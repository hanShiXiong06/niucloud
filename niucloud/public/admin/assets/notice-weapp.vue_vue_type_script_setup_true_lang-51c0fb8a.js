import{d as C,r as v,R as N,c as S,e as c,v as y,x as t,g as f,y as s,A as _,B as m,u as l,Q as U,f as D,F as j,z as L}from"./base-06478700.js";/* empty css                   */import{E as O}from"./el-overlay-42a687c6.js";/* empty css                  */import{a as T,E as $}from"./el-form-item-314d006d.js";/* empty css                 */import{t as n}from"./index-81ed253c.js";import{e as z}from"./notice-e3403280.js";import{E as A,b as G}from"./index-6290cf08.js";import{E as I}from"./index-c2f001d3.js";import{v as K}from"./directive-cb2d3366.js";const Q={class:"input-width"},q={class:"input-width"},H={class:"input-width"},J={class:"dialog-footer"},re=C({__name:"notice-weapp",emits:["complete"],setup(M,{expose:V,emit:h}){const u=v(!1),i=v(!0),g={is_weapp:0,key:"",name:"",title:"",type:"",content:[],first:"",remark:"",temp_key:""},a=N({...g}),b=v(),x=S(()=>({})),F=async o=>{i.value||!o||await o.validate(async e=>{if(e){i.value=!0;const p=a;p.status=p.is_weapp,z(p).then(w=>{i.value=!1,u.value=!1,h("complete")}).catch(()=>{i.value=!1})}})};return V({showDialog:u,setFormData:async(o=null)=>{i.value=!0,Object.assign(a,g),o&&Object.keys(a).forEach(e=>{o[e]!=null&&(a[e]=o[e]),o.weapp&&o.weapp[e]!=null&&(a[e]=o.weapp[e])}),i.value=!1}}),(o,e)=>{const p=A,w=G,d=T,k=$,E=I,B=O,R=K;return c(),y(B,{modelValue:u.value,"onUpdate:modelValue":e[3]||(e[3]=r=>u.value=r),title:l(n)("noticeSetting"),width:"550px","destroy-on-close":!0},{footer:t(()=>[f("span",J,[s(E,{onClick:e[1]||(e[1]=r=>u.value=!1)},{default:t(()=>[_(m(l(n)("cancel")),1)]),_:1}),s(E,{type:"primary",loading:i.value,onClick:e[2]||(e[2]=r=>F(b.value))},{default:t(()=>[_(m(l(n)("confirm")),1)]),_:1},8,["loading"])])]),default:t(()=>[U((c(),y(k,{model:a,"label-width":"110px",ref_key:"formRef",ref:b,rules:l(x),class:"page-form"},{default:t(()=>[s(d,{label:l(n)("status")},{default:t(()=>[s(w,{modelValue:a.is_weapp,"onUpdate:modelValue":e[0]||(e[0]=r=>a.is_weapp=r)},{default:t(()=>[s(p,{label:1},{default:t(()=>[_(m(l(n)("startUsing")),1)]),_:1}),s(p,{label:0},{default:t(()=>[_(m(l(n)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),s(d,{label:l(n)("name")},{default:t(()=>[f("div",Q,m(a.name),1)]),_:1},8,["label"]),s(d,{label:l(n)("weappTempKey")},{default:t(()=>[f("div",q,m(a.temp_key),1)]),_:1},8,["label"]),s(d,{label:l(n)("content")},{default:t(()=>[f("div",H,[(c(!0),D(j,null,L(a.content,(r,W)=>(c(),D("div",null,m(r[0])+"："+m(r[1]),1))),256))])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[R,i.value]])]),_:1},8,["modelValue","title"])}}});export{re as _};