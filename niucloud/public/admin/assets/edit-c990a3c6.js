import{d as j,r as h,R as q,c as $,e as c,f as U,g as p,u as a,B as u,y as r,x as i,Q as z,v as C,F as G,z as M,A as _}from"./base-04829be5.js";/* empty css                   *//* empty css                  *//* empty css                *//* empty css                     *//* empty css                        *//* empty css                 *//* empty css                 */import{_ as Q}from"./index.vue_vue_type_script_setup_true_lang-6dde38ee.js";import{_ as H}from"./index-c1ab0e3c.js";/* empty css               *//* empty css                  *//* empty css                  *//* empty css                     *//* empty css                  */import{u as J,t}from"./index-043d021e.js";import{c as K,f as W,h as X,i as Y}from"./article-082cb1c5.js";import{u as Z,b as ee}from"./vue-router-fee568b2.js";import{d as te}from"./index-faea7bd5.js";import{a as oe}from"./index-92283b18.js";import{E as le}from"./index-db9b8d96.js";import{a as ae,E as re}from"./index-6bd50bb5.js";import{a as ie,E as se}from"./index-02bf3820.js";import{E as me,b as ne}from"./index-1cbf3455.js";import{E as pe}from"./index-ee32f612.js";import{E as de}from"./index-88566e4e.js";import{E as ue}from"./index-eb678249.js";import{v as ce}from"./directive-013f0a4e.js";import"./index.vue_vue_type_style_index_0_lang-f0796d29.js";/* empty css                   */import"./attachment-9a932beb.js";import"./index-30df2c14.js";import"./index-7e933ae4.js";/* empty css                        *//* empty css                      *//* empty css                    *//* empty css                 */import"./el-tooltip-4ed993c7.js";/* empty css                 *//* empty css               *//* empty css                    *//* empty css                         */import"./index-bdd39755.js";import"./index-94a82d50.js";import"./index-a2524300.js";import"./index-de053f2e.js";import"./focus-trap-be36cfe9.js";import"./index-bf8db610.js";import"./index-e9e16697.js";import"./error-78e43d3e.js";import"./index-1d455165.js";import"./index-b1557f8a.js";import"./index-9a9de0a3.js";import"./scroll-e5463626.js";import"./vnode-85ccdc7f.js";import"./event-9519ab40.js";import"./index-4edf2cad.js";import"./index.vue_vue_type_script_setup_true_lang-df8a984f.js";/* empty css                */import"./sys-f9859bed.js";import"./storage-1a3ddb14.js";import"./index-d60f63e2.js";import"./aria-adfa05c5.js";import"./validator-6838b9a3.js";import"./index-760fce0d.js";import"./typescript-defaf979.js";import"./index-cbf0aee7.js";import"./index-c4af56cf.js";import"./index-ed22fe56.js";import"./debounce-f064e94e.js";import"./position-b298e95e.js";import"./index-91afef8c.js";import"./index-c3b3b83a.js";import"./index-d7f4b4bb.js";import"./isEqual-ba353d68.js";import"./_Uint8Array-99b916e9.js";import"./flatten-94587e2b.js";import"./index-1808e3f9.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./common-111e3797.js";import"./common-2cf17469.js";import"./index-236cb599.js";import"./castArray-11aea762.js";import"./_initCloneObject-e5a1aa13.js";import"./index-bf9de702.js";import"./strings-4ec3ae35.js";import"./index-b519934c.js";import"./index-9d53c87f.js";const _e={class:"main-container"},fe={class:"detail-head"},ge=p("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1),be={class:"ml-[1px]"},he=p("span",{class:"adorn"},"|",-1),ve={class:"right"},Ve={class:"fixed-footer-wrap"},ye={class:"fixed-footer"},ro=j({__name:"edit",setup(we){const f=Z(),g=ee(),b=parseInt(f.query.id||0),n=h(!1),v=h([]),R=te();J();const S=f.meta.title,V={id:"",category_id:"",title:"",intro:"",summary:"",image:"",author:"",content:"",visit:"",visit_virtual:"",is_show:1,sort:0},o=q({...V});b&&(async(d=0)=>{if(n.value=!0,Object.assign(o,V),d){const e=await(await K(d)).data;if(!e||Object.keys(e).length==0)return oe.error(t("articleNull")),setTimeout(()=>{g.go(-1)},2e3),!1;Object.keys(o).forEach(s=>{e[s]!=null&&(o[s]=e[s])}),n.value=!1}else n.value=!1})(b),(async()=>{v.value=await(await W({})).data})();const y=h(),k=$(()=>({title:[{required:!0,message:t("titlePlaceholder"),trigger:"blur"}],category_id:[{required:!0,message:t("categoryIdPlaceholder"),trigger:"blur"}],content:[{required:!0,message:t("contentPlaceholder"),trigger:"blur"},{validator:(d,e,s)=>{!e.replace(/<[^<>]+>/g,"").replace(/&nbsp;/gi,"")&&e.indexOf("img")===-1?s(new Error(t("contentPlaceholder"))):s()},trigger:["blur","change"]}]})),A=async d=>{n.value||!d||await d.validate(async e=>{e&&(n.value=!0,(b?X:Y)(o).then(x=>{n.value=!1,w()}).catch(()=>{n.value=!1}))})},w=()=>{R.removeTab(f.path),g.push({path:"/article/list"})};return(d,e)=>{const s=le,m=ae,x=ie,F=se,I=H,N=Q,E=me,B=ne,D=pe,L=re,O=de,P=ue,T=ce;return c(),U("div",_e,[p("div",fe,[p("div",{class:"left",onClick:e[0]||(e[0]=l=>a(g).push({path:"/article/list"}))},[ge,p("span",be,u(a(t)("returnToPreviousPage")),1)]),he,p("span",ve,u(a(S)),1)]),r(O,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[z((c(),C(L,{model:o,"label-width":"90px",ref_key:"formRef",ref:y,rules:a(k),class:"page-form"},{default:i(()=>[r(m,{label:a(t)("title"),prop:"title"},{default:i(()=>[r(s,{modelValue:o.title,"onUpdate:modelValue":e[1]||(e[1]=l=>o.title=l),clearable:"",placeholder:a(t)("titlePlaceholder"),class:"input-width",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("categoryName"),prop:"category_id"},{default:i(()=>[r(F,{modelValue:o.category_id,"onUpdate:modelValue":e[2]||(e[2]=l=>o.category_id=l),clearable:"",placeholder:a(t)("categoryIdPlaceholder"),class:"input-width"},{default:i(()=>[(c(!0),U(G,null,M(v.value,l=>(c(),C(x,{label:l.name,value:l.category_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("intro"),prop:"intro"},{default:i(()=>[r(s,{modelValue:o.intro,"onUpdate:modelValue":e[3]||(e[3]=l=>o.intro=l),type:"textarea",rows:"4",clearable:"",placeholder:a(t)("introPlaceholder"),class:"input-width",maxlength:"50"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("summary"),prop:"summary"},{default:i(()=>[r(s,{modelValue:o.summary,"onUpdate:modelValue":e[4]||(e[4]=l=>o.summary=l),type:"textarea",rows:"4",clearable:"",placeholder:a(t)("summaryPlaceholder"),class:"input-width",maxlength:"50"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("image")},{default:i(()=>[r(I,{modelValue:o.image,"onUpdate:modelValue":e[5]||(e[5]=l=>o.image=l)},null,8,["modelValue"])]),_:1},8,["label"]),r(m,{label:a(t)("author"),prop:"author"},{default:i(()=>[r(s,{modelValue:o.author,"onUpdate:modelValue":e[6]||(e[6]=l=>o.author=l),clearable:"",placeholder:a(t)("authorPlaceholder"),class:"input-width",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("content"),prop:"content"},{default:i(()=>[r(N,{modelValue:o.content,"onUpdate:modelValue":e[7]||(e[7]=l=>o.content=l)},null,8,["modelValue"])]),_:1},8,["label"]),r(m,{label:a(t)("visitVirtual")},{default:i(()=>[r(s,{modelValue:o.visit_virtual,"onUpdate:modelValue":e[8]||(e[8]=l=>o.visit_virtual=l),clearable:"",placeholder:a(t)("visitVirtualPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("isShow")},{default:i(()=>[r(B,{modelValue:o.is_show,"onUpdate:modelValue":e[9]||(e[9]=l=>o.is_show=l),placeholder:a(t)("isShowPlaceholder")},{default:i(()=>[r(E,{label:1},{default:i(()=>[_(u(a(t)("show")),1)]),_:1}),r(E,{label:0},{default:i(()=>[_(u(a(t)("hidden")),1)]),_:1})]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(t)("sort"),prop:"sort"},{default:i(()=>[r(D,{modelValue:o.sort,"onUpdate:modelValue":e[10]||(e[10]=l=>o.sort=l),min:0},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[T,n.value]])]),_:1}),p("div",Ve,[p("div",ye,[r(P,{type:"primary",onClick:e[11]||(e[11]=l=>A(y.value))},{default:i(()=>[_(u(a(t)("save")),1)]),_:1}),r(P,{onClick:e[12]||(e[12]=l=>w())},{default:i(()=>[_(u(a(t)("cancel")),1)]),_:1})])])])}}});export{ro as default};