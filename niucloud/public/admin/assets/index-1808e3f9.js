import{b as q,s as W,E as X,d as Y,G as ee,a4 as oe,am as G,a5 as le,a as I,h as ue,j as ce}from"./index-7e933ae4.js";import{d as k,c as N,e as u,f as C,B as M,v as $,x as H,Z as ae,u as n,_ as A,I as de,b as L,r as x,w as V,y as ne,F as te,z as se,n as S,g as Z,a as ge,H as K,P as pe,J as fe,U as T,l as me}from"./base-04829be5.js";import{a as ve,E as be}from"./index-02bf3820.js";import{m as ie}from"./typescript-defaf979.js";import{u as D}from"./index-1d455165.js";import{i as Pe}from"./isEqual-ba353d68.js";import{E as Ce}from"./index-db9b8d96.js";import{w as he}from"./index-30df2c14.js";import{d as ye}from"./error-78e43d3e.js";const re=Symbol("elPaginationKey"),_e=q({disabled:Boolean,currentPage:{type:Number,default:1},prevText:{type:String},prevIcon:{type:W}}),ze={click:e=>e instanceof MouseEvent},ke=["disabled","aria-disabled"],Se={key:0},Ne=k({name:"ElPaginationPrev"}),xe=k({...Ne,props:_e,emits:ze,setup(e){const o=e,t=N(()=>o.disabled||o.currentPage<=1);return(r,l)=>(u(),C("button",{type:"button",class:"btn-prev",disabled:n(t),"aria-disabled":n(t),onClick:l[0]||(l[0]=p=>r.$emit("click",p))},[r.prevText?(u(),C("span",Se,M(r.prevText),1)):(u(),$(n(X),{key:1},{default:H(()=>[(u(),$(ae(r.prevIcon)))]),_:1}))],8,ke))}});var Ee=A(xe,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/prev.vue"]]);const we=q({disabled:Boolean,currentPage:{type:Number,default:1},pageCount:{type:Number,default:50},nextText:{type:String},nextIcon:{type:W}}),$e=["disabled","aria-disabled"],Te={key:0},Be=k({name:"ElPaginationNext"}),Ie=k({...Be,props:we,emits:["click"],setup(e){const o=e,t=N(()=>o.disabled||o.currentPage===o.pageCount||o.pageCount===0);return(r,l)=>(u(),C("button",{type:"button",class:"btn-next",disabled:n(t),"aria-disabled":n(t),onClick:l[0]||(l[0]=p=>r.$emit("click",p))},[r.nextText?(u(),C("span",Te,M(r.nextText),1)):(u(),$(n(X),{key:1},{default:H(()=>[(u(),$(ae(r.nextIcon)))]),_:1}))],8,$e))}});var Me=A(Ie,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/next.vue"]]);const R=()=>de(re,{}),qe=q({pageSize:{type:Number,required:!0},pageSizes:{type:Y(Array),default:()=>ie([10,20,30,40,50,100])},popperClass:{type:String},disabled:Boolean,size:{type:String,values:ee}}),Le=k({name:"ElPaginationSizes"}),Ae=k({...Le,props:qe,emits:["page-size-change"],setup(e,{emit:o}){const t=e,{t:r}=D(),l=L("pagination"),p=R(),h=x(t.pageSize);V(()=>t.pageSizes,(c,y)=>{if(!Pe(c,y)&&Array.isArray(c)){const d=c.includes(t.pageSize)?t.pageSize:t.pageSizes[0];o("page-size-change",d)}}),V(()=>t.pageSize,c=>{h.value=c});const z=N(()=>t.pageSizes);function E(c){var y;c!==h.value&&(h.value=c,(y=p.handleSizeChange)==null||y.call(p,Number(c)))}return(c,y)=>(u(),C("span",{class:S(n(l).e("sizes"))},[ne(n(be),{"model-value":h.value,disabled:c.disabled,"popper-class":c.popperClass,size:c.size,"validate-event":!1,onChange:E},{default:H(()=>[(u(!0),C(te,null,se(n(z),d=>(u(),$(n(ve),{key:d,value:d,label:d+n(r)("el.pagination.pagesize")},null,8,["value","label"]))),128))]),_:1},8,["model-value","disabled","popper-class","size"])],2))}});var je=A(Ae,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/sizes.vue"]]);const Fe=q({size:{type:String,values:ee}}),Ue=["disabled"],Ke=k({name:"ElPaginationJumper"}),We=k({...Ke,props:Fe,setup(e){const{t:o}=D(),t=L("pagination"),{pageCount:r,disabled:l,currentPage:p,changeEvent:h}=R(),z=x(),E=N(()=>{var d;return(d=z.value)!=null?d:p==null?void 0:p.value});function c(d){z.value=+d}function y(d){d=Math.trunc(+d),h==null||h(+d),z.value=void 0}return(d,m)=>(u(),C("span",{class:S(n(t).e("jump")),disabled:n(l)},[Z("span",{class:S([n(t).e("goto")])},M(n(o)("el.pagination.goto")),3),ne(n(Ce),{size:d.size,class:S([n(t).e("editor"),n(t).is("in-pagination")]),min:1,max:n(r),disabled:n(l),"model-value":n(E),"validate-event":!1,type:"number","onUpdate:modelValue":c,onChange:y},null,8,["size","class","max","disabled","model-value"]),Z("span",{class:S([n(t).e("classifier")])},M(n(o)("el.pagination.pageClassifier")),3)],10,Ue))}});var De=A(We,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/jumper.vue"]]);const Je=q({total:{type:Number,default:1e3}}),Oe=["disabled"],Ve=k({name:"ElPaginationTotal"}),He=k({...Ve,props:Je,setup(e){const{t:o}=D(),t=L("pagination"),{disabled:r}=R();return(l,p)=>(u(),C("span",{class:S(n(t).e("total")),disabled:n(r)},M(n(o)("el.pagination.total",{total:l.total})),11,Oe))}});var Re=A(He,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/total.vue"]]);const Ge=q({currentPage:{type:Number,default:1},pageCount:{type:Number,required:!0},pagerCount:{type:Number,default:7},disabled:Boolean}),Ze=["onKeyup"],Qe=["aria-current","tabindex"],Xe=["tabindex"],Ye=["aria-current","tabindex"],ea=["tabindex"],aa=["aria-current","tabindex"],na=k({name:"ElPaginationPager"}),ta=k({...na,props:Ge,emits:["change"],setup(e,{emit:o}){const t=e,r=L("pager"),l=L("icon"),p=x(!1),h=x(!1),z=x(!1),E=x(!1),c=x(!1),y=x(!1),d=N(()=>{const s=t.pagerCount,i=(s-1)/2,a=Number(t.currentPage),g=Number(t.pageCount);let v=!1,_=!1;g>s&&(a>s-i&&(v=!0),a<g-i&&(_=!0));const B=[];if(v&&!_){const b=g-(s-2);for(let w=b;w<g;w++)B.push(w)}else if(!v&&_)for(let b=2;b<s;b++)B.push(b);else if(v&&_){const b=Math.floor(s/2)-1;for(let w=a-b;w<=a+b;w++)B.push(w)}else for(let b=2;b<g;b++)B.push(b);return B}),m=N(()=>t.disabled?-1:0);ge(()=>{const s=(t.pagerCount-1)/2;p.value=!1,h.value=!1,t.pageCount>t.pagerCount&&(t.currentPage>t.pagerCount-s&&(p.value=!0),t.currentPage<t.pageCount-s&&(h.value=!0))});function f(s=!1){t.disabled||(s?z.value=!0:E.value=!0)}function j(s=!1){s?c.value=!0:y.value=!0}function J(s){const i=s.target;if(i.tagName.toLowerCase()==="li"&&Array.from(i.classList).includes("number")){const a=Number(i.textContent);a!==t.currentPage&&o("change",a)}else i.tagName.toLowerCase()==="li"&&Array.from(i.classList).includes("more")&&U(s)}function U(s){const i=s.target;if(i.tagName.toLowerCase()==="ul"||t.disabled)return;let a=Number(i.textContent);const g=t.pageCount,v=t.currentPage,_=t.pagerCount-2;i.className.includes("more")&&(i.className.includes("quickprev")?a=v-_:i.className.includes("quicknext")&&(a=v+_)),Number.isNaN(+a)||(a<1&&(a=1),a>g&&(a=g)),a!==v&&o("change",a)}return(s,i)=>(u(),C("ul",{class:S(n(r).b()),onClick:U,onKeyup:he(J,["enter"])},[s.pageCount>0?(u(),C("li",{key:0,class:S([[n(r).is("active",s.currentPage===1),n(r).is("disabled",s.disabled)],"number"]),"aria-current":s.currentPage===1,tabindex:n(m)}," 1 ",10,Qe)):K("v-if",!0),p.value?(u(),C("li",{key:1,class:S(["more","btn-quickprev",n(l).b(),n(r).is("disabled",s.disabled)]),tabindex:n(m),onMouseenter:i[0]||(i[0]=a=>f(!0)),onMouseleave:i[1]||(i[1]=a=>z.value=!1),onFocus:i[2]||(i[2]=a=>j(!0)),onBlur:i[3]||(i[3]=a=>c.value=!1)},[(z.value||c.value)&&!s.disabled?(u(),$(n(oe),{key:0})):(u(),$(n(G),{key:1}))],42,Xe)):K("v-if",!0),(u(!0),C(te,null,se(n(d),a=>(u(),C("li",{key:a,class:S([[n(r).is("active",s.currentPage===a),n(r).is("disabled",s.disabled)],"number"]),"aria-current":s.currentPage===a,tabindex:n(m)},M(a),11,Ye))),128)),h.value?(u(),C("li",{key:2,class:S(["more","btn-quicknext",n(l).b(),n(r).is("disabled",s.disabled)]),tabindex:n(m),onMouseenter:i[4]||(i[4]=a=>f()),onMouseleave:i[5]||(i[5]=a=>E.value=!1),onFocus:i[6]||(i[6]=a=>j()),onBlur:i[7]||(i[7]=a=>y.value=!1)},[(E.value||y.value)&&!s.disabled?(u(),$(n(le),{key:0})):(u(),$(n(G),{key:1}))],42,ea)):K("v-if",!0),s.pageCount>1?(u(),C("li",{key:3,class:S([[n(r).is("active",s.currentPage===s.pageCount),n(r).is("disabled",s.disabled)],"number"]),"aria-current":s.currentPage===s.pageCount,tabindex:n(m)},M(s.pageCount),11,aa)):K("v-if",!0)],42,Ze))}});var sa=A(ta,[["__file","/home/runner/work/element-plus/element-plus/packages/components/pagination/src/components/pager.vue"]]);const P=e=>typeof e!="number",ia=q({total:Number,pageSize:Number,defaultPageSize:Number,currentPage:Number,defaultCurrentPage:Number,pageCount:Number,pagerCount:{type:Number,validator:e=>I(e)&&Math.trunc(e)===e&&e>4&&e<22&&e%2===1,default:7},layout:{type:String,default:["prev","pager","next","jumper","->","total"].join(", ")},pageSizes:{type:Y(Array),default:()=>ie([10,20,30,40,50,100])},popperClass:{type:String,default:""},prevText:{type:String,default:""},prevIcon:{type:W,default:()=>ue},nextText:{type:String,default:""},nextIcon:{type:W,default:()=>ce},small:Boolean,background:Boolean,disabled:Boolean,hideOnSinglePage:Boolean}),ra={"update:current-page":e=>I(e),"update:page-size":e=>I(e),"size-change":e=>I(e),"current-change":e=>I(e),"prev-click":e=>I(e),"next-click":e=>I(e)},Q="ElPagination";var oa=k({name:Q,props:ia,emits:ra,setup(e,{emit:o,slots:t}){const{t:r}=D(),l=L("pagination"),p=pe().vnode.props||{},h="onUpdate:currentPage"in p||"onUpdate:current-page"in p||"onCurrentChange"in p,z="onUpdate:pageSize"in p||"onUpdate:page-size"in p||"onSizeChange"in p,E=N(()=>{if(P(e.total)&&P(e.pageCount)||!P(e.currentPage)&&!h)return!1;if(e.layout.includes("sizes")){if(P(e.pageCount)){if(!P(e.total)&&!P(e.pageSize)&&!z)return!1}else if(!z)return!1}return!0}),c=x(P(e.defaultPageSize)?10:e.defaultPageSize),y=x(P(e.defaultCurrentPage)?1:e.defaultCurrentPage),d=N({get(){return P(e.pageSize)?c.value:e.pageSize},set(a){P(e.pageSize)&&(c.value=a),z&&(o("update:page-size",a),o("size-change",a))}}),m=N(()=>{let a=0;return P(e.pageCount)?P(e.total)||(a=Math.max(1,Math.ceil(e.total/d.value))):a=e.pageCount,a}),f=N({get(){return P(e.currentPage)?y.value:e.currentPage},set(a){let g=a;a<1?g=1:a>m.value&&(g=m.value),P(e.currentPage)&&(y.value=g),h&&(o("update:current-page",g),o("current-change",g))}});V(m,a=>{f.value>a&&(f.value=a)});function j(a){f.value=a}function J(a){d.value=a;const g=m.value;f.value>g&&(f.value=g)}function U(){e.disabled||(f.value-=1,o("prev-click",f.value))}function s(){e.disabled||(f.value+=1,o("next-click",f.value))}function i(a,g){a&&(a.props||(a.props={}),a.props.class=[a.props.class,g].join(" "))}return fe(re,{pageCount:m,disabled:N(()=>e.disabled),currentPage:f,changeEvent:j,handleSizeChange:J}),()=>{var a,g;if(!E.value)return ye(Q,r("el.pagination.deprecationWarning")),null;if(!e.layout||e.hideOnSinglePage&&m.value<=1)return null;const v=[],_=[],B=T("div",{class:l.e("rightwrapper")},_),b={prev:T(Ee,{disabled:e.disabled,currentPage:f.value,prevText:e.prevText,prevIcon:e.prevIcon,onClick:U}),jumper:T(De,{size:e.small?"small":"default"}),pager:T(sa,{currentPage:f.value,pageCount:m.value,pagerCount:e.pagerCount,onChange:j,disabled:e.disabled}),next:T(Me,{disabled:e.disabled,currentPage:f.value,pageCount:m.value,nextText:e.nextText,nextIcon:e.nextIcon,onClick:s}),sizes:T(je,{pageSize:d.value,pageSizes:e.pageSizes,popperClass:e.popperClass,disabled:e.disabled,size:e.small?"small":"default"}),slot:(g=(a=t==null?void 0:t.default)==null?void 0:a.call(t))!=null?g:null,total:T(Re,{total:P(e.total)?0:e.total})},w=e.layout.split(",").map(F=>F.trim());let O=!1;return w.forEach(F=>{if(F==="->"){O=!0;return}O?_.push(b[F]):v.push(b[F])}),i(v[0],l.is("first")),i(v[v.length-1],l.is("last")),O&&_.length>0&&(i(_[0],l.is("first")),i(_[_.length-1],l.is("last")),v.push(B)),T("div",{role:"pagination","aria-label":"pagination",class:[l.b(),l.is("background",e.background),{[l.m("small")]:e.small}]},v)}}});const ba=me(oa);export{ba as E};
