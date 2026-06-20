/**
 * Smart Attendance System (SAS)
 * Core Frontend JavaScript File
 */

document.addEventListener("DOMContentLoaded", function () {
    const toggleDesk = document.getElementById("sidebar-toggle-desk");
    const toggleMob = document.getElementById("sidebar-toggle-mob");
    const sidebar = document.getElementById("sidebar");

    // Desktop Sidebar collapse toggle
    if (toggleDesk) {
        toggleDesk.addEventListener("click", function () {
            document.body.classList.toggle("sidebar-collapsed");
            // Save state in localStorage to persist across navigation
            const isCollapsed = document.body.classList.contains("sidebar-collapsed");
            localStorage.setItem("sidebar-collapsed", isCollapsed);
        });

        // Restore sidebar state from local storage
        if (localStorage.getItem("sidebar-collapsed") === "true") {
            document.body.classList.add("sidebar-collapsed");
        }
    }

    // Mobile Sidebar overlay slide toggle
    if (toggleMob) {
        toggleMob.addEventListener("click", function (e) {
            e.stopPropagation();
            document.body.classList.toggle("sidebar-open");
        });
    }

    // Close mobile sidebar when clicking outside of it
    document.addEventListener("click", function (e) {
        if (document.body.classList.contains("sidebar-open")) {
            const isClickInsideSidebar = sidebar.contains(e.target);
            const isClickOnToggleButton = toggleMob && toggleMob.contains(e.target);

            if (!isClickInsideSidebar && !isClickOnToggleButton) {
                document.body.classList.remove("sidebar-open");
            }
        }
    });

    // Tooltip initialization (if bootstrap tooltips are used)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
