/* empty css             *//* empty css                   */import{E as F}from"./el-overlay-380df695.js";/* empty css                  */import{a as k,E as B}from"./el-form-item-144bc604.js";/* empty css                 */import{t as a}from"./index-6b4cbbe4.js";import{c as C,f as M,h as R}from"./delivery-44173086.js";import{E as S}from"./index-5f2625ed.js";import{E as q}from"./index-6f670765.js";import{v as I}from"./directive-d226d53f.js";import{d as U,r as v,M as j,c as L,b as g,m as b,p as d,f as O,q as m,i as h,v as y,x as D,u as o,L as $}from"./runtime-core.esm-bundler-c17ab5bc.js";const T={class:"dialog-footer"},oe=U({__name:"delivery-personnel-edit",emits:["complete"],setup(z,{expose:V,emit:w}){let t=v(!1);const r=v(!1),c={deliver_id:"",deliver_name:"",deliver_mobile:""},l=j({...c}),f=v(),E=L(()=>({deliver_name:[{required:!0,message:a("deliverNamePlaceholder"),trigger:"blur"}],deliver_mobile:[{required:!0,message:a("deliverMobilePlaceholder"),trigger:"blur"},{min:11,max:11,message:"请输入11位手机号码",trigger:"blur"},{pattern:/^(13[0-9]|14[579]|15[0-3,5-9]|16[6]|17[0135678]|18[0-9]|19[89])\d{8}$/,message:"请输入正确的手机号码",trigger:"blur"}]})),x=async n=>{if(r.value||!n)return;let e=l.deliver_id?C:M;await n.validate(async i=>{i&&(r.value=!0,e(l).then(p=>{r.value=!1,t.value=!1,w("complete")}).catch(p=>{r.value=!1}))})};return V({showDialog:t,setFormData:async(n=null)=>{if(Object.assign(l,c),r.value=!0,n){const e=await(await R(n.deliver_id)).data;e&&Object.keys(l).forEach(i=>{e[i]!=null&&(l[i]=e[i])})}r.value=!1}}),(n,e)=>{const i=S,u=k,p=B,_=q,N=F,P=I;return g(),b(N,{modelValue:o(t),"onUpdate:modelValue":e[4]||(e[4]=s=>h(t)?t.value=s:t=s),title:l.deliver_id?o(a)("updateDeliver"):o(a)("addDeliveryPersonnel"),width:"480",class:"diy-dialog-wrap","destroy-on-close":!0},{footer:d(()=>[O("span",T,[m(_,{onClick:e[2]||(e[2]=s=>h(t)?t.value=!1:t=!1)},{default:d(()=>[y(D(o(a)("cancel")),1)]),_:1}),m(_,{type:"primary",loading:r.value,onClick:e[3]||(e[3]=s=>x(f.value))},{default:d(()=>[y(D(o(a)("confirm")),1)]),_:1},8,["loading"])])]),default:d(()=>[$((g(),b(p,{model:l,"label-width":"120px",ref_key:"formRef",ref:f,rules:o(E),class:"page-form"},{default:d(()=>[m(u,{label:o(a)("deliverName"),prop:"deliver_name"},{default:d(()=>[m(i,{modelValue:l.deliver_name,"onUpdate:modelValue":e[0]||(e[0]=s=>l.deliver_name=s),clearable:"",placeholder:o(a)("deliverNamePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),m(u,{label:o(a)("deliverMobile"),prop:"deliver_mobile"},{default:d(()=>[m(i,{modelValue:l.deliver_mobile,"onUpdate:modelValue":e[1]||(e[1]=s=>l.deliver_mobile=s),clearable:"",placeholder:o(a)("deliverMobilePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[P,r.value]])]),_:1},8,["modelValue","title"])}}});export{oe as _};