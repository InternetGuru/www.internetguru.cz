require("ScrollReveal", function () {
  window.sr = ScrollReveal({
    scale: 1,
    duration: 700,
    delay: 100,
    distance: '10em',
    origin: 'right',
    easing: 'ease',
    viewFactor: 0.6
  });
  sr.reveal('.img');
})
