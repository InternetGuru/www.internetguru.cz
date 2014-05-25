
(function(win) {


  /**
   * Slider object
   *
   * h1 => title
   * h2 => slide heading
   * content between h2 => slide content
   *
   * TODO set all styles by jQuery css()
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

      setSlide = function(hash) {

        $("#slide" + currentSlide).removeClass("active");

        hash = hash.substr(1, hash.length);

        for (var i = 0; i < slideHash.length; i++) {
          if(hash == slideHash[i]) {
            currentSlide = i;
            break;
          }
        };

        slideHorizontalAnim(false);
        $("#slide" + currentSlide).addClass("active");
      },

      initSlides = function() {
        if(numSlides == -1)
          throw "no slides loaded";

        addCSS("horizontal.css");

        var e = null;
        var width = 0;
        for (var i = 0; i < numSlides; i++) {
          e = $('#slide'+i);
          width += e.outerWidth(true);
        }
        $("#slides").css("width",width);
        win.setTimeout(function(){
          setSlide(win.location.hash);
        }, 100);
      },

      slideLeft = function() {
        if(currentSlide == 0) return;
        $("#slide" + currentSlide).removeClass("active");
        currentSlide--;
        slideHorizontalAnim();
        $("#slide" + currentSlide).addClass("active");
      },

      slideRight = function() {
        if(currentSlide+1 == numSlides) return;
        $("#slide" + currentSlide).removeClass("active");
        currentSlide++;
        slideHorizontalAnim();
        $("#slide" + currentSlide).addClass("active");
      },

      slideHorizontalAnim = function(animation) {

        if(typeof(animation)==='undefined') animation = true;

        var marginLeft = getLeftMargin();
        for (var i = 0; i < currentSlide; i++) {
          marginLeft -= $("#slide"+i).outerWidth(true);
        }
        $("#slides").clearQueue();
        if(animation){
          $("#slides").animate({
            marginLeft:  marginLeft + "px"
          }, Config.animationSpeed);
        } else {
          $("#slides").css("margin-left", marginLeft + "px");
        }
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

        var controls = $("<div id='slider-controll'></div>");
        controls.append("<span id='arrow-left'>" + Config.leftArrow + "</span>");
        controls.append("<span id='arrow-right'>" + Config.rightArrow + "</span>");
        if(Config.arrowsPrepend)
          $(Config.arrowsLocation).prepend(controls);
        else
          $(Config.arrowsLocation).append(controls);

        $("body > div.section").append(slides);
      },

      fireEvents = function() {
        var arrowLeft = $("#arrow-left");
        var arrowRight = $("#arrow-right");

        $(document).keydown(function(e){
          switch(e.keyCode){
            case 37:
            // case 40:
              slideLeft();
            break;
            case 39:
            // case 38:
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
            initSlides();
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



