/* empty css             *//* empty css                   */import{E as C}from"./el-overlay-380df695.js";/* empty css                  */import{a as N,E as L}from"./el-form-item-144bc604.js";/* empty css                       *//* empty css                 */import{t as s}from"./index-6b4cbbe4.js";import{e as S}from"./notice-c8e67bc8.js";import{E as U,b as j}from"./index-6ff31475.js";import{E as O}from"./index-6f670765.js";import{v as T}from"./directive-d226d53f.js";import{d as $,r as v,M as q,c as G,b as c,m as y,p as t,f,q as n,v as _,x as m,u as l,L as I,e as D,F as K,t as M}from"./runtime-core.esm-bundler-c17ab5bc.js";const z={class:"input-width"},A={class:"input-width"},H={class:"input-width"},J={class:"dialog-footer"},pe=$({__name:"notice-weapp",emits:["complete"],setup(P,{expose:V,emit:h}){const u=v(!1),i=v(!0),b={is_weapp:0,key:"",name:"",title:"",type:"",content:[],first:"",remark:"",temp_key:""},a=q({...b}),g=v(),x=G(()=>({})),F=async o=>{i.value||!o||await o.validate(async e=>{if(e){i.value=!0;const p=a;p.status=p.is_weapp,S(p).then(w=>{i.value=!1,u.value=!1,h("complete")}).catch(()=>{i.value=!1})}})};return V({showDialog:u,setFormData:async(o=null)=>{i.value=!0,Object.assign(a,b),o&&Object.keys(a).forEach(e=>{o[e]!=null&&(a[e]=o[e]),o.weapp&&o.weapp[e]!=null&&(a[e]=o.weapp[e])}),i.value=!1}}),(o,e)=>{const p=U,w=j,d=N,k=L,E=O,B=C,R=T;return c(),y(B,{modelValue:u.value,"onUpdate:modelValue":e[3]||(e[3]=r=>u.value=r),title:l(s)("noticeSetting"),width:"550px","destroy-on-close":!0},{footer:t(()=>[f("span",J,[n(E,{onClick:e[1]||(e[1]=r=>u.value=!1)},{default:t(()=>[_(m(l(s)("cancel")),1)]),_:1}),n(E,{type:"primary",loading:i.value,onClick:e[2]||(e[2]=r=>F(g.value))},{default:t(()=>[_(m(l(s)("confirm")),1)]),_:1},8,["loading"])])]),default:t(()=>[I((c(),y(k,{model:a,"label-width":"110px",ref_key:"formRef",ref:g,rules:l(x),class:"page-form"},{default:t(()=>[n(d,{label:l(s)("status")},{default:t(()=>[n(w,{modelValue:a.is_weapp,"onUpdate:modelValue":e[0]||(e[0]=r=>a.is_weapp=r)},{default:t(()=>[n(p,{label:1},{default:t(()=>[_(m(l(s)("startUsing")),1)]),_:1}),n(p,{label:0},{default:t(()=>[_(m(l(s)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),n(d,{label:l(s)("name")},{default:t(()=>[f("div",z,m(a.name),1)]),_:1},8,["label"]),n(d,{label:l(s)("weappTempKey")},{default:t(()=>[f("div",A,m(a.temp_key),1)]),_:1},8,["label"]),n(d,{label:l(s)("content")},{default:t(()=>[f("div",H,[(c(!0),D(K,null,M(a.content,(r,W)=>(c(),D("div",null,m(r[0])+"："+m(r[1]),1))),256))])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[R,i.value]])]),_:1},8,["modelValue","title"])}}});export{pe as _};
