/* empty css             *//* empty css                   */import{E as Z}from"./el-overlay-380df695.js";/* empty css                  */import{a as H,E as J}from"./el-form-item-144bc604.js";/* empty css                        *//* empty css                 */import{_ as Q}from"./index-28380927.js";/* empty css               *//* empty css                  *//* empty css                  */import{A as W,B as X,C as Y,D as ee,F as le}from"./index-596de8a9.js";/* empty css                  *//* empty css                *//* empty css                    *//* empty css                       *//* empty css                       *//* empty css                 */import{v as y}from"./event-3e082a4a.js";import{t}from"./index-6b4cbbe4.js";import{g as ae}from"./tools-c759a545.js";import{E as te}from"./index-5f2625ed.js";import{E as oe,b as ne}from"./index-6ff31475.js";import{a as ue,E as re}from"./index-b7b91fcb.js";import{E as de}from"./index-58d1de41.js";import{E as me}from"./index-2f9c8f6b.js";import{E as se}from"./index-6f670765.js";import{v as pe}from"./directive-d226d53f.js";import{d as ie,r as b,M as _e,c as fe,b as f,m as v,p as n,f as S,q as a,v as p,x as i,u,L as c,C as ce,e as ye,F as be,t as ve}from"./runtime-core.esm-bundler-c17ab5bc.js";const Ve={class:"input-width"},he={class:"dialog-footer"},Xe=ie({__name:"edit-menu",props:{menuTree:{type:Array,default:()=>[]}},emits:["complete"],setup(ge,{expose:x,emit:C}){const V=b(!1),_=b(!1);let w="";const U={id:0,menu_name:"",menu_type:0,parent_key:"",icon:"",api_url:"",router_path:"",view_path:"",methods:"post",sort:"",status:1,is_show:1,menu_key:"",app_type:"",addon:"",menu_short_name:""},e=_e({...U}),k=b([]),E=b([]),P=b([]),M=b(),I=r=>/^([a-zA-Z_$])([a-zA-Z0-9_$])*$/.test(r),A=fe(()=>({menu_name:[{required:!0,message:t("menuNamePlaceholder"),trigger:"blur"}],menu_key:[{required:!0,message:t("menuKeyPlaceholder"),trigger:"blur"},{validator:(r,l,d)=>{I(l)||d(new Error(t("menuKeyValidata"))),d()},trigger:"blur"}],router_path:[{required:e.menu_type!=2,message:t("routePathPlaceholder"),trigger:"blur"}],view_path:[{required:e.menu_type==1,message:t("viewPathPlaceholder"),trigger:"blur"}],icon:[{required:e.menu_type!=2,message:t("selectIconPlaceholder"),trigger:"blur"}],api_url:[{required:e.menu_type==2,message:t("authIdPlaceholder"),trigger:"blur"}]})),q=async()=>{let{data:r}=await ae({});k.value=[{title:"系统",key:""}],k.value.push(...r)},B=async()=>{let{data:r}=await ee();E.value=[{menu_name:"顶级",menu_key:""}],E.value.push(...r)},D=async r=>{let{data:l}=await le(r);P.value=l},L=async r=>{e.parent_key="",r!=""&&(await D(r),e.parent_key=P.value[0].menu_key)},$=async r=>{if(_.value||!r)return;const l=e.id?W:X;await r.validate(async(d,m)=>{if(d){_.value=!0;const s=e;s.api_url=s.api_url?`${s.api_url}/${e.methods}`:"",l(s).then(g=>{_.value=!1,V.value=!1,C("complete")}).catch(()=>{_.value=!1})}})};return x({showDialog:V,setFormData:async(r=null)=>{if(_.value=!0,Object.assign(e,U),w=t("addMenu"),q(),B(),r.menu_key){w=t("updateMenu");const l=await(await Y(r.menu_key)).data;Object.keys(e).forEach(d=>{l[d]!=null&&(e[d]=l[d])}),e.addon!=""&&D(e.addon)}else Object.keys(e).forEach(l=>{r[l]!=null&&(e[l]=r[l])});_.value=!1}}),(r,l)=>{const d=te,m=H,s=oe,g=ne,h=ue,T=re,F=de,K=Q,O=me,R=J,N=se,j=Z,z=pe;return f(),v(j,{modelValue:V.value,"onUpdate:modelValue":l[17]||(l[17]=o=>V.value=o),title:u(w),width:"500px","destroy-on-close":!0},{footer:n(()=>[S("span",he,[a(N,{onClick:l[15]||(l[15]=o=>V.value=!1)},{default:n(()=>[p(i(u(t)("cancel")),1)]),_:1}),a(N,{type:"primary",loading:_.value,onClick:l[16]||(l[16]=o=>$(M.value))},{default:n(()=>[p(i(u(t)("confirm")),1)]),_:1},8,["loading"])])]),default:n(()=>[c((f(),v(R,{model:e,"label-width":"90px",class:"page-form",ref_key:"formRef",ref:M,rules:u(A)},{default:n(()=>[a(m,{label:u(t)("menuName"),prop:"menu_name"},{default:n(()=>[a(d,{modelValue:e.menu_name,"onUpdate:modelValue":l[0]||(l[0]=o=>e.menu_name=o),placeholder:u(t)("menuNamePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),e.id?ce("",!0):(f(),v(m,{key:0,label:u(t)("menuKey"),prop:"menu_key"},{default:n(()=>[a(d,{modelValue:e.menu_key,"onUpdate:modelValue":l[1]||(l[1]=o=>e.menu_key=o),placeholder:u(t)("menuKeyPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])),a(m,{label:u(t)("menuType")},{default:n(()=>[a(g,{modelValue:e.menu_type,"onUpdate:modelValue":l[2]||(l[2]=o=>e.menu_type=o)},{default:n(()=>[a(s,{label:0},{default:n(()=>[p(i(u(t)("menuTypeDir")),1)]),_:1}),a(s,{label:1},{default:n(()=>[p(i(u(t)("menuTypeMenu")),1)]),_:1}),a(s,{label:2},{default:n(()=>[p(i(u(t)("menuTypeButton")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),a(m,{label:u(t)("addon"),prop:"addon"},{default:n(()=>[a(T,{modelValue:e.addon,"onUpdate:modelValue":l[3]||(l[3]=o=>e.addon=o),placeholder:"Select",class:"input-width",onChange:L},{default:n(()=>[(f(!0),ye(be,null,ve(k.value,(o,G)=>(f(),v(h,{label:o.title,value:o.key,key:G},null,8,["label","value"]))),128))]),_:1},8,["modelValue"])]),_:1},8,["label"]),a(m,{label:u(t)("parentMenu"),prop:"parent_key"},{default:n(()=>[e.addon!=""?(f(),v(F,{key:0,class:"input-width",modelValue:e.parent_key,"onUpdate:modelValue":l[4]||(l[4]=o=>e.parent_key=o),props:{label:"menu_name",value:"menu_key"},data:P.value,"check-strictly":"","render-after-expand":!1},null,8,["modelValue","data"])):(f(),v(F,{key:1,class:"input-width",modelValue:e.parent_key,"onUpdate:modelValue":l[5]||(l[5]=o=>e.parent_key=o),props:{label:"menu_name",value:"menu_key"},data:E.value,"check-strictly":"","render-after-expand":!1},null,8,["modelValue","data"]))]),_:1},8,["label"]),c(a(m,{label:u(t)("routePath"),prop:"router_path"},{default:n(()=>[a(d,{modelValue:e.router_path,"onUpdate:modelValue":l[6]||(l[6]=o=>e.router_path=o),placeholder:u(t)("routePathPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),[[y,e.menu_type!=2]]),c(a(m,{label:u(t)("viewPath"),prop:"view_path"},{default:n(()=>[a(d,{modelValue:e.view_path,"onUpdate:modelValue":l[7]||(l[7]=o=>e.view_path=o),placeholder:u(t)("viewPathPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),[[y,e.menu_type==1]]),c(a(m,{label:u(t)("authId"),prop:"api_url"},{default:n(()=>[a(d,{modelValue:e.api_url,"onUpdate:modelValue":l[9]||(l[9]=o=>e.api_url=o),placeholder:u(t)("authIdPlaceholder"),class:"input-width"},{append:n(()=>[a(T,{class:"w-[90px] border-none",modelValue:e.methods,"onUpdate:modelValue":l[8]||(l[8]=o=>e.methods=o)},{default:n(()=>[a(h,{label:"POST",value:"post"}),a(h,{label:"GET",value:"get"}),a(h,{label:"PUT",value:"put"}),a(h,{label:"DELETE",value:"delete"})]),_:1},8,["modelValue"])]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),[[y,e.menu_type!=0]]),c(a(m,{label:u(t)("menuIcon"),prop:"icon"},{default:n(()=>[S("div",Ve,[a(K,{modelValue:e.icon,"onUpdate:modelValue":l[10]||(l[10]=o=>e.icon=o)},null,8,["modelValue"])])]),_:1},8,["label"]),[[y,e.menu_type!=2]]),c(a(m,{label:u(t)("status")},{default:n(()=>[a(g,{modelValue:e.status,"onUpdate:modelValue":l[11]||(l[11]=o=>e.status=o)},{default:n(()=>[a(s,{label:1},{default:n(()=>[p(i(u(t)("statusNormal")),1)]),_:1}),a(s,{label:0},{default:n(()=>[p(i(u(t)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),[[y,e.menu_type!=2]]),c(a(m,{label:u(t)("isShow")},{default:n(()=>[a(g,{modelValue:e.is_show,"onUpdate:modelValue":l[12]||(l[12]=o=>e.is_show=o)},{default:n(()=>[a(s,{label:1},{default:n(()=>[p(i(u(t)("show")),1)]),_:1}),a(s,{label:0},{default:n(()=>[p(i(u(t)("hidden")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),[[y,e.menu_type!=2]]),a(m,{label:u(t)("menuShortName")},{default:n(()=>[a(d,{modelValue:e.menu_short_name,"onUpdate:modelValue":l[13]||(l[13]=o=>e.menu_short_name=o),placeholder:u(t)("menuShortNamePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(m,{label:u(t)("sort")},{default:n(()=>[a(O,{modelValue:e.sort,"onUpdate:modelValue":l[14]||(l[14]=o=>e.sort=o),min:0},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[z,_.value]])]),_:1},8,["modelValue","title"])}}});export{Xe as _};
