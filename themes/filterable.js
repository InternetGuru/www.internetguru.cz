(function(win){

  var Config = {}
  Config.initClass = "filterable";
  Config.close = "×";

  Filterable = function() {
    var
    that = this,
    initCfg = function(cfg) {
      if(typeof cfg === 'undefined') return;
      for(var attr in cfg) {
        if(!Config.hasOwnProperty(attr)) continue;
        Config[attr] = cfg[attr];
      }
    },
    initFilters = function() {
      var dls = document.querySelectorAll("." + Config.initClass);
      for(var i = 0; i < dls.length; i++) {
        var fDl = new FilterableDl(dls[i]);
        fDl.init();
      }
    }
    return {
      init : function(cfg) {
        initCfg(cfg);
        initFilters();
      }
    }
  }

  FilterableDl = function(dl) {
    var
    that = this,
    wrapper = dl,
    tags = [],
    rows = [],
    initStrucure = function() {
      var dds = dl.querySelectorAll("dd.kw");
      for(var i = 0; i < dds.length; i++) {
        var kws = dds[i].textContent.split(",").map(function(s) { return s.replace(/^\s\s*/, '').replace(/\s\s*$/, '') });
        dds[i].innerHTML = "";
        rows.push({ dd: dds[i], kws: kws });
        for(var j = 0; j < kws.length; j++) {
          var a = document.createElement("a");
          a.href = "#" + kws[j];
          a.textContent = kws[j];
          a.className = "tag";
          dds[i].appendChild(a);
          if(!(kws[j] in tags)) tags[kws[j]] = [];
          tags[kws[j]].push({a: a, dd: dds[i]});
        }
      }
      for(kw in tags) {
        if(tags[kw].length > 1) {
          for(i in tags[kw]) {
            // tags[kw][i].a.classList.add("multiple");
            tags[kw][i].a.addEventListener("click", doFilter, false);
          }
        } else {
          for(i in tags[kw]) { tags[kw][i].a.classList.add("hide"); }
        }
      }
    },
    clearFilter = function() {
      var dds = wrapper.getElementsByTagName("dd");
      for(var i = 0; i < dds.length; i++) { dds[i].className = "" }
      var dts = wrapper.getElementsByTagName("dt");
      for(var i = 0; i < dts.length; i++) { dts[i].className = "" }
      for(kw in tags) {
        for(i in tags[kw]) {
          deactivate(tags[kw][i].a);
        }
      }
    },
    hideRow = function(el) {
      var e = el;
      while(e.nodeName.toLowerCase() == "dd") {
        e.className = "hide";
        e = e.previousSibling;
      }
      e.previousSibling.className = "hide";
    },
    removeFilter = function(e) {
      clearFilter();
      deactivate(e.target);
    }
    deactivate = function(el) {
      el.classList.remove("active");
      el.addEventListener("click", doFilter, false);
      el.removeEventListener("click", removeFilter, false);
    }
    activate = function(el) {
      el.classList.add("active");
      el.removeEventListener("click", doFilter, false);
      el.addEventListener("click", removeFilter, false);
    }
    doFilter = function(e) {
      var value = e.target.textContent;
      clearFilter();
      for(i in rows) {
        var found = false;
        for(j in rows[i].kws) {
          if(rows[i].kws[j] != value) continue;
          found = true;
          break;
        }
        if(!found) {
          hideRow(rows[i].dd);
        }
      }
      for(kw in tags) {
        if(kw != value) continue;
        for(i in tags[kw]) {
          activate(tags[kw][i].a);
        }
      }
    }
    return {
      init : function() {
        initStrucure();
      }
    }
  }

  filterable = new Filterable();
  win.Filterable = filterable;
  filterable.init();

})(window);