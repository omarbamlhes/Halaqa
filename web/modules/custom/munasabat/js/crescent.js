/**
 * @file
 * Floating crescent and stars effect for Ramadan.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.munasabatCrescent = {
    attach: function (context) {
      once('munasabat-crescent', 'body.occasion--active', context).forEach(function (body) {
        var container = document.createElement('div');
        container.className = 'occasion-crescent-container';
        body.appendChild(container);

        var symbols = ['&#9734;', '&#9733;', '&#9790;'];
        var count = 15;

        for (var i = 0; i < count; i++) {
          var el = document.createElement('span');
          el.className = 'occasion-crescent-star';
          el.innerHTML = symbols[Math.floor(Math.random() * symbols.length)];
          el.style.left = Math.random() * 100 + '%';
          el.style.fontSize = (Math.random() * 20 + 12) + 'px';
          el.style.animationDelay = (Math.random() * 6) + 's';
          el.style.animationDuration = (Math.random() * 4 + 4) + 's';
          container.appendChild(el);
        }

        // Remove after 10 seconds.
        setTimeout(function () {
          container.style.transition = 'opacity 2s';
          container.style.opacity = '0';
          setTimeout(function () {
            container.remove();
          }, 2000);
        }, 10000);
      });
    }
  };

})(Drupal, once);
