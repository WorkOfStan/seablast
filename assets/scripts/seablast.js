"use strict";
// Seablast common components
/* global $ */

/**
 * Module for invoking /api/error to log the client issues on the server.
 *
 * Usage:
 * const errorLogger = new ErrorLogger(env.csrfToken, env.API_BASE);
 * errorLogger.log('Welcome, hi', 'debug');
 *
 * @class
 * @constructor
 * @public
 * @param {string} csrfToken
 * @param {string} apiBase
 */
class ErrorLogger {
  constructor(csrfToken, apiBase) {
    this.csrfToken = csrfToken;
    this.errorCount = 0;
    this.apiBase = apiBase;
  }

  log(message, severity = "error") {
    const errorData = {
      csrfToken: this.csrfToken,
      message,
      severity,
      order: ++this.errorCount,
      page: window.location.href,
    };
    console.error(JSON.stringify(errorData)); // Consider removing for production
    this.sendErrorData(errorData);
  }

  sendErrorData(errorData) {
    $.ajax({
      url: this.apiBase + "/api/error",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify(errorData),
      dataType: "json", // Expecting JSON response
      success: (response) => {
        console.log("Error sent successfully to be logged", response);
      },
      error: (xhr, status, error) => {
        console.error("Error sending data of error: " + this.errorCount, error); // debug
        const banners = new BannerManager();
        banners.addBanner("Error sending data " + error, "warning");
      },
    });
  }
}
// Export the class
export { ErrorLogger };

/**
 * Messages - With this setup, each time addBanner is called, a new banner message is added to the container,
 * and each banner can be closed individually without affecting the others.
 *
 * Usage:
 * const bannerManager = new BannerManager();
 * bannerManager.addBanner('Another footer information message', 'info');
 * bannerManager.addBanner('Another footer warning message', 'warning');
 *
 * @class
 * @constructor
 * @public
 */
class BannerManager {
  constructor() {
    this.validTypes = ["warning", "info"];
  }

  isBottomOfElementOutsideView(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
      return null; // element is not found
    }
    // Get the element's position relative to the viewport
    const rect = element.getBoundingClientRect();
    // Calculate the element's bottom position relative to the document
    const elementBottom = rect.top + rect.height + window.scrollY;
    // Get the viewport's height
    const viewportHeight = window.innerHeight;
    // Get the current scroll position
    const scrollPosition = window.scrollY;
    // Check if the bottom of the element is vertically outside the viewport
    return (
      elementBottom - scrollPosition < 0 ||
      elementBottom - scrollPosition > viewportHeight
    );
  }

  addBanner(message, type = "info") {
    if (!this.validTypes.includes(type)) {
      type = "warning"; // Default to 'warning' if the type is not valid
    }
    const banner = document.createElement("div");
    banner.className = `banner ${type}`;
    banner.innerHTML = `<span>${message}</span>`;

    const closeButton = document.createElement("button");
    closeButton.innerHTML = "✖";
    closeButton.onclick = () => banner.remove();
    banner.appendChild(closeButton);

    const bannerContainer = document.getElementById("banner-container");
    const shouldFly = this.isBottomOfElementOutsideView("banner-container");
    const targetContainer =
      shouldFly &&
      document.querySelector("main > div.container > div.k-container-square")
        ? // The :first-child pseudo-class is not necessary because querySelector automatically returns the first matching element.
          document.querySelector(
            "main > div.container > div.k-container-square",
          ) // fly
        : bannerContainer; // just show
    targetContainer.appendChild(banner);

    if (shouldFly) {
      banner.classList.add("floating");
      setTimeout(() => {
        banner.classList.remove("floating");
        bannerContainer.appendChild(banner);
        // Optional: scroll into view
        // bannerContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 1800); // Matches floatBanner animation duration
    }
    return banner; // argument for changeBanner
  }

  /**
   * Updates the message and optionally the type of an existing banner.
   * If the specified type is not valid, it defaults to 'warning'.
   * @param {HTMLElement} banner - The banner element to update.
   * @param {string} message - The new message for the banner.
   * @param {string} [type] - Optional. The new type for the banner. If not provided, the banner's type is not changed.
   */
  changeBanner(banner, message, type = "") {
    // Update the banner message
    const span = banner.querySelector("span");
    if (span) {
      span.textContent = message;
    }
    // Update the banner type, if a new type is provided and it is valid.
    if (!type) {
      // If no type is provided, do not change the banner's class to keep its current type.
      return;
    }
    // normalize type
    if (!this.validTypes.includes(type)) {
      type = "warning"; // Defaults to 'warning', if the type is not valid.
    }
    $.each(this.validTypes, function (index, className) {
      $(banner).toggleClass(className, className === type);
    });
  }
}

// Example of using BannerManager to add banners on page load
//document.addEventListener('DOMContentLoaded', function() {
//    const bannerManager = new BannerManager();
//    bannerManager.addBanner('This is an information message', 'info');
//    bannerManager.addBanner('Just a test', 'warning');
//    bannerManager.addBanner('Another information message', 'info');
//});
// Export the class
export { BannerManager };
