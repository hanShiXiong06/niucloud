/* empty css             *//* empty css                   *//* empty css                */import{a as X,E as Y}from"./el-form-item-144bc604.js";/* empty css                    *//* empty css                        *//* empty css                 *//* empty css                  *//* empty css                       *//* empty css                 */import{_ as Z}from"./index.vue_vue_type_script_setup_true_lang-4c106e55.js";import{_ as ee}from"./index-2de2d5e5.js";/* empty css                 *//* empty css                        */import{d as $}from"./index-1bbcede8.js";import{v as te}from"./event-3e082a4a.js";import{t as a}from"./index-3862e13d.js";import{c as oe}from"./common-a45d855f.js";import{o as le,s as ae}from"./diy-76da287d.js";import{S as re}from"./sortable.esm-be94e56d.js";import{r as se}from"./range-2f27c02f.js";import{E as ne}from"./index-9ef6974e.js";import{E as ie}from"./index-6701860e.js";import{E as me}from"./index-5f2625ed.js";import{E as pe}from"./index-6f670765.js";import{a as de,E as ce}from"./index-6982a78b.js";import{E as ue,b as _e}from"./index-6ff31475.js";import{E as fe}from"./index-3c4f5fae.js";import{E as xe}from"./index-c5af06c2.js";import{v as ve}from"./directive-d226d53f.js";import{d as be,r as y,M as I,o as ge,A as ye,V as P,b as u,e as x,L as B,m as N,p as r,f as n,h as T,F as D,t as H,u as s,q as o,C as j,x as m,v as _}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as Ve}from"./_plugin-vue_export-helper-c27b6911.js";import"./plugin-vue_export-helper-c018272e.js";import"./_baseClone-37ba2e68.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./cloneDeep-028669bf.js";import"./index-a6ffcea0.js";import"./index-72bf6cb5.js";import"./index.vue_vue_type_style_index_0_lang-027a5d0f.js";import"./attachment-92033b47.js";/* empty css               *//* empty css                  *//* empty css                  *//* empty css                  *//* empty css                      *//* empty css                    *//* empty css                 */import"./el-tooltip-4ed993c7.js";/* empty css               *//* empty css                    *//* empty css                   */import"./index-a62384b2.js";import"./index-138bfa06.js";import"./index-b74135ff.js";import"./aria-adfa05c5.js";import"./validator-f5e079db.js";import"./index-4ea26b0e.js";import"./index-d6b4c236.js";import"./index-d64b2f0e.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./index-cefc0b67.js";import"./index-b7b91fcb.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./index-f6eed9e8.js";import"./vue-router-9f815af7.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./_isIterateeCall-797defa1.js";import"./position-0d02b322.js";const Ce={class:"main-container"},he={class:"flex"},ke={class:"w-[360px] h-[400px] absolute mr-[30px] border-[1px] border-gray-300"},Ee={class:"image-slot flex justify-center items-center mt-1"},we={class:"flex-1 ml-[430px]"},Fe={class:"flex items-center border-l-[3px] border-primary pl-[5px] leading-[1.1] mt-[10px]"},Ue={class:"text-[14px]"},Se={class:"text-[12px] ml-[8px] text-gray-500"},$e=["data-id"],Ie={class:"flex align-center"},Pe={class:"flex flex-col justify-center items-center"},Be={class:"mr-[10px] text-sm"},Ne={class:"flex flex-col justify-center items-center"},Te={class:"mr-[10px] text-sm"},De={class:"flex align-center"},He={class:"flex align-center"},je={class:"flex align-center"},Re={class:"fixed-footer-wrap"},Le={class:"fixed-footer"},ze=be({__name:"tabbar",setup(Oe){const k=y("navPicture"),d=y(!1),t=I({backgroundColor:"#FFFFFF",textColor:"#333333",textHoverColor:"#333333",type:"1",list:[]}),R=I({text:"",link:{name:"",title:"",parent:"",url:""},iconSelectPath:"",iconPath:""}),E=()=>{t.list.length>=5||t.list.push({...R})};E();const L=i=>{t.list.splice(i,1)},w=y();((i=1)=>{d.value=!0,le({}).then(e=>{d.value=!1,Object.keys(t).forEach((p,v)=>{t[p]=e.data[p]})}).catch(()=>{d.value=!1})})();const z=async i=>{if(O())return!1;d.value||!i||await i.validate(async e=>{e&&(d.value=!0,ae({menu:t}).then(p=>{d.value=!1}).catch(()=>{d.value=!1}))})},O=()=>{if(t.list.length<2)return $({type:"error",message:a("leastTwoNav")}),!0;try{const i=y("");t.list.forEach((e,p)=>{if(e.iconPath||(i.value=`${a("pleaseUpload")}${p+1}${a("navIcon")}`),e.iconSelectPath||(i.value=`${a("pleaseUpload")}${p+1}${a("navSelectIcon")}`),e.text||(i.value=`${a("pleaseEnter")}[${p+1}${a("navTitle")}`),e.link.url||(i.value=`${a("pleaseChoose")}${p+1}${a("navLink")}`),i.value)throw $({type:"error",message:i.value}),Error()})}catch{return!0}return!1},F=y();return ge(()=>{const i=re.create(F.value,{group:"item-wrap",animation:200,onEnd:e=>{const p=t.list[e.oldIndex];t.list.splice(e.oldIndex,1),t.list.splice(e.newIndex,0,p),ye(()=>{i.sort(se(t.list.length).map(v=>v.toString()))})}})}),(i,e)=>{const p=P("Picture"),v=ne,M=ie,U=ee,f=X,V=me,q=Z,A=P("CircleCloseFilled"),b=pe,S=de,C=ue,G=_e,h=fe,J=ce,K=Y,Q=xe,W=ve;return u(),x("div",Ce,[B((u(),N(Q,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[n("div",he,[n("div",ke,[n("div",{class:"flex items-center justify-between absolute h-[60px] left-[0px] right-[0px] bottom-[0px] bg-white border-[1px] border-primary",style:T({backgroundColor:t.backgroundColor})},[(u(!0),x(D,null,H(t.list,(l,g)=>(u(),x("div",{class:"flex flex-1 flex-col items-center justify-center",key:"b"+g},[["1","2"].includes(t.type.toString())?(u(),N(M,{key:0,class:"w-[22px] h-[22px] mb-[5px] leading-1",src:s(oe)(l.iconPath),fit:i.contain},{error:r(()=>[n("div",Ee,[o(v,null,{default:r(()=>[o(p,{class:"text-3xl text-gray-500"})]),_:1})])]),_:2},1032,["src","fit"])):j("",!0),["1","3"].includes(t.type.toString())?(u(),x("span",{key:1,class:"text-[12px]",style:T({color:t.textColor})},m(l.text),5)):j("",!0)]))),128))],4)]),n("div",we,[n("div",Fe,[n("span",Ue,m(s(a)("bottomNav")),1),n("span",Se,m(s(a)("bottomNavHint")),1)]),o(K,{model:t,"label-width":"100px",ref_key:"formRef",ref:w},{default:r(()=>[o(J,{modelValue:k.value,"onUpdate:modelValue":e[10]||(e[10]=l=>k.value=l),class:"demo-tabs mt-[15px]"},{default:r(()=>[o(S,{label:s(a)("navImage"),name:"navPicture"},{default:r(()=>[n("div",{ref_key:"navItemRef",ref:F},[(u(!0),x(D,null,H(t.list,(l,g)=>(u(),x("div",{key:"a"+g,"data-id":g,class:"item-wrap !cursor-move border-2 border-dashed pt-[18px] m-[10px] mb-[15px] relative list-item"},[o(f,{label:s(a)("navIconOne")},{default:r(()=>[n("div",Ie,[n("div",Pe,[o(U,{modelValue:l.iconPath,"onUpdate:modelValue":c=>l.iconPath=c,width:"60px",height:"60px",limit:1},null,8,["modelValue","onUpdate:modelValue"]),n("span",Be,m(s(a)("uploadImgUnselected")),1)]),n("div",Ne,[o(U,{modelValue:l.iconSelectPath,"onUpdate:modelValue":c=>l.iconSelectPath=c,width:"60px",height:"60px",limit:1},null,8,["modelValue","onUpdate:modelValue"]),n("span",Te,m(s(a)("uploadImgSelected")),1)])])]),_:2},1032,["label"]),o(f,{label:s(a)("navTitleOne")},{default:r(()=>[o(V,{class:"w-[215px]",modelValue:l.text,"onUpdate:modelValue":c=>l.text=c,placeholder:s(a)("titleContent"),maxlength:"5","show-word-limit":""},null,8,["modelValue","onUpdate:modelValue","placeholder"])]),_:2},1032,["label"]),o(f,{label:s(a)("navLinkOne")},{default:r(()=>[o(q,{modelValue:l.link,"onUpdate:modelValue":c=>l.link=c},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"]),o(v,{class:"close-icon cursor-pointer -top-[11px] -right-[8px]",onClick:c=>L(g)},{default:r(()=>[o(A)]),_:2},1032,["onClick"])],8,$e))),128))],512),B(o(b,{type:"primary",class:"mt-[15px]",onClick:E},{default:r(()=>[_(m(s(a)("addnav")),1)]),_:1},512),[[te,t.list.length<5]])]),_:1},8,["label"]),o(S,{label:s(a)("styleSet"),name:"setStyle"},{default:r(()=>[o(f,{label:s(a)("navType")},{default:r(()=>[o(G,{modelValue:t.type,"onUpdate:modelValue":e[0]||(e[0]=l=>t.type=l),class:"ml-4"},{default:r(()=>[o(C,{label:"1",size:"large"},{default:r(()=>[_(m(s(a)("imageText")),1)]),_:1}),o(C,{label:"2",size:"large"},{default:r(()=>[_(m(s(a)("image")),1)]),_:1}),o(C,{label:"3",size:"large"},{default:r(()=>[_(m(s(a)("text")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),o(f,{label:s(a)("textColor")},{default:r(()=>[n("div",De,[o(h,{modelValue:t.textColor,"onUpdate:modelValue":e[1]||(e[1]=l=>t.textColor=l)},null,8,["modelValue"]),o(V,{class:"ml-[10px]",modelValue:t.textColor,"onUpdate:modelValue":e[2]||(e[2]=l=>t.textColor=l),disabled:""},null,8,["modelValue"]),o(b,{class:"ml-[10px]",type:"primary",onClick:e[3]||(e[3]=l=>t.textColor="#333333")},{default:r(()=>[_(m(s(a)("reset")),1)]),_:1})])]),_:1},8,["label"]),o(f,{label:s(a)("textSelectColor")},{default:r(()=>[n("div",He,[o(h,{modelValue:t.textHoverColor,"onUpdate:modelValue":e[4]||(e[4]=l=>t.textHoverColor=l)},null,8,["modelValue"]),o(V,{class:"ml-[10px]",modelValue:t.textHoverColor,"onUpdate:modelValue":e[5]||(e[5]=l=>t.textHoverColor=l),disabled:""},null,8,["modelValue"]),o(b,{class:"ml-[10px]",type:"primary",onClick:e[6]||(e[6]=l=>t.textHoverColor="#333333")},{default:r(()=>[_(m(s(a)("reset")),1)]),_:1})])]),_:1},8,["label"]),o(f,{label:s(a)("backgroundColor")},{default:r(()=>[n("div",je,[o(h,{modelValue:t.backgroundColor,"onUpdate:modelValue":e[7]||(e[7]=l=>t.backgroundColor=l)},null,8,["modelValue"]),o(V,{class:"ml-[10px]",modelValue:t.backgroundColor,"onUpdate:modelValue":e[8]||(e[8]=l=>t.backgroundColor=l),disabled:""},null,8,["modelValue"]),o(b,{class:"ml-[10px]",type:"primary",onClick:e[9]||(e[9]=l=>t.backgroundColor="#FFFFFF")},{default:r(()=>[_(m(s(a)("reset")),1)]),_:1})])]),_:1},8,["label"])]),_:1},8,["label"])]),_:1},8,["modelValue"])]),_:1},8,["model"])])])]),_:1})),[[W,d.value]]),n("div",Re,[n("div",Le,[o(b,{type:"primary",onClick:e[11]||(e[11]=l=>z(w.value))},{default:r(()=>[_(m(s(a)("save")),1)]),_:1})])])])}}});const fo=Ve(ze,[["__scopeId","data-v-37011145"]]);export{fo as default};