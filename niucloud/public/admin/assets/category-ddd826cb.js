/* empty css             *//* empty css                   *//* empty css                *//* empty css                        *//* empty css                    *//* empty css               */import"./el-tooltip-4ed993c7.js";/* empty css                  */import"./index-d5447f06.js";/* empty css                 *//* empty css                        *//* empty css                  */import{_ as q}from"./category_default-9a714445.js";import{t as i}from"./index-ebefdd26.js";import{g as P,u as z,i as A,j as O}from"./goods-f7db3cf5.js";import{c as G}from"./common-a45d855f.js";import{_ as H}from"./category-edit.vue_vue_type_style_index_0_lang-9eb10fe9.js";import{u as J}from"./vue-router-9f815af7.js";import{S as K}from"./sortable.esm-be94e56d.js";import{a4 as Q}from"./event-3e082a4a.js";import{c as E}from"./cloneDeep-028669bf.js";import{E as U}from"./index-b74135ff.js";import{E as W}from"./index-6f670765.js";import{a as X,E as Y}from"./index-6191b0b4.js";import{E as Z}from"./index-6701860e.js";import{E as tt}from"./index-5c20ff8f.js";import{E as et}from"./index-c5af06c2.js";import{v as ot}from"./directive-d226d53f.js";import{d as at,M as rt,r as I,o as it,A as st,b as D,e as nt,q as c,p as n,f as m,x as u,u as p,v as x,L as lt,m as ct,at as pt,au as dt}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as mt}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./plugin-vue_export-helper-c018272e.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./index-9ef6974e.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./el-form-item-144bc604.js";import"./_baseClone-37ba2e68.js";/* empty css                 */import"./index-a6ffcea0.js";import"./index-5f2625ed.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";/* empty css                  */import"./index-76a877c2.js";import"./index.vue_vue_type_style_index_0_lang-b9e244b2.js";import"./attachment-2dcaf405.js";/* empty css                  *//* empty css                  *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                    *//* empty css                   */import"./index-0ee6390f.js";import"./index-4ea26b0e.js";import"./index-d6b4c236.js";import"./index-d64b2f0e.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./index-cefc0b67.js";import"./index-b7b91fcb.js";import"./strings-e72e60f7.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./validator-f5e079db.js";import"./index-f6eed9e8.js";import"./index-1b611f36.js";import"./aria-adfa05c5.js";import"./_isIterateeCall-797defa1.js";import"./position-0d02b322.js";const R=y=>(pt("data-v-9a9f2699"),y=y(),dt(),y),_t={class:"main-container"},ut={class:"flex justify-between items-center"},ft={class:"text-[20px]"},gt={class:"mt-[10px]"},ht=R(()=>m("i",{class:"order-0 iconfont icontuodong vues-rank cursor-move mr-[8px]"},null,-1)),yt={class:"order-2"},vt={class:"h-[30px]"},xt=R(()=>m("div",{class:"image-slot"},[m("img",{class:"w-[30px] h-[30px]",src:q})],-1)),wt=at({__name:"category",setup(y){const S=J().meta.title,T=Q(),o=rt({loading:!0,data:[],searchParam:{category_name:""}});I(),it(()=>{st(()=>{B()}),w()});const d=I([]),B=()=>{const e=T.value.$el.querySelector(".el-table__body-wrapper tbody");K.create(e,{handle:".vues-rank",animation:300,onMove:({dragged:a,related:l})=>{const s=d.value[a.rowIndex],_=d.value[l.rowIndex];if(s.pid!==_.pid)return!1},onStart:()=>{d.value=N(E(o.data))},onEnd:a=>{var v;const l=d.value[a.oldIndex],s=d.value[a.newIndex];if(a.oldIndex===a.newIndex||l.pid!==s.pid||d.value.indexOf(l)<0)return!1;const r=d.value.splice(a.oldIndex,1)[0];d.value.splice(a.newIndex,0,r);const b=s.pid,C=(v=d.value.filter(t=>t.pid===b))==null?void 0:v.map((t,f)=>{if(t.level===1&&t.category_id===r.category_id&&(o.data=o.data.filter(g=>g.category_id!==r.category_id),o.data.splice(f,0,r)),t.level===2&&t.category_id===r.category_id){const g=o.data.findIndex(k=>k.category_id===t.pid),V=E(o.data[g].child_list.filter(k=>k.category_id!==r.category_id));o.data[g].child_list=[],o.data[g].child_list.push(...V),o.data[g].child_list.splice(f,0,r)}return{category_id:t.category_id,sort:9999-f}});$({category_sort_array:C})}})},N=(e,a="child_list")=>{const l=[],s=_=>{_&&_.length>0&&_.filter(r=>r).forEach(r=>{l.push(r),s(r[a]||[])})};return s(e),l},w=()=>{o.loading=!0,P({...o.searchParam}).then(e=>{o.loading=!1,o.data=e.data}).catch(()=>{o.loading=!1})},$=e=>{z(e).then(a=>{})},j=e=>{e.is_show=e.is_show===1?2:1;const a=E(e);delete a.child_list,A(a)},h=I(null),L=()=>{h.value.setFormData(),h.value.showDialog=!0},M=e=>{h.value.setFormData(e),h.value.showDialog=!0},F=e=>{U.confirm(!e.child_list||!e.child_list.length?i("categoryDeleteTips"):i("categoryDeleteTips1"),i("warning"),{confirmButtonText:i("confirm"),cancelButtonText:i("cancel"),type:"warning"}).then(()=>{O(e.category_id).then(()=>{w()}).catch(()=>{})})};return(e,a)=>{const l=W,s=X,_=Z,r=tt,b=Y,C=et,v=ot;return D(),nt("div",_t,[c(C,{class:"box-card !border-none",shadow:"never"},{default:n(()=>[m("div",ut,[m("span",ft,u(p(S)),1),c(l,{type:"primary",onClick:L},{default:n(()=>[x(u(p(i)("addCategory")),1)]),_:1})]),m("div",gt,[lt((D(),ct(b,{data:o.data,ref_key:"tableRef",ref:T,size:"large","row-key":"category_id","tree-props":{hasChildren:"hasChildren",children:"child_list"}},{empty:n(()=>[m("span",null,u(o.loading?"":p(i)("emptyData")),1)]),default:n(()=>[c(s,{label:p(i)("categoryName"),"min-width":"120"},{default:n(({row:t})=>[ht,m("span",yt,u(t.category_name),1)]),_:1},8,["label"]),c(s,{label:p(i)("image"),width:"170",align:"left"},{default:n(({row:t})=>[m("div",vt,[c(_,{class:"w-[30px] h-[30px]",src:p(G)(t.image),fit:"contain"},{error:n(()=>[xt]),_:2},1032,["src"])])]),_:1},8,["label"]),c(s,{prop:"is_show",label:p(i)("isShow"),width:"400"},{default:n(({row:t})=>[c(r,{class:"cursor-pointer",type:t.is_show!=2?"success":"danger",onClick:f=>j(t)},{default:n(()=>[x(u(t.is_show!=2?"是":"否"),1)]),_:2},1032,["type","onClick"])]),_:1},8,["label"]),c(s,{label:p(i)("operation"),fixed:"right",align:"right",width:"120"},{default:n(({row:t})=>[c(l,{type:"primary",link:"",onClick:f=>M(t)},{default:n(()=>[x(u(p(i)("edit")),1)]),_:2},1032,["onClick"]),c(l,{type:"primary",link:"",onClick:f=>F(t)},{default:n(()=>[x(u(p(i)("delete")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[v,o.loading]])]),c(H,{ref_key:"editCategoryDialog",ref:h,onComplete:w},null,512)]),_:1})])}}});const Ge=mt(wt,[["__scopeId","data-v-9a9f2699"]]);export{Ge as default};
