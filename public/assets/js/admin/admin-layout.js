
/**
 * Admin layout interactions.
 *
 * Handles the mobile sidebar and the user dropdown.
 */

const sidebar = document.getElementById("admin-sidebar");
const sidebarToggle = document.getElementById("admin-sidebar-toggle");
const sidebarClose = document.getElementById("admin-sidebar-close");
const sidebarBackdrop = document.getElementById("admin-sidebar-backdrop");

const userMenu = document.querySelector(".admin-user-menu");
const userMenuToggle = document.getElementById("admin-user-menu-toggle");
const userMenuDropdown = document.getElementById("admin-user-menu-dropdown");

const desktopMediaQuery = window.matchMedia("(min-width: 992px)");

/**
 * Mobile sidebar.
 */

const isSidebarOpen = () => sidebar.classList.contains("is-open");

const openSidebar = () => {
    if (desktopMediaQuery.matches) {
        return;
    }

    closeUserMenu();

    sidebar.classList.add("is-open");
    sidebarBackdrop.hidden = false;

    sidebarToggle.setAttribute("aria-expanded", "true");
    sidebarToggle.setAttribute("aria-label", "Fermer le menu de navigation");

    document.body.style.overflow = "hidden";

    sidebarClose.focus();
};

const closeSidebar = (restoreFocus = false) => {
    const wasOpen = isSidebarOpen();

    sidebar.classList.remove("is-open");
    sidebarBackdrop.hidden = true;

    sidebarToggle.setAttribute("aria-expanded", "false");
    sidebarToggle.setAttribute("aria-label", "Ouvrir le menu de navigation");

    document.body.style.overflow = "";

    if (restoreFocus && wasOpen && !desktopMediaQuery.matches) {
        sidebarToggle.focus();
    }
};

sidebarToggle.addEventListener("click", () => {
    if (isSidebarOpen()) {
        closeSidebar(true);
    } else {
        openSidebar();
    }
});

sidebarClose.addEventListener("click", () => {
    closeSidebar(true);
});

sidebarBackdrop.addEventListener("click", () => {
    closeSidebar(true);
});

/**
 * User dropdown.
 */

const isUserMenuOpen = () => !userMenuDropdown.hidden;

function closeUserMenu(restoreFocus = false) {
    if (userMenuDropdown.hidden) {
        return;
    }

    userMenuDropdown.hidden = true;
    userMenuToggle.setAttribute("aria-expanded", "false");

    if (restoreFocus) {
        userMenuToggle.focus();
    }
}

const openUserMenu = () => {
    if (isSidebarOpen()) {
        closeSidebar();
    }

    userMenuDropdown.hidden = false;
    userMenuToggle.setAttribute("aria-expanded", "true");
};

userMenuToggle.addEventListener("click", () => {
    if (isUserMenuOpen()) {
        closeUserMenu();
    } else {
        openUserMenu();
    }
});

/**
 * Close the dropdown when clicking outside.
 */

document.addEventListener("click", (event) => {
    if (isUserMenuOpen() && !userMenu.contains(event.target)) {
        closeUserMenu();
    }
});

/**
 * Keyboard interactions.
 */

document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
        return;
    }

    if (isUserMenuOpen()) {
        closeUserMenu(true);
        return;
    }

    if (isSidebarOpen()) {
        closeSidebar(true);
    }
});

/**
 * Restore the desktop layout when resizing.
 */

desktopMediaQuery.addEventListener("change", (event) => {
    if (event.matches) {
        closeSidebar();
    }
});
