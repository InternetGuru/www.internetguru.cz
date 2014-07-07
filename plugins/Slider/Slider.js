
(function(win) {

  var Config = {}

  // default mode
  Config.animationSpeed = 500; // ms
  Config.leftArrow = "&lt;";
  Config.rightArrow = "&gt;";
  Config.arrowsLocation = "body"; // selector
  Config.arrowsPrepend = false; // prepend (true) or append (false)
  Config.styleSheet = "slider.css";


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

      isInt = function(n) {
         return typeof n === 'number' && parseFloat(n) == parseInt(n, 10) && !isNaN(n);
      },

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

        var e = null;
        var width = 0;
        for (var i = 0; i < numSlides; i++) {
          e = $('#slide'+i);
          width += e.outerWidth(true);
        }

        addCSS(Config.styleSheet);

        $(".slide").css("max-width",$(win).outerWidth(true));
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

        var controls = $("<div class='slider-controll'></div>");
        controls.append("<span class='slider-arrow-left'>" + Config.leftArrow + "</span>");
        controls.append("<span class='slider-arrow-right'>" + Config.rightArrow + "</span>");

        var slider = $("<div id='slider'></div>");
        slider.append(slides);
        $("body > div.section").append(slider);

        if(Config.arrowsPrepend)
          $(Config.arrowsLocation).prepend(controls);
        else
          $(Config.arrowsLocation).append(controls);

      },

      fireEvents = function() {
        var arrowLeft = $(".slider-arrow-left");
        var arrowRight = $(".slider-arrow-right");

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
        });

        arrowLeft.click(slideLeft);
        arrowRight.click(slideRight);

        //Init touch swipe
        $("#slider").swipe( {
          triggerOnTouchEnd : true,
          swipeStatus : swipeStatus,
          allowPageScroll:"vertical"
        });
      }

      swipeStatus = function(event, phase, direction, distance, fingers){
        switch(phase) {
          case "start":
            // $("#slider").css("cursor", "-webkit-grabbing");
          break;
          case "move":
          break;
          case "cancel":

          break;
          case "end":
            // $("#slider").css("cursor", "default");
            if (direction == "right")
              slideRight();
            else if (direction == "left")
              slideLeft();
          break;
        }
      }


      // public

      return {

         /**
          * - initial version
          */
         // version : "1.0",

         /**
          * - remove mode
          * - support mobile dragging
          * - refreshing bug fixed
          */
         version : "1.1",

         init : function() {
            initStructure();
            initSlides();
            fireEvents();
         },

         setAnimationSpeed: function(speed) {
            speed = parseInt(speed);
            if(!isInt(speed)) return;
            Config.animationSpeed = speed;
         },

         setLeftArrow: function(arrow) {
            Config.leftArrow = arrow;
         },

         setRightArrow: function(arrow) {
            Config.rightArrow = arrow;
         },

         setArrowsLocation: function(selector) {
            Config.arrowsLocation = selector;
         },

         setArrowsPrepend: function(prepend) {
            Config.arrowsPrepend = prepend != "false";
         },

         setCss: function(css) {
          Config.styleSheet = css;
         }
      }
   };

   var slider = new Slider();
   win.Slider = slider;

   $(function(){
    slider.init();
   });

})(window);



