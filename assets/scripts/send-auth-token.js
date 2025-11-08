"use strict";
/* global $, API_BASE, csrfToken, flags */
// expected by plugin Seablast\Auth
// expects Environment.js and seablast.js

/**
 * Globally available function to send the social login auth token to your backend for verification.
 *
 * Expected by Seablast/Auth.
 *
 * @returns {void}
 */
$(() => {
  /** @type import('./Environment.js').Environment */
  const env = {
    API_BASE,
    API_BASE_DIR: `${API_BASE}/api/`,
    flags,
    csrfToken,
  };

  /**
   * Send the social login auth token to your backend for verification.
   *
   * @param {string} authToken  Social provider-issued auth token.
   * @param {string} provider   Provider name, e.g. "google" | "facebook" .
   * @param {{log: (message: string, level?: string) => void}} errorLogger  Logger with a .log(message, level) method.
   * @param {{ userRoute?: string }} [options]  Optional settings. If userRoute is provided and current path differs, it will redirect there on success; otherwise it just reloads the page to refresh the menu.
   * @returns {void}
   */
  window.sendAuthToken = (authToken, provider, errorLogger, options = {}) => {
    fetch(`${env.API_BASE_DIR}social-login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        csrfToken: env.csrfToken,
        authToken,
        provider,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // User is successfully logged in, handle accordingly.
          // If a specific user route is provided and we're not on it, navigate there; otherwise reload to update the menu.
          // TODO ? if not called from /user, go to /user {* AuthConstant::USER_ROUTE *}
          if (options.userRoute && location.pathname !== options.userRoute) {
            location.assign(options.userRoute);
          } else {
            location.reload(); // To refresh the UI after login
          }
        } else {
          // Handle login failure.
          if (errorLogger && typeof errorLogger.log === "function") {
            errorLogger.log(
              `Social login to ${provider} failed: ${JSON.stringify(data)}`,
              "error",
            );
          }
        }
      })
      .catch((err) => {
        if (errorLogger && typeof errorLogger.log === "function") {
          errorLogger.log(
            `Network/parse error during social login to ${provider}: ${String(err)}`,
            "error",
          );
        }
      });
  };
}); //$
