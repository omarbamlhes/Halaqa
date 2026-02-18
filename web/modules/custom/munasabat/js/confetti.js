/**
 * @file
 * Confetti particle animation for celebrations.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.munasabatConfetti = {
    attach: function (context) {
      once('munasabat-confetti', 'body.occasion--active', context).forEach(function (body) {
        var canvas = document.createElement('canvas');
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '99999';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        body.appendChild(canvas);

        var ctx = canvas.getContext('2d');
        var particles = [];
        var colors = ['#006B3F', '#FFFFFF', '#D4AF37', '#C5A028', '#6B4226'];
        var startTime = Date.now();
        var duration = 5000;
        var fadeStart = 3500;

        // Create particles.
        for (var i = 0; i < 150; i++) {
          particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            size: Math.random() * 8 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            speedY: Math.random() * 3 + 1,
            speedX: (Math.random() - 0.5) * 2,
            rotation: Math.random() * 360,
            rotationSpeed: (Math.random() - 0.5) * 10
          });
        }

        function animate() {
          var elapsed = Date.now() - startTime;

          if (elapsed > duration) {
            canvas.remove();
            return;
          }

          var globalAlpha = 1;
          if (elapsed > fadeStart) {
            globalAlpha = 1 - (elapsed - fadeStart) / (duration - fadeStart);
          }

          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.globalAlpha = globalAlpha;

          particles.forEach(function (p) {
            p.y += p.speedY;
            p.x += p.speedX;
            p.rotation += p.rotationSpeed;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rotation * Math.PI) / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size / 2);
            ctx.restore();

            // Reset if off screen.
            if (p.y > canvas.height) {
              p.y = -p.size;
              p.x = Math.random() * canvas.width;
            }
          });

          requestAnimationFrame(animate);
        }

        animate();

        // Handle resize.
        window.addEventListener('resize', function () {
          canvas.width = window.innerWidth;
          canvas.height = window.innerHeight;
        });
      });
    }
  };

})(Drupal, once);
