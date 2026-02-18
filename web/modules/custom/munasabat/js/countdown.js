/**
 * @file
 * Countdown timer for occasions.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.munasabatCountdown = {
    attach: function (context) {
      once('munasabat-countdown', '.occasion-countdown', context).forEach(function (element) {
        var targetDate = element.getAttribute('data-target-date');
        if (!targetDate) {
          return;
        }

        var target = new Date(targetDate + 'T00:00:00').getTime();
        var daysEl = element.querySelector('[data-countdown-days]');
        var hoursEl = element.querySelector('[data-countdown-hours]');
        var minutesEl = element.querySelector('[data-countdown-minutes]');
        var secondsEl = element.querySelector('[data-countdown-seconds]');
        var timerEl = element.querySelector('.occasion-countdown__timer');
        var reachedEl = element.querySelector('.occasion-countdown__reached');

        function updateCountdown() {
          var now = new Date().getTime();
          var distance = target - now;

          if (distance < 0) {
            if (timerEl) {
              timerEl.style.display = 'none';
            }
            if (reachedEl) {
              reachedEl.style.display = 'block';
            }
            return;
          }

          var days = Math.floor(distance / (1000 * 60 * 60 * 24));
          var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
          var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
          var seconds = Math.floor((distance % (1000 * 60)) / 1000);

          if (daysEl) daysEl.textContent = days;
          if (hoursEl) hoursEl.textContent = hours;
          if (minutesEl) minutesEl.textContent = minutes;
          if (secondsEl) secondsEl.textContent = seconds;
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
      });
    }
  };

})(Drupal, once);
