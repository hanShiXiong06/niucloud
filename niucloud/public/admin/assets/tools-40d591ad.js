import{w as r}from"./index-aae906bf.js";function a(e){return r.get("generator/generator",{params:e})}function n(e){return r.get(`generator/generator/${e}`)}function o(e){return r.post("generator/generator",e,{showErrorMessage:!0,showSuccessMessage:!0})}function s(e){return r.put(`generator/generator/${e.id}`,e,{showErrorMessage:!0,showSuccessMessage:!0})}function u(e){return r.delete(`generator/generator/${e}`,{showErrorMessage:!0,showSuccessMessage:!0})}function g(e){return r.post("generator/download",e)}function c(){return r.get("generator/table")}export{o as a,n as b,a as c,u as d,g as e,c as g,s as u};
