// Navigation Active State
document.querySelectorAll(".nav-link").forEach((link) => {
  link.addEventListener("click", function (e) {
    if (this.getAttribute("href") === "#") {
      e.preventDefault();
    }
    document
      .querySelectorAll(".nav-link")
      .forEach((l) => l.classList.remove("active"));
    this.classList.add("active");
  });
});

// Set default active nav link on load if none already exists
document.addEventListener("DOMContentLoaded", function () {
  const activeLink = document.querySelector(".nav-link.active");
  if (!activeLink) {
    const firstLink = document.querySelector(".nav-link");
    if (firstLink) {
      firstLink.classList.add("active");
    }
  }
});

// Quick Action Buttons
const requestHelpBtn = document.querySelector(".action-button.primary");
if (requestHelpBtn) {
  requestHelpBtn.addEventListener("click", function () {
    alert("Opening Request Help form...");
  });
}

const manageSkillsBtn = document.querySelectorAll(
  ".action-button.secondary",
)[0];
if (manageSkillsBtn) {
  manageSkillsBtn.addEventListener("click", function () {
    alert("Opening Manage Skills...");
  });
}

const findMentorsBtn = document.querySelector(".action-button.outline");
if (findMentorsBtn) {
  findMentorsBtn.addEventListener("click", function () {
    alert("Finding mentors...");
  });
}

// Offer Help Button
document.querySelectorAll(".offer-help-btn").forEach((btn) => {
  btn.addEventListener("click", function (e) {
    e.preventDefault();
    alert("Thank you for offering help!");
  });
});

// Top navigation menu on small screens
document.addEventListener("DOMContentLoaded", function () {
  const navbar = document.querySelector(".navbar");
  const menuToggle = document.getElementById("menuToggle");

  if (navbar && menuToggle) {
    menuToggle.addEventListener("click", function () {
      const isOpen = navbar.classList.toggle("is-open");
      menuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
  }

  // Dashboard sidebar toggle (mobile)
  const sidebar = document.getElementById("appSidebar");
  const sidebarToggle = document.getElementById("sidebarToggle");
  if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      sidebar.classList.toggle("is-open");
    });
    document.addEventListener("click", function (e) {
      if (
        window.innerWidth <= 900 &&
        sidebar.classList.contains("is-open") &&
        !sidebar.contains(e.target) &&
        e.target !== sidebarToggle &&
        !sidebarToggle.contains(e.target)
      ) {
        sidebar.classList.remove("is-open");
      }
    });
  }
});


