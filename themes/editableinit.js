require(["IGCMS", "IGCMS.Editable"], function () {
  if (typeof IGCMS === "undefined") throw "IGCMS is not defined";
  IGCMS.Editable.init({
    unload_msg: "Obsah formuláře byl změněn"
  })
})
