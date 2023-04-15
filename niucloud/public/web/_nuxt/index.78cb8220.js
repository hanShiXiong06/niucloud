import{Y as rA,aq as wA,N as iA,Q as CA,ar as cA,r as C,e as D,u as A,Z as q,a0 as uA,as as VA,at as RA,au as jA,an as FA,a as Y,V as dA,o as R,i as k,j as u,c as sA,w as M,y as X,D as Z,a2 as O,l as g,a3 as aA,a5 as NA,z as J,ad as oA,v as H,a6 as SA,ac as vA,a1 as pA,F as ZA,x as MA,t as gA,af as mA,av as kA,f as DA,aw as TA,ax as UA,aj as LA,ay as WA,az as z,E as qA,G as OA}from"./entry.08c6ab45.js";import{f as HA}from"./vnode.7865e18e.js";import{t as nA}from"./throttle.d003d777.js";import{_ as PA}from"./nuxt-link.c2db7583.js";import{_ as GA}from"./_plugin-vue_export-helper.c27b6911.js";import"./debounce.3db11f73.js";const XA=(a,Q,l)=>HA(a.subTree).filter(c=>{var e;return wA(c)&&((e=c.type)==null?void 0:e.name)===Q&&!!c.component}).map(c=>c.component.uid).map(c=>l[c]).filter(c=>!!c),JA=(a,Q)=>{const l={},t=rA([]);return{children:t,addChild:e=>{l[e.uid]=e,t.value=XA(a,Q,l)},removeChild:e=>{delete l[e],t.value=t.value.filter(f=>f.uid!==e)}}},YA=iA({initialIndex:{type:Number,default:0},height:{type:String,default:""},trigger:{type:String,values:["hover","click"],default:"hover"},autoplay:{type:Boolean,default:!0},interval:{type:Number,default:3e3},indicatorPosition:{type:String,values:["","none","outside"],default:""},arrow:{type:String,values:["always","hover","never"],default:"hover"},type:{type:String,values:["","card"],default:""},loop:{type:Boolean,default:!0},direction:{type:String,values:["horizontal","vertical"],default:"horizontal"},pauseOnHover:{type:Boolean,default:!0}}),zA={change:(a,Q)=>[a,Q].every(CA)},EA=Symbol("carouselContextKey"),lA=300,KA=(a,Q,l)=>{const{children:t,addChild:E,removeChild:c}=JA(cA(),"ElCarouselItem"),e=C(-1),f=C(null),h=C(!1),B=C(),y=D(()=>a.arrow!=="never"&&!A(I)),V=D(()=>t.value.some(s=>s.props.label.toString().length>0)),x=D(()=>a.type==="card"),I=D(()=>a.direction==="vertical"),j=nA(s=>{o(s)},lA,{trailing:!0}),w=nA(s=>{T(s)},lA);function b(){f.value&&(clearInterval(f.value),f.value=null)}function F(){a.interval<=0||!a.autoplay||f.value||(f.value=setInterval(()=>U(),a.interval))}const U=()=>{e.value<t.value.length-1?e.value=e.value+1:a.loop&&(e.value=0)};function o(s){if(FA(s)){const L=t.value.filter(G=>G.props.name===s);L.length>0&&(s=t.value.indexOf(L[0]))}if(s=Number(s),Number.isNaN(s)||s!==Math.floor(s))return;const m=t.value.length,S=e.value;s<0?e.value=a.loop?m-1:0:s>=m?e.value=a.loop?0:m-1:e.value=s,S===e.value&&r(S),$()}function r(s){t.value.forEach((m,S)=>{m.translateItem(S,e.value,s)})}function n(s,m){var S,L,G,AA;const W=A(t),eA=W.length;if(eA===0||!s.states.inStage)return!1;const xA=m+1,QA=m-1,tA=eA-1,hA=W[tA].states.active,bA=W[0].states.active,yA=(L=(S=W[xA])==null?void 0:S.states)==null?void 0:L.active,IA=(AA=(G=W[QA])==null?void 0:G.states)==null?void 0:AA.active;return m===tA&&bA||yA?"left":m===0&&hA||IA?"right":!1}function v(){h.value=!0,a.pauseOnHover&&b()}function N(){h.value=!1,F()}function d(s){A(I)||t.value.forEach((m,S)=>{s===n(m,S)&&(m.states.hover=!0)})}function i(){A(I)||t.value.forEach(s=>{s.states.hover=!1})}function p(s){e.value=s}function T(s){a.trigger==="hover"&&s!==e.value&&(e.value=s)}function K(){o(e.value-1)}function BA(){o(e.value+1)}function $(){b(),F()}q(()=>e.value,(s,m)=>{r(m),m>-1&&Q("change",s,m)}),q(()=>a.autoplay,s=>{s?F():b()}),q(()=>a.loop,()=>{o(e.value)}),q(()=>a.interval,()=>{$()}),q(()=>t.value,()=>{t.value.length>0&&o(a.initialIndex)});const _=rA();return uA(()=>{_.value=VA(B.value,()=>{r()}),F()}),RA(()=>{b(),B.value&&_.value&&_.value.stop()}),jA(EA,{root:B,isCardType:x,isVertical:I,items:t,loop:a.loop,addItem:E,removeItem:c,setActiveItem:o}),{root:B,activeIndex:e,arrowDisplay:y,hasLabel:V,hover:h,isCardType:x,items:t,handleButtonEnter:d,handleButtonLeave:i,handleIndicatorClick:p,handleMouseEnter:v,handleMouseLeave:N,setActiveItem:o,prev:K,next:BA,throttledArrowClick:j,throttledIndicatorHover:w}},_A=["onMouseenter","onClick"],$A={key:0},Ae="ElCarousel",ee=Y({name:Ae}),te=Y({...ee,props:YA,emits:zA,setup(a,{expose:Q,emit:l}){const t=a,{root:E,activeIndex:c,arrowDisplay:e,hasLabel:f,hover:h,isCardType:B,items:y,handleButtonEnter:V,handleButtonLeave:x,handleIndicatorClick:I,handleMouseEnter:j,handleMouseLeave:w,setActiveItem:b,prev:F,next:U,throttledArrowClick:o,throttledIndicatorHover:r}=KA(t,l),n=dA("carousel"),v=D(()=>{const d=[n.b(),n.m(t.direction)];return A(B)&&d.push(n.m("card")),d}),N=D(()=>{const d=[n.e("indicators"),n.em("indicators",t.direction)];return A(f)&&d.push(n.em("indicators","labels")),(t.indicatorPosition==="outside"||A(B))&&d.push(n.em("indicators","outside")),d});return Q({setActiveItem:b,prev:F,next:U}),(d,i)=>(R(),k("div",{ref_key:"root",ref:E,class:Z(A(v)),onMouseenter:i[6]||(i[6]=O((...p)=>A(j)&&A(j)(...p),["stop"])),onMouseleave:i[7]||(i[7]=O((...p)=>A(w)&&A(w)(...p),["stop"]))},[u("div",{class:Z(A(n).e("container")),style:pA({height:d.height})},[A(e)?(R(),sA(oA,{key:0,name:"carousel-arrow-left",persisted:""},{default:M(()=>[X(u("button",{type:"button",class:Z([A(n).e("arrow"),A(n).em("arrow","left")]),onMouseenter:i[0]||(i[0]=p=>A(V)("left")),onMouseleave:i[1]||(i[1]=(...p)=>A(x)&&A(x)(...p)),onClick:i[2]||(i[2]=O(p=>A(o)(A(c)-1),["stop"]))},[g(A(aA),null,{default:M(()=>[g(A(NA))]),_:1})],34),[[J,(d.arrow==="always"||A(h))&&(t.loop||A(c)>0)]])]),_:1})):H("v-if",!0),A(e)?(R(),sA(oA,{key:1,name:"carousel-arrow-right",persisted:""},{default:M(()=>[X(u("button",{type:"button",class:Z([A(n).e("arrow"),A(n).em("arrow","right")]),onMouseenter:i[3]||(i[3]=p=>A(V)("right")),onMouseleave:i[4]||(i[4]=(...p)=>A(x)&&A(x)(...p)),onClick:i[5]||(i[5]=O(p=>A(o)(A(c)+1),["stop"]))},[g(A(aA),null,{default:M(()=>[g(A(SA))]),_:1})],34),[[J,(d.arrow==="always"||A(h))&&(t.loop||A(c)<A(y).length-1)]])]),_:1})):H("v-if",!0),vA(d.$slots,"default")],6),d.indicatorPosition!=="none"?(R(),k("ul",{key:0,class:Z(A(N))},[(R(!0),k(ZA,null,MA(A(y),(p,T)=>(R(),k("li",{key:T,class:Z([A(n).e("indicator"),A(n).em("indicator",d.direction),A(n).is("active",T===A(c))]),onMouseenter:K=>A(r)(T),onClick:O(K=>A(I)(T),["stop"])},[u("button",{class:Z(A(n).e("button"))},[A(f)?(R(),k("span",$A,gA(p.props.label),1)):H("v-if",!0)],2)],42,_A))),128))],2)):H("v-if",!0)],34))}});var se=mA(te,[["__file","/home/runner/work/element-plus/element-plus/packages/components/carousel/src/carousel.vue"]]);const ae=iA({name:{type:String,default:""},label:{type:[String,Number],default:""}}),oe=(a,Q)=>{const l=kA(EA),t=cA(),E=.83,c=C(!1),e=C(0),f=C(1),h=C(!1),B=C(!1),y=C(!1),V=C(!1),{isCardType:x,isVertical:I}=l;function j(o,r,n){const v=n-1,N=r-1,d=r+1,i=n/2;return r===0&&o===v?-1:r===v&&o===0?n:o<N&&r-o>=i?n+1:o>d&&o-r>=i?-2:o}function w(o,r){var n;const v=((n=l.root.value)==null?void 0:n.offsetWidth)||0;return y.value?v*((2-E)*(o-r)+1)/4:o<r?-(1+E)*v/4:(3+E)*v/4}function b(o,r,n){const v=l.root.value;return v?((n?v.offsetHeight:v.offsetWidth)||0)*(o-r):0}const F=(o,r,n)=>{var v;const N=A(x),d=(v=l.items.value.length)!=null?v:Number.NaN,i=o===r;!N&&!UA(n)&&(V.value=i||o===n),!i&&d>2&&l.loop&&(o=j(o,r,d));const p=A(I);h.value=i,N?(y.value=Math.round(Math.abs(o-r))<=1,e.value=w(o,r),f.value=A(h)?1:E):e.value=b(o,r,p),B.value=!0};function U(){if(l&&A(x)){const o=l.items.value.findIndex(({uid:r})=>r===t.uid);l.setActiveItem(o)}}return uA(()=>{l.addItem({props:a,states:DA({hover:c,translate:e,scale:f,active:h,ready:B,inStage:y,animating:V}),uid:t.uid,translateItem:F})}),TA(()=>{l.removeItem(t.uid)}),{active:h,animating:V,hover:c,inStage:y,isVertical:I,translate:e,isCardType:x,scale:f,ready:B,handleItemClick:U}},ne=Y({name:"ElCarouselItem"}),le=Y({...ne,props:ae,setup(a){const Q=a,l=dA("carousel"),{active:t,animating:E,hover:c,inStage:e,isVertical:f,translate:h,isCardType:B,scale:y,ready:V,handleItemClick:x}=oe(Q),I=D(()=>{const w=`${`translate${A(f)?"Y":"X"}`}(${A(h)}px)`,b=`scale(${A(y)})`;return{transform:[w,b].join(" ")}});return(j,w)=>X((R(),k("div",{class:Z([A(l).e("item"),A(l).is("active",A(t)),A(l).is("in-stage",A(e)),A(l).is("hover",A(c)),A(l).is("animating",A(E)),{[A(l).em("item","card")]:A(B)}]),style:pA(A(I)),onClick:w[0]||(w[0]=(...b)=>A(x)&&A(x)(...b))},[A(B)?X((R(),k("div",{key:0,class:Z(A(l).e("mask"))},null,2)),[[J,!A(t)]]):H("v-if",!0),vA(j.$slots,"default")],6)),[[J,A(V)]])}});var fA=mA(le,[["__file","/home/runner/work/element-plus/element-plus/packages/components/carousel/src/carousel-item.vue"]]);const re=LA(se,{CarouselItem:fA}),ie=WA(fA);const ce="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAAAAAAAAAAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADb/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAAtAC0DAREAAhEBAxEB/8QAHQAAAQMFAQAAAAAAAAAAAAAACAQGCQACAwUHCv/EADoQAAEDAwEGAQkECwAAAAAAAAQCAwUBBgcIAAkSExQVERYXGSI4V4e31iEjU6ckJTFodZWWl7XV5f/EAB0BAAEEAwEBAAAAAAAAAAAAAAgEBQYHAAIDAQn/xABGEQABBAECBAIDCQwKAwAAAAACAQMEBQYABxESExQIIRUiNxYXGCNWhYa1tiQxQVJTlJWlprTV1jI4UWZnc3STltTT4+T/2gAMAwEAAhEDEQA/APcdqIzVF6eMO3hmGahD7ijbP8n+phosgcU4zygumEtdnkPl0qO305E20U7zKesyw4hHrqTtO9tMFl7l5tS4TBnx6yVdekulOltOvR2PRtRPtj6jbKo4XVbgGyHKvkbgkvqouo7lmRMYnQT7+RGdlswO1547JADrndTY0IeUnPUTkOSJlx++IqieapqPcTeuCnijHA6Vs5GBGjslhmCCNkClikNpeHJGIZjltPjvtLQ6y80tbbra0rQpSVUrUk3vB67HddjyN3cBYfYcNl5l50mnWXWiUHGnWzlibbjZiQGBihASKJIioqaqpvfAHQB1rCMmcbcAXG3G20MHANEIDAxaUSAhVCEhVUJFRUVUXSj0qP7puev5d/y9ufwQ/wDGLb384/8As1v79n9xso/2f/TrbY43r+IrtyPFY7vewL0xI5Kl0jazl3vg1AiZR9KegHnBmm2TowYxxaGOveZqOIt5l4zkBVeLYR5P4PM0psYmZLQZHR5kMRlZSV9K1ISTMiNqvcO17pm4xLdYESc7cDRx4QMGOo+gMudqjfGhnW7FTZVdjRK+50Vkzya6TDxInTCSAoLjIOKqCrpDytqQk5yt8zgSotuIdQh1paHGnEJcbcbVRaHELpRSFoWmtUqQpNaKSpNa0VStK0rWldhDISAiAxIDElEhJFEhIV4EJCvBUJFRUVFRFRU4L56u1FQkQhVFEkRUVF4oqKnFFRfwoqeaLq/bXXugN3m3sP5t+G3zdsHYhvCn7fMD+lH2MyLVY7yezfI/mf6+q9PfEeVse4c0e6cbvyZdAFo235lMLxndpFstwfrzMewVRRuEIYp7jeoy5w15XBThrxKp408WDM8PyXN969z6XFaiRdWnu7zqV2cUmRd7djJbBHXeL7rIcoc48fX4+acEXTlQ3lVj+A4jPuZrUCH7nMdZ67yGodVyqi8gcGwMuJcq8PV4eXmunZj3V3puyvdcfY+PMsW9dN2SrZz0dCADzLZRTcaERInLbUXGDD0oOCKQQvjeTWqGlUTRSq0TVoyXZfdDD6eTf5Lh1lUU8Mo4SZ8hyCTLJSpDcWOhIzLdc4uyHm2h5QX1jTjwTiqLqrPMQvJzVbU3sSbOfRwmozQyEM0ZbJ5xUVxkB9RsCNeJJ5CvDivlqGO/IvTZcWtDWTa+pW5I20IGdBjmbSuYpkxUlCXUOmHdHkIV4EQpxBLA9XOpGeSkQ4NTopFFUWmqDlx6ZulWbF7IW21lXKurGvfkHc1TRsJFn1DizQcjTgfeZEmnHUDpOtqr0d9AeaVFFUIerNnEJe4e4ELMJbMCLJbZGDMMXOtGmgjBC7GJsDVDEePOBcG3W1UD4oqcOt6DNZBdkZJB0jXteYGWLTekEwWHMp2+5IFJ5dUuLjICSbkmGJCkS8KlDMfQltBFuPtdrX1UYsZ4CGeIfZBm/wAWkbz0FHIw24bjLYZviNkMZolPiIy7GKcVxyMsxt1SOSrRK1aNH3aIzLR1uQ+7Y7gHW3DeCWNi1ewSdSLj93FJ404easxXReAXegQcBaQkQ4hj0PjGFAmZ0Nvn7oltAbvNvYfzb8Nvm7YOxDeFP2+YH9KPsZkWqx3k9m+R/M/19V6z23L4FhdEWnE3UYxaBGPPM/g9qrd7Qzc7Dd6Xj2E7aroXAjk9VSiX+Q9yPFulV+C08X26WkLcSfv1uexti5dNZL7tc+PmoZxV87sByWf3KdwL8deiqq31A6nAl5fVXh5bQ38Yj7cYi5lowDqvQGNjwsY6SY/cLVRukvTJtxOfyLlLl8vPzTjrnNn553X+Pp8O6rGPwdaNyx6CmwZ63bGpEywjZoroRiBzwreZIZSUI++M/RDiaOsOuNL8ULVSsnu9vPFjklc/T5BHz65qpJMlIrrO/wC8hvFHeB9gnY79kbRq0+2262pCvI4AmnAkRdNFfk+zFVKbnVjuNwJjSGjUqJXIw+2jgE24gOtxRMUNsiAkRfMSVF8l0sKyluycs3u09JM4MvW/LzlggaGythsyEzOy5zjIATb559v1cfIeXVgdC33v2UQmqqUp9nBnEvFZh1CYRTz+ix6jhPyFYiZCcaDXwo4uSHybjsWKC22Ao44Qth9/mVEVV10O62bvbISeHGrGzsH22+q/WA7IkyHVFptDdciqRGSqIIpF/YnFETQ9ah8U4zxVrx0OhY1sK0rDDl5s0qUGtOBjoJiQJGlhmR3zG44dhBDrLTi22lu0UpCFqSmtKVrTaydtMwyrL/Dzv7IynIrnIX4UBhmI7cWEmwcjNOwnTcbYKS44rYGYiRiCohEiKvFUTUUyyjpqTc3bZunq4NY2/JcN8IMZqMLphIAQJxGhFDIRVUFS4qiKqJqarYE9EVoDd5t7D+bfht83bB2Ibwp+3zA/pR9jMi1WO8ns3yP5n+vqvWKIltP8Noc04m6lE2+vHNMRYOb8LkipOZju+Lx5Ddsr0cSGcXz+GhHLd5HLRSquNaeKnjvNh7jzt/tz2NrVshyf3aZ+fGrmRIMn0eOSzu6+OmPx2enxVvmDqcxeXKK8F4asP4tH22xFzMEirUegcbH7sYekNdytVG6PxbDbrnN5FwLl4J58VTjofPOHufvwMQ/2+vr6Z2sn3N+Nb8pmn/I8f/imor6W2D/Fof0VZ/8AT057JvzdTFXha41kM4rpeL8/Es2tUKxb0GMpcDhzCIiopBFutMMEddVjkuvOtttucK1rSmla0ab7H/GA1SW7t85l60jdbNO3R/IKJ1ha0I7hTes01ZG44126Oc4ABGY8RESVURVtbZ7IHYQgrRpEsClMDC6dbYg53ROCjHIZxBAD6qjykRIIrwVVRPPWo1i+33oM/ikp/mhNluyX9XTxDf6SL+4PaT7ge1HbD/Pe/eW9S1bBtq9dAbvNvYfzb8Nvm7YOxDeFP2+YH9KPsZkWqx3k9m+R/M/19V6b2JMo6O7u0tYDsDLuUNOs2zDYgxKxL2ffV/47fVFXBC2LChvjyUJMTNHQZaLKSSK+OUO0WGQl5h1DbqVpo55liW9lLu3uLkeF4nuZAOdmuZOQrrH8cyZtJlbOyCc+25FnwoPJIhy2VaebcacNl9tQcAiBUVUlFdYBPwrF6u+usSkjHoKIZECztKklYlR6yM2YPRpEjmafYNDAwMBNs0ISRCRU0o7Futfx9E39R4d/2m3L0j4t/wAnvx+jM2/6mt+22V/G25/O8f8A/NpfFDbseDk4+ZhpXRhGS0UYNIxkiDdOIBjADg3kPilikNSqXGCB320OsutqSttxKVJrStKV2Ty3fFbPiyYM2HvnLhzGHY0qLIqM0dYkR3wJt5l5s4ai4062RAYEiiQqqKiouurAbNRnmpEd/bxl9hwHWXm5tADjTrZIYOAYvoomBIhCSKioqIqaFvUhkvHGSteehovHWQLJv4SLmjhpMqyrrgrqHjiCJcZ1hg56CPPbEefaQtxlohTa3EIUtCVJTWtLb2vxXJ8W8PG/zOTY5fY49LgMOxWr2nsKh2S03CdBxyO3YR45vNtmQiZtoQiSoJKiqiahOX3FRcbnbauVNrW2gMyXAeOunRZwMmcgCEXSjOuo2RIiqImqKqIqoiompoNgY0Q+mHk7GNj5jsebxvkiE8o7LuPtveYbuUvEdZ2iXAno79YwJ8XKj9PKxYJX6KcxzuRyH+aM68y5IsUyu/wi/gZRi8/0Ze1nddjO7WFN6HewpNfJ+5rCNLhu9WHLkM/HR3OTqdRvkdADFruaatyCtk1FvG7uul9HuI/WkR+p28hqU18bFdZfDkfZaP1HR5uXlLmAiFRJ9GTof9yX5k5d+vtrk+FZv58vP2Xwz+XdQX3m9t/k5+uL7+Kar0ZOh/3JfmTl36+2z4Vm/ny8/ZfDP5d1nvN7b/Jz9cX38U1XoydD/uS/MnLv19tnwrN/Pl5+y+Gfy7rPeb23+Tn64vv4pp12NoA0j42u+378srEvZbrtaSZloGV8vMmSPQyA/FySOhlrzPjSuDiV90YGQwrx9ZpXhTZnyDxG7zZRS2WPXuZd9T28VyFYQ/c9isXuIznDnb7iHRx5TXNwT12H2nE/AaaXVu1uCVE+LZ11F286E8L8V/0ncvdJ0OPKfSfsHWT4cf6LjZiv4RXRj7UjqwNf/9k=",ue="data:image/jpeg;base64,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",de=""+new URL("community.f4aea720.jpg",import.meta.url).href,ve=""+new URL("wx.6a078824.jpg",import.meta.url).href;const pe={},P=a=>(qA("data-v-9be35501"),a=a(),OA(),a),me={class:"w-full"},Ee=P(()=>u("div",{class:"h-full index-carousel"},null,-1)),fe={class:"word main-container pb-[200px] pt-[60px]"},Be=P(()=>u("div",{class:"word-title"},[u("p",{class:"text-[40px] text-[#666] font-bold text-center mb-[20px]"},"Niucloud文档管理"),u("p",{class:"text-[16px] text-[#666] text-center"},"无论是快速上手，还是深入了解NIUCLOUD，这里都是最好的地点")],-1)),xe={class:"flex justify-between mt-[100px]"},Qe={class:"w-[280px]"},he=z('<div class="flex items-center" data-v-9be35501><div class="w-[30px] h-[30px] mr-[10px]" data-v-9be35501><img src="'+ce+'" data-v-9be35501></div><p class="text-[20px] text-[#666] font-bold" data-v-9be35501>官方教程</p></div><p class="text-[14px] w-[280px] h-[100px] text-[#666666] leading-[22px] mt-[30px] mb-[20px]" data-v-9be35501> 详尽细致的逐步官方教程，帮助您系统全面的接触NIUCLOUD，建议在使用前阅读。</p>',2),be=P(()=>u("div",{class:"flex justify-between items-center w-[280px] h-[40px] leading-[40px] rounded-[5px] border-[1px] border-[solid] border-[#508BFE]"},[u("span",{class:"block ml-[20px] text-[14px] text-[#333]"},"前往教程"),u("span",{class:"block mr-[20px] text-[24px] text-[#333]"},"→")],-1)),ye={class:"w-[280px]"},Ie=z('<div class="flex items-center" data-v-9be35501><div class="w-[30px] h-[30px] mr-[10px]" data-v-9be35501><img src="'+ue+'" data-v-9be35501></div><p class="text-[20px] text-[#666] font-bold" data-v-9be35501>API文档</p></div><p class="text-[14px] w-[280px] h-[100px] text-[#666666] leading-[22px] mt-[30px] mb-[20px]" data-v-9be35501> 您可以通过API文档了解niucloud的正确使用方法，也可以更加深入地理解niucloud的运行逻辑。</p>',2),we=P(()=>u("div",{class:"flex justify-between items-center w-[280px] h-[40px] leading-[40px] rounded-[5px] border-[1px] border-[solid] border-[#508BFE]"},[u("span",{class:"block ml-[20px] text-[14px] text-[#333]"},"前往API文档"),u("span",{class:"block mr-[20px] text-[24px] text-[#333]"},"→")],-1)),Ce={class:"w-[280px]"},Ve=z('<div class="flex items-center" data-v-9be35501><div class="w-[30px] h-[30px] mr-[10px]" data-v-9be35501><img src="'+de+'" data-v-9be35501></div><p class="text-[20px] text-[#666] font-bold" data-v-9be35501>问答社区</p></div><p class="text-[14px] w-[280px] h-[100px] text-[#666666] leading-[22px] mt-[30px] mb-[20px]" data-v-9be35501> 便捷地浏览其它用户关于niucloud的问题，并从解答中获取niucloud的使用方法，当然您可以进行提问。</p>',2),Re=P(()=>u("div",{class:"flex justify-between items-center w-[280px] h-[40px] leading-[40px] rounded-[5px] border-[1px] border-[solid] border-[#508BFE]"},[u("span",{class:"block ml-[20px] text-[14px] text-[#333]"},"前往问答社区"),u("span",{class:"block mr-[20px] text-[24px] text-[#333]"},"→")],-1)),je=z('<div class="w-[280px]" data-v-9be35501><div class="flex items-center" data-v-9be35501><div class="w-[30px] h-[30px] mr-[10px]" data-v-9be35501><img src="'+ve+'" data-v-9be35501></div><p class="text-[20px] text-[#666] font-bold" data-v-9be35501>关注公众号</p></div><p class="text-[14px] w-[280px] h-[100px] text-[#666666] leading-[22px] mt-[30px] mb-[20px]" data-v-9be35501> 您可以扫描页面底部的二维码来关注我们的官方公众号，获得一手咨询及使用技巧。</p></div>',1);function Fe(a,Q){const l=ie,t=re,E=PA;return R(),k("div",me,[g(t,{height:"500px",arrow:"never"},{default:M(()=>[g(l,null,{default:M(()=>[Ee]),_:1})]),_:1}),u("div",fe,[Be,u("div",xe,[u("div",Qe,[he,g(E,{to:"https://www.kancloud.cn/cui18734824089/niucloud-admin-develop/3148343",target:"_blank"},{default:M(()=>[be]),_:1})]),u("div",ye,[Ie,g(E,{to:"https://www.niucloud.com/apidoc.html",target:"_blank"},{default:M(()=>[we]),_:1})]),u("div",Ce,[Ve,g(E,null,{default:M(()=>[Re]),_:1})]),je])])])}const De=GA(pe,[["render",Fe],["__scopeId","data-v-9be35501"]]);export{De as default};
