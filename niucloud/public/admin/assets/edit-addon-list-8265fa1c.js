/* empty css             *//* empty css                   */import{E as A}from"./el-overlay-380df695.js";/* empty css                        *//* empty css                    *//* empty css               */import"./el-tooltip-4ed993c7.js";/* empty css                  */import{T as z,_ as F}from"./index-1bbcede8.js";/* empty css                 *//* empty css                        */import{E as N}from"./el-form-item-144bc604.js";/* empty css                  */import{v as f}from"./event-3e082a4a.js";import{t as i}from"./index-3862e13d.js";import{u as j}from"./diy-d0990451.js";import{c as v}from"./common-a45d855f.js";import{S as M}from"./sortable.esm-be94e56d.js";import{r as P}from"./range-2f27c02f.js";import{E as R}from"./index-6f670765.js";import{E as $}from"./index-6701860e.js";import{a as O,E as q}from"./index-6191b0b4.js";import{v as U}from"./directive-d226d53f.js";import{d as W,r as C,o as G,A as H,M as J,b as p,e as x,L as m,u as t,f as a,x as _,q as r,p as c,F as w,t as K,v as Q,m as y,C as X,g as Y}from"./runtime-core.esm-bundler-c17ab5bc.js";const Z={class:"content-wrap"},ee={class:"edit-attr-item-wrap"},te={class:"mb-[10px]"},oe={class:"flex items-center pb-[10px]"},ae=["src"],le={class:"flex flex-col justify-center ml-[10px] leading-[1]"},ne={class:"text-[14px]"},se={class:"text-[12px] text-[#999] mt-[8px]"},ie=["onClick"],re={class:"style-wrap"},de=W({__name:"edit-addon-list",setup(ce,{expose:k}){const o=j();o.editComponent.ignore=[],o.editComponent.verify=s=>{var e={code:!0,message:""};return e};const u=C(!1),b=C();G(()=>{H(()=>{const s=M.create(b.value,{group:"item-wrap",animation:200,onEnd:e=>{const d=o.editComponent.list[e.oldIndex];o.editComponent.list.splice(e.oldIndex,1),o.editComponent.list.splice(e.newIndex,0,d),s.sort(P(o.editComponent.list.length).map(g=>g.toString()))}})})});const l=J({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{title:"",key:""}});((s=1)=>{l.loading=!0,l.page=s,z({...l.searchParam}).then(e=>{l.loading=!1,l.data=e.data,l.total=e.data.length}).catch(()=>{l.loading=!1})})();const E=s=>{let e={id:o.generateRandom(),key:"",title:"",url:"",icon:"",desc:""};for(let d in s)e[d]=s[d];o.editComponent.list.push(e),u.value=!1},S=()=>{u.value=!0};return k({}),(s,e)=>{const d=F,g=R,T=N,D=$,h=O,L=q,B=A,V=U;return p(),x(w,null,[m(a("div",Z,[a("div",ee,[a("h3",te,_(t(i)("addonListSet")),1),r(T,{"label-width":"100px",class:"px-[10px]"},{default:c(()=>[a("div",{ref_key:"addonBoxRef",ref:b},[(p(!0),x(w,null,K(t(o).editComponent.list,(n,I)=>(p(),x("div",{key:n.id,class:"item-wrap !cursor-move p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]"},[m(a("div",oe,[a("img",{class:"w-[60px] h-[60px] rounded-md",src:t(v)(n.icon)},null,8,ae),a("div",le,[a("span",ne,_(n.title),1),a("span",se,_(n.desc),1)])],512),[[f,n.title]]),m(a("div",{class:"del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]",onClick:me=>t(o).editComponent.list.splice(I,1)},[r(d,{name:"element-CircleCloseFilled",color:"#bbb",size:"20px"})],8,ie),[[f,t(o).editComponent.list.length>1]])]))),128))],512),r(g,{class:"w-full",onClick:S},{default:c(()=>[Q(_(t(i)("addAddon")),1)]),_:1})]),_:1})]),r(B,{modelValue:u.value,"onUpdate:modelValue":e[0]||(e[0]=n=>u.value=n),title:t(i)("addonListTips"),width:"600px","close-on-press-escape":!1,"close-on-click-modal":!1},{default:c(()=>[a("div",null,[m((p(),y(L,{data:l.data,size:"large",onCurrentChange:E,"highlight-current-row":"","max-height":"500px"},{empty:c(()=>[a("span",null,_(l.loading?"":t(i)("emptyData")),1)]),default:c(()=>[r(h,{label:t(i)("addonIcon"),width:"120",align:"center"},{default:c(({row:n})=>[n.icon?(p(),y(D,{key:0,class:"w-[50px] h-[50px]",src:t(v)(n.icon),fit:"contain"},null,8,["src"])):X("",!0)]),_:1},8,["label"]),r(h,{prop:"title","show-overflow-tooltip":!0,width:"120",label:t(i)("addonTitle")},null,8,["label"]),r(h,{prop:"desc","show-overflow-tooltip":!0,label:t(i)("addonDesc")},null,8,["label"])]),_:1},8,["data"])),[[V,l.loading]])])]),_:1},8,["modelValue","title"])],512),[[f,t(o).editTab=="content"]]),m(a("div",re,[Y(s.$slots,"style")],512),[[f,t(o).editTab=="style"]])],64)}}}),je=Object.freeze(Object.defineProperty({__proto__:null,default:de},Symbol.toStringTag,{value:"Module"}));export{je as _};
