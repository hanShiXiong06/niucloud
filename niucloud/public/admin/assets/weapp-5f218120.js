import{x as t}from"./index-d5447f06.js";function s(){return t.get("weapp/config")}function n(e){return t.put("weapp/config",e,{showSuccessMessage:!0})}function a(){return t.get("weapp/template")}function r(e){return t.put("weapp/template/sync",e,{showSuccessMessage:!0})}function o(e){return t.post("weapp/version",e,{showSuccessMessage:!0})}function u(){return t.get("weapp/preview")}function i(e){return t.get("weapp/version",{params:e})}function c(e){return t.get(`weapp/upload/${e}`)}export{u as a,i as b,c,n as d,a as e,r as f,s as g,o as s};
