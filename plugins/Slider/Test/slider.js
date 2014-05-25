
(function(win) {


  /**
   * Slider object
   *
   * h1 => title
   * h2 => slide heading
   * content between h2 => slide content
   */
   var Slider = function() {

      // private

      var
      conf  = Config,
      numSlides = 0,
      currentSlide = 0,
      slideHash = [],

      getLeftMargin = function() {
        var el = $("#slide" + currentSlide);
        return ($(win).outerWidth() - el.outerWidth(true)) / 2;
      },

      setHorizontalSlide = function(hash) {

        hash = hash.substr(1, hash.length);

        for (var i = 0; i < slideHash.length; i++) {
          if(hash == slideHash[i]) {
            currentSlide = i;
            break;
          }
        };
        // alert(getLeftMargin());
        $("#slides").css("margin-left", getLeftMargin());
      },

      initHorizontal = function() {
        if(numSlides == -1)
          throw "no slides loaded";

        addCSS("horizontal.css");

        var e = null;
        var width = 0;
        for (var i = 0; i < numSlides; i++) {
          e = $('#slide'+i);
          width += e.outerWidth(true);
        };
        $("#slides").css("width",width);

        win.setTimeout(function() {
          setHorizontalSlide(win.location.hash);
        }, 100);
      },

      initVertical = function() {
        addCSS("vertical.css");
        // TODO slide script
      },

      slideLeft = function() {
        if(currentSlide == 0) return;
        currentSlide--;
        //$("#slides").css("margin-left", "-" + currentSlide + "00%");
        $("#slides").animate({
          marginLeft: "-" + ( (currentSlide * 100 * Config.width) )+ "%"
        });
        win.location.hash = slideHash[currentSlide];
      },

      slideRight = function() {
        if(currentSlide+1 == numSlides) return;
        currentSlide++;
        //$("#slides").css("margin-left", "-" + currentSlide + "00%");
        $("#slides").animate({
          marginLeft: "-" + ( (currentSlide * 100 * Config.width) ) + "%"
        });
        win.location.hash = slideHash[currentSlide];
      },

      addCSS = function (path) {
        $("<link/>", {
           rel: "stylesheet",
           type: "text/css",
           href: path
        }).appendTo("head");
      }

      initStructure = function() {
        var slides = $("<div id='slides'></div>");
        // var slides = $("body > div.section");
        var el = $("body > div.section").children();

        var addToSlide = false;
        var slide = null;

        el.each(function(i) {
          if(el[i].nodeName == "H2") {
            addToSlide = true;
            if(slides != null)
              slides.append(slide);
            slide = $("<div id='slide" + numSlides + "' class='slide'></div>");
            numSlides++;
            slideDiv = $("<div></div>");
            slide.append(slideDiv);
            slideDiv.append(el[i]);
            slideHash.push(el[i].id);
            el[i].id = "js_" + el[i].id;
          }
          if(addToSlide && numSlides != 0) {
            //alert("add to slide num:" + numSlides + " " + el[i].nodeName + " element");
            slideDiv.append(el[i]);
          }
        });
        if(slides != null) slides.append(slide);

        var controls = $("<div></div>");
        controls.append("<span id='arrow-left'>&lt;</span>");
        controls.append("<span id='arrow-right'>&gt;</span>");

        var wrapper = $("<div class='slides-wrapper'></div>")
        wrapper.append(slides);
        wrapper.append(controls);
        $("body > div.section").append(wrapper);
      },

      fireEvents = function() {
        var arrowLeft = $("#arrow-left");
        var arrowRight = $("#arrow-right");

        $(document).keydown(function(e){
          switch(e.keyCode){
            case 37:
              slideLeft();
            break;
            case 39:
              slideRight();
            break;
          }
          return false;
        });

        arrowLeft.click(slideLeft);
        arrowRight.click(slideRight);
      }


      // public

      return {

         version : "1.0",

         mode : function() {
            return Config.mode;
         },

         init : function() {
            initStructure();

            switch(this.mode()) {

              case Config.modes.HORIZONTAL:
                initHorizontal();
              break;

              case Config.modes.VERTICAL:
                initVertical();
              break;
            }
            fireEvents();
         }

      }
   };

   var slider = new Slider();
   win.Slider = slider;


   $(function(){
     slider.init();
   });

})(window);



