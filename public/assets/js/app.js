/**
 * PromoTrack AFP-RF shared application JavaScript.
 * Vanilla JS only, per the required frontend stack.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggleButton = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('appSidebar');

        if (toggleButton && sidebar) {
            toggleButton.addEventListener('click', function () {
                sidebar.classList.toggle('show');
            });

            document.addEventListener('click', function (event) {
                var isClickInsideSidebar = sidebar.contains(event.target);
                var isClickOnToggle = toggleButton.contains(event.target);

                if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });
        }

        // Auto-dismiss confirmation prompts on any element carrying
        // data-confirm="Message to show before submit".
        document.querySelectorAll('[data-confirm]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                var message = element.getAttribute('data-confirm');

                if (typeof Swal !== 'undefined') {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Please confirm',
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, proceed',
                        cancelButtonText: 'Cancel',
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            if (element.tagName === 'FORM') {
                                element.submit();
                            } else if (element.closest('form')) {
                                element.closest('form').submit();
                            } else if (element.href) {
                                window.location.href = element.href;
                            }
                        }
                    });
                } else if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        // Bell-icon notification dropdown: polled once on page load.
        var notificationsList = document.getElementById('notificationsList');
        var notificationsBadge = document.getElementById('notificationsBadge');

        if (notificationsList && notificationsBadge) {
            fetch(baseUrl + 'api/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        return;
                    }

                    var unreadCount = payload.data.unread_count || 0;

                    if (unreadCount > 0) {
                        notificationsBadge.textContent = unreadCount;
                        notificationsBadge.classList.remove('d-none');
                    }

                    var notifications = payload.data.notifications || [];

                    if (notifications.length === 0) {
                        return;
                    }

                    notificationsList.innerHTML = '';

                    notifications.forEach(function (notification) {
                        var item = document.createElement('li');
                        var link = document.createElement('a');
                        link.className = 'dropdown-item small';
                        link.href = '#';
                        link.textContent = notification.title;
                        link.addEventListener('click', function (event) {
                            event.preventDefault();
                            var csrfHeaderMeta = document.querySelector('meta[name="csrf-header"]');
                            var csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                            var headers = { 'X-Requested-With': 'XMLHttpRequest' };

                            if (csrfHeaderMeta && csrfTokenMeta) {
                                headers[csrfHeaderMeta.content] = csrfTokenMeta.content;
                            }

                            fetch(baseUrl + 'api/notifications/' + notification.id + '/read', {
                                method: 'POST',
                                headers: headers,
                            });
                        });
                        item.appendChild(link);
                        notificationsList.appendChild(item);
                    });
                })
                .catch(function () {
                    // Notifications are a convenience, not a critical path; fail silently.
                });
        }
    });
})();
