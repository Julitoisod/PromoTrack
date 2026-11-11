/**
 * PromoTrack AFP-RF shared application JavaScript.
 * Vanilla JS only, per the required frontend stack.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggleButton = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('appSidebar');

        var DESKTOP_MIN_WIDTH = 992;
        var RAIL_STORAGE_KEY = 'promotrack.sidebarCollapsed';

        // The stored rail preference is a convenience; a browser that refuses
        // storage (private mode, blocked site data) must still render the page.
        var railStore = {
            read: function () {
                try {
                    return window.localStorage.getItem(RAIL_STORAGE_KEY) === '1';
                } catch (error) {
                    return false;
                }
            },
            write: function (collapsed) {
                try {
                    window.localStorage.setItem(RAIL_STORAGE_KEY, collapsed ? '1' : '0');
                } catch (error) {
                    // Preference not persisted; the current page still works.
                }
            },
        };

        // aria-expanded describes whether the sidebar's labels are showing, so it has to
        // track the rail state rather than being a static attribute in the markup.
        var syncToggleState = function () {
            if (toggleButton) {
                toggleButton.setAttribute(
                    'aria-expanded',
                    document.body.classList.contains('sidebar-collapsed') ? 'false' : 'true'
                );
            }
        };

        if (railStore.read()) {
            document.body.classList.add('sidebar-collapsed');
        }

        syncToggleState();

        if (toggleButton && sidebar) {
            toggleButton.addEventListener('click', function () {
                // One control, two jobs: off-canvas on phones, icon rail on desktop.
                if (window.innerWidth >= DESKTOP_MIN_WIDTH) {
                    var collapsed = document.body.classList.toggle('sidebar-collapsed');
                    railStore.write(collapsed);
                    syncToggleState();
                    return;
                }

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

        // Show / Hide for values masked as dots (username, email on Account
        // Settings) and for password inputs.
        document.addEventListener('click', function (event) {
            var maskButton = event.target.closest('.mask-toggle');

            if (maskButton) {
                var masked = document.getElementById(maskButton.dataset.maskTarget);
                var shown = maskButton.getAttribute('aria-pressed') === 'true';

                masked.querySelector('.masked-dots').classList.toggle('d-none', !shown);
                masked.querySelector('.masked-real').classList.toggle('d-none', shown);
                maskButton.setAttribute('aria-pressed', shown ? 'false' : 'true');
                maskButton.querySelector('i').className = shown ? 'bi bi-eye' : 'bi bi-eye-slash';
                maskButton.querySelector('span').textContent = shown ? 'Show' : 'Hide';
                return;
            }

            var passwordButton = event.target.closest('.password-toggle');

            if (passwordButton) {
                var field = document.getElementById(passwordButton.dataset.passwordTarget);
                var visible = field.type === 'text';

                field.type = visible ? 'password' : 'text';
                passwordButton.setAttribute('aria-pressed', visible ? 'false' : 'true');
                passwordButton.title = visible ? 'Show password' : 'Hide password';
                passwordButton.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
            }
        });

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
