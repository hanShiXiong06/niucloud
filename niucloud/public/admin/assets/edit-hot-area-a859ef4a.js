import{d as J,c as ie,r as I,R as ae,e as X,f as Y,g as u,h as K,u as c,A as B,B as V,y as w,x as L,j as O,F,z as P,H as R,bl as ne,bm as fe,Q}from"./base-d77b0726.js";/* empty css                *//* empty css                   *//* empty css                  */import"./el-form-item-4ed993c7.js";import{_ as re}from"./index.vue_vue_type_script_setup_true_lang-8d43c28e.js";import{_ as pe}from"./index.vue_vue_type_script_setup_true_lang-c86b2be1.js";import{d as N,v as q}from"./index-331c6de1.js";import{t as v}from"./index-ace71ef4.js";import{d as Z}from"./common-56ee0a80.js";import{a as G}from"./index-9e51ba8b.js";import{a as ee,E as te}from"./index-68c5ad54.js";import{E as de}from"./index-91bdda63.js";import{E as ce}from"./index-6a54cf26.js";import{_ as ue}from"./_plugin-vue_export-helper-c27b6911.js";import{_ as me}from"./index-3f78000a.js";import{u as he}from"./diy-9615aa7d.js";const _e=A=>(ne("data-v-7b0ed719"),A=A(),fe(),A),ge={key:0},ve={class:"text-primary p-[4px]"},xe={key:1},be={class:"flex"},ye=["id","onMousedown"],we=_e(()=>u("span",{class:"p-[4px]"},"|",-1)),ke=["onMousedown"],Te=["onMousedown"],Ce=["onMousedown"],Me=["onMousedown"],Ve={class:"mb-[10px] text-lg text-black"},He={class:"overflow-y-auto max-h-[300px]"},Xe={key:0,class:"mb-[16px]"},Ye={class:"flex items-center"},Le={class:"dialog-footer"},Se=J({__name:"index",props:{modelValue:{type:String,default:""}},emits:["update:modelValue"],setup(A,{expose:z,emit:b}){const j=A,S=ie({get(){return j.modelValue},set(x){b("update:modelValue",x)}}),g=I(!1),m=I(400),T=I(400),W=I(4),p=ae([]),U=()=>{let x=p.length%W.value*100,a=Math.floor(p.length/W.value)*100;a>=m.value&&(a=0,x=0),p.push({left:x,top:a,width:100,height:100,unit:"px",link:{name:""}})},$=(x,a)=>{let d=document.getElementById("box_"+a),o=x.clientX-d.offsetLeft,H=x.clientY-d.offsetTop;document.onmousemove=function(_){d.style.left=_.clientX-o+"px",d.style.top=_.clientY-H+"px",_.clientX-o<0&&(d.style.left=0),_.clientX-o>m.value-d.offsetWidth&&(d.style.left=m.value-d.offsetWidth+"px"),_.clientY-H<0&&(d.style.top=0),_.clientY-H>T.value-d.offsetHeight&&(d.style.top=T.value-d.offsetHeight+"px"),p[a].left=d.offsetLeft,p[a].top=d.offsetTop,p[a].width=d.offsetWidth,p[a].height=d.offsetHeight,p[a].unit="px"},document.onmouseup=function(_){document.onmousemove=null}},D=(x,a)=>{var d=x;d.stopPropagation();let o=document.getElementById("box_"+a),H=x.target.className;var _=o.offsetWidth,E=o.offsetHeight,k=d.clientX,r=d.clientY,f=o.offsetLeft,n=o.offsetTop,l=50,s=50;document.onmousemove=function(se){var h=se;if(H=="box1"){let e=_-(h.clientX-k),C=m.value,t=E-(h.clientY-r),M=T.value-n,i=f+(h.clientX-k),y=n+(h.clientY-r);e<l&&(e=l),e>C&&(e=C),t<s&&(t=s),t>M&&(t=M),f==0&&n==0?e==l&&t==s?(i=l,y=s):e==l&&t>s?i=l:e>l&&t==s&&(y=s):f==0&&n>0?e==l&&t==s||e==l&&t>s?(i=l,y=o.offsetTop):e>l&&t==s&&(y=o.offsetTop):f>0&&n==0?e==l&&t==s?(i=o.offsetLeft,y=o.offsetTop):e==l&&t>s?(i=o.offsetLeft,y=0):e>l&&t==s&&(y=o.offsetTop):f>0&&n>0&&(e==l&&t==s||e==l&&t>s?(i=o.offsetLeft,y=o.offsetTop):e>l&&t==s&&(y=o.offsetTop)),i<0&&(i=0,e=_-(h.clientX-k)+(f+(h.clientX-k))),y<0&&(y=0,t=n+(h.clientY-r)+(E-(h.clientY-r))),o.style.width=e+"px",o.style.height=t+"px",o.style.left=i+"px",o.style.top=y+"px"}else if(H=="box2"){let e=_+(h.clientX-k),C=m.value-f,t=E-(h.clientY-r),M=T.value-n,i=n+(h.clientY-r);e<l&&(e=l),e>C&&(e=C),t<s&&(t=s),t>M&&(t=M),f==0&&n==0?e==l&&t==s?i=s:e==l&&t>s||e>l&&t==s&&(i=s):f==0&&n>0?(e==l&&t==s||e==l&&t>s||e>l&&t==s)&&(i=o.offsetTop):f>0&&n==0?e==l&&t==s?i=o.offsetTop:e==l&&t>s?i=0:e>l&&t==s&&(i=o.offsetTop):f>0&&n>0&&(e==l&&t==s||e==l&&t>s||e>l&&t==s)&&(i=o.offsetTop),i<0&&(i=0,t=n+(h.clientY-r)+(E-(h.clientY-r))),o.style.width=e+"px",o.style.height=t+"px",o.style.top=i+"px"}else if(H=="box3"){let e=_-(h.clientX-k),C=m.value,t=E+(h.clientY-r),M=T.value-n,i=f+(h.clientX-k);e<l&&(e=l),e>C&&(e=C),t<s&&(t=s),t>M&&(t=M),f==0&&n==0||f==0&&n>0?(e==l&&t==s||e==l&&t>s)&&(i=l):f>0&&n==0?(e==l&&t==s||e==l&&t>s)&&(i=o.offsetLeft):f>0&&n>0&&(e==l&&t==s||e==l&&t>s)&&(i=o.offsetLeft),i<0&&(i=0,e=_-(h.clientX-k)+(f+(h.clientX-k))),o.style.width=e+"px",o.style.height=t+"px",o.style.left=i+"px"}else if(H=="box4"){let e=_+(h.clientX-k),C=m.value-f,t=E+(h.clientY-r),M=T.value-n;e<l&&(e=l),e>C&&(e=C),t<s&&(t=s),t>M&&(t=M),o.style.width=e+"px",o.style.height=t+"px"}p[a].left=o.offsetLeft,p[a].top=o.offsetTop,p[a].width=o.offsetWidth,p[a].height=o.offsetHeight,p[a].unit="px"},document.onmouseup=function(){document.onmousemove=null,document.onmouseup=null}},oe=()=>{if(!S.value.imageUrl){G({type:"warning",message:`${v("imageUrlTip")}`});return}Object.keys(S.value.heatMapData).length?p.splice(0,p.length,...S.value.heatMapData):(p.splice(0,p.length),U()),g.value=!0},le=()=>{var x=!0;for(let a=0;a<p.length;a++)if(!p[a].link.title){G({type:"warning",message:v("selectedHotArea")+(a+1)+v("hotAreaLink")}),x=!1;break}x&&(p.forEach((a,d)=>{var o=document.getElementById("box_"+d);a.width=parseFloat(o.offsetWidth/m.value*100).toFixed(2),a.height=parseFloat(o.offsetHeight/T.value*100).toFixed(2),a.left=parseFloat(o.offsetLeft/m.value*100).toFixed(2),a.top=parseFloat(o.offsetTop/T.value*100).toFixed(2),a.unit="%"}),S.value.heatMapData=p,g.value=!1)};return z({showDialog:g}),(x,a)=>{const d=pe,o=re,H=ee,_=de,E=te,k=ce;return X(),Y("div",null,[u("div",{onClick:oe,class:"cursor-pointer"},[K(x.$slots,"default",{},()=>[c(S).heatMapData.length?(X(),Y("div",ge,[B(V(c(v)("selected")),1),u("span",ve,V(c(S).heatMapData.length),1),B(V(c(v)("selectedAfterHotArea")),1)])):(X(),Y("div",xe,V(c(v)("clickSet")),1))],!0)]),w(k,{modelValue:g.value,"onUpdate:modelValue":a[1]||(a[1]=r=>g.value=r),title:c(v)("hotAreaSet"),width:"45%","close-on-press-escape":!1,"destroy-on-close":!0,"close-on-click-modal":!1},{footer:L(()=>[u("span",Le,[w(_,{onClick:a[0]||(a[0]=r=>g.value=!1)},{default:L(()=>[B(V(c(v)("cancel")),1)]),_:1}),w(_,{type:"primary",onClick:le},{default:L(()=>[B(V(c(v)("confirm")),1)]),_:1})])]),default:L(()=>[u("div",be,[u("div",{class:"content-box relative bg-cover bg-gray-100 border border-dashed border-gray-500",style:O({backgroundImage:"url("+c(Z)(c(S).imageUrl)+")",width:m.value+"px",height:T.value+"px"})},[(X(!0),Y(F,null,P(p,(r,f)=>(X(),Y("div",{id:"box_"+f,class:"area-box cursor-move border border-solid border-[#ccc] w-[100px] h-[100px] absolute top-0 left-0 select-none p-[5px]",style:O({left:r.left+r.unit,top:r.top+r.unit,width:r.width+r.unit,height:r.height+r.unit}),onMousedown:n=>$(n,f)},[u("span",null,V(f+1),1),r.link.title?(X(),Y(F,{key:0},[we,u("span",null,V(r.link.title),1)],64)):R("",!0),u("span",{class:"box1",onMousedown:N(n=>D(n,f),["stop"])},null,40,ke),u("span",{class:"box2",onMousedown:N(n=>D(n,f),["stop"])},null,40,Te),u("span",{class:"box3",onMousedown:N(n=>D(n,f),["stop"])},null,40,Ce),u("span",{class:"box4",onMousedown:N(n=>D(n,f),["stop"])},null,40,Me)],44,ye))),256))],4),w(E,{"label-width":"80px",class:"pl-[20px]"},{default:L(()=>[u("h3",Ve,V(c(v)("hotAreaManage")),1),u("div",He,[(X(!0),Y(F,null,P(p,(r,f)=>(X(),Y(F,null,[r?(X(),Y("div",Xe,[w(H,{label:c(v)("hotArea")+(f+1)},{default:L(()=>[u("div",Ye,[w(d,{modelValue:r.link,"onUpdate:modelValue":n=>r.link=n},null,8,["modelValue","onUpdate:modelValue"]),w(o,{class:"del cursor-pointer mx-[10px]",name:"element-CircleCloseFilled",color:"#bbb",size:"20px",onClick:n=>p.splice(f,1)},null,8,["onClick"])])]),_:2},1032,["label"])])):R("",!0)],64))),256))]),w(_,{type:"primary",plain:"",class:"ml-[80px]",onClick:U},{default:L(()=>[B(V(c(v)("addHotArea")),1)]),_:1})]),_:1})])]),_:1},8,["modelValue","title"])])}}});const Ee=ue(Se,[["__scopeId","data-v-7b0ed719"]]),Ae={class:"content-wrap"},We={class:"edit-attr-item-wrap"},$e={class:"mb-[10px]"},Be={ref:"imageBoxRef"},Fe={class:"item-wrap p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]"},Ue={class:"style-wrap"},De=J({__name:"edit-hot-area",setup(A,{expose:z}){const b=he();b.editComponent.ignore=[],b.editComponent.verify=g=>{var m={code:!0,message:""};return b.value[g].imageUrl===""&&(m.code=!1,m.message=v("imageUrlTip")),m};const j=g=>{S()},S=()=>{let g=new Image;g.src=Z(b.editComponent.imageUrl),g.onload=async()=>{b.editComponent.imgWidth=g.width,b.editComponent.imgHeight=g.height}};return z({}),(g,m)=>{const T=me,W=ee,p=Ee,U=te;return X(),Y(F,null,[Q(u("div",Ae,[u("div",We,[u("h3",$e,V(c(v)("hotAreaSet")),1),w(U,{"label-width":"80px",class:"px-[10px]"},{default:L(()=>[u("div",Be,[u("div",Fe,[w(W,{label:c(v)("hotAreaBackground")},{default:L(()=>[w(T,{modelValue:c(b).editComponent.imageUrl,"onUpdate:modelValue":m[0]||(m[0]=$=>c(b).editComponent.imageUrl=$),limit:1,onChange:j},null,8,["modelValue"])]),_:1},8,["label"]),w(W,{label:c(v)("hotAreaSet")},{default:L(()=>[w(p,{modelValue:c(b).editComponent,"onUpdate:modelValue":m[1]||(m[1]=$=>c(b).editComponent=$)},null,8,["modelValue"])]),_:1},8,["label"])])],512)]),_:1})])],512),[[q,c(b).editTab=="content"]]),Q(u("div",Ue,[K(g.$slots,"style")],512),[[q,c(b).editTab=="style"]])],64)}}}),st=Object.freeze(Object.defineProperty({__proto__:null,default:De},Symbol.toStringTag,{value:"Module"}));export{st as _};