/* empty css             *//* empty css                   */import{E as k}from"./el-overlay-380df695.js";/* empty css                  */import{a as F,E as B}from"./el-form-item-144bc604.js";/* empty css                 */import{t as a}from"./index-3862e13d.js";import{t as C,v as P,w as U}from"./goods-fd566118.js";import{E as I}from"./index-5f2625ed.js";import{E as R}from"./index-6f670765.js";import{v as j}from"./directive-d226d53f.js";import{d as q,r as p,M as O,c as $,b as g,m as V,p as n,f as M,q as m,v as h,x as w,u as s,L as S}from"./runtime-core.esm-bundler-c17ab5bc.js";const T={class:"dialog-footer"},te=q({__name:"label-edit",emits:["complete"],setup(z,{expose:y,emit:E}){const d=p(!1),r=p(!1),c=p(""),_={label_id:"",label_name:"",memo:"",sort:""},l=O({..._}),b=p(),x=$(()=>({label_name:[{required:!0,message:a("labelNamePlaceholder"),trigger:"blur"}]})),D=async i=>{if(r.value||!i)return;const e=l.label_id?C:P;await i.validate(async t=>{t&&(r.value=!0,e(l).then(f=>{r.value=!1,d.value=!1,E("complete")}).catch(f=>{r.value=!1}))})};return y({showDialog:d,setFormData:async(i=null)=>{if(Object.assign(l,_),r.value=!0,i){const e=await(await U(i.label_id)).data;c.value=a("updateLabel"),e&&Object.keys(l).forEach(t=>{e[t]!=null&&(l[t]=e[t])})}else c.value=a("addLabel");r.value=!1}}),(i,e)=>{const t=I,u=F,f=B,v=R,L=k,N=j;return g(),V(L,{modelValue:d.value,"onUpdate:modelValue":e[5]||(e[5]=o=>d.value=o),title:c.value,width:"500px",class:"diy-dialog-wrap","destroy-on-close":!0},{footer:n(()=>[M("span",T,[m(v,{onClick:e[3]||(e[3]=o=>d.value=!1)},{default:n(()=>[h(w(s(a)("cancel")),1)]),_:1}),m(v,{type:"primary",loading:r.value,onClick:e[4]||(e[4]=o=>D(b.value))},{default:n(()=>[h(w(s(a)("confirm")),1)]),_:1},8,["loading"])])]),default:n(()=>[S((g(),V(f,{model:l,"label-width":"120px",ref_key:"formRef",ref:b,rules:s(x),class:"page-form"},{default:n(()=>[m(u,{label:s(a)("labelName"),prop:"label_name"},{default:n(()=>[m(t,{modelValue:l.label_name,"onUpdate:modelValue":e[0]||(e[0]=o=>l.label_name=o),clearable:"",placeholder:s(a)("labelNamePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),m(u,{label:s(a)("memo")},{default:n(()=>[m(t,{modelValue:l.memo,"onUpdate:modelValue":e[1]||(e[1]=o=>l.memo=o),type:"textarea",clearable:"",placeholder:s(a)("memoPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),m(u,{label:s(a)("sort")},{default:n(()=>[m(t,{modelValue:l.sort,"onUpdate:modelValue":e[2]||(e[2]=o=>l.sort=o),clearable:"",placeholder:s(a)("sortPlaceholder"),class:"input-width",onkeyup:"this.value = this.value.replace(/[^\\d\\.]/g,'');"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[N,r.value]])]),_:1},8,["modelValue","title"])}}});export{te as _};
