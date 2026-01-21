/**
 * @file
 * Notifications system JavaScript.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.halaqaNotifications = {
    attach: function (context, settings) {
      once('notification-bell', '[data-notification-toggle]', context).forEach(function (bell) {
        const wrapper = bell.closest('.notification-bell-wrapper');
        const dropdown = wrapper.querySelector('[data-notification-dropdown]');
        const badge = wrapper.querySelector('[data-notification-count]');
        const list = wrapper.querySelector('[data-notification-list]');
        const markAllBtn = wrapper.querySelector('[data-mark-all-read]');

        // Toggle dropdown on bell click
        bell.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();

          const isActive = dropdown.classList.contains('active');

          // Close any other open dropdowns
          document.querySelectorAll('.notification-dropdown.active').forEach(function (d) {
            d.classList.remove('active');
          });

          if (!isActive) {
            dropdown.classList.add('active');
            loadNotifications();
          }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
          if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('active');
          }
        });

        // Mark all as read
        if (markAllBtn) {
          markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            markAllAsRead();
          });
        }

        // Load notifications
        function loadNotifications() {
          fetch('/api/notifications', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
            }
          })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            renderNotifications(data);
          })
          .catch(function (error) {
            console.error('Error loading notifications:', error);
            list.innerHTML = '<div class="notification-dropdown-empty">' + Drupal.t('Error loading notifications') + '</div>';
          });
        }

        // Render notifications in dropdown
        function renderNotifications(data) {
          if (!data.notifications || data.notifications.length === 0) {
            list.innerHTML = '<div class="notification-dropdown-empty">' +
              '<span class="no-notifications-icon">🔔</span>' +
              '<p>' + Drupal.t('No notifications') + '</p></div>';
            return;
          }

          let html = '';
          data.notifications.forEach(function (notification) {
            const readClass = notification.is_read ? 'notification-read' : 'notification-unread';
            const colorClass = notification.color || 'notification-default';

            const content = '<div class="notification-icon">' + notification.icon + '</div>' +
              '<div class="notification-content">' +
              '<div class="notification-message">' + notification.message + '</div>' +
              '<div class="notification-time">' + notification.time_ago + '</div>' +
              '</div>';

            if (notification.link) {
              html += '<a href="' + notification.link + '" class="notification-item ' + colorClass + ' ' + readClass + '" data-notification-id="' + notification.id + '">' + content + '</a>';
            } else {
              html += '<div class="notification-item ' + colorClass + ' ' + readClass + '" data-notification-id="' + notification.id + '">' + content + '</div>';
            }
          });

          list.innerHTML = html;

          // Add click handler to mark as read when clicking notification
          list.querySelectorAll('.notification-item.notification-unread').forEach(function (item) {
            item.addEventListener('click', function () {
              const nid = this.getAttribute('data-notification-id');
              if (nid) {
                markAsRead(nid);
              }
            });
          });
        }

        // Mark single notification as read
        function markAsRead(nid) {
          fetch('/notifications/' + nid + '/mark-read', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            }
          })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            if (data.success) {
              updateUnreadCount();
            }
          })
          .catch(function (error) {
            console.error('Error marking notification as read:', error);
          });
        }

        // Mark all as read
        function markAllAsRead() {
          fetch('/notifications/mark-all-read', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            }
          })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            if (data.success) {
              // Update UI
              list.querySelectorAll('.notification-unread').forEach(function (item) {
                item.classList.remove('notification-unread');
                item.classList.add('notification-read');
              });
              updateBadge(0);
            }
          })
          .catch(function (error) {
            console.error('Error marking all as read:', error);
          });
        }

        // Update unread count
        function updateUnreadCount() {
          fetch('/api/notifications/unread-count', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
            }
          })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            updateBadge(data.unread_count);
          })
          .catch(function (error) {
            console.error('Error updating unread count:', error);
          });
        }

        // Update badge display
        function updateBadge(count) {
          if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
          } else {
            badge.classList.add('hidden');
          }
        }

        // Initial load of unread count
        updateUnreadCount();

        // Periodically check for new notifications (every 60 seconds)
        setInterval(updateUnreadCount, 60000);
      });
    }
  };

})(Drupal, drupalSettings, once);
