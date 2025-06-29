"use strict";
/* global $, API_BASE, csrfToken, flags */

import { ErrorLogger } from "./seablast.js";

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
  const errorLogger = new ErrorLogger(env.csrfToken, env.API_BASE);

  /**
   * Send the social login auth token to your backend for verification.
   *
   * @param {string} authToken
   * @param {string} provider
   * @returns {void}
   */
  window.sendAuthToken = (authToken, provider) => {
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
          // User is successfully logged in, handle accordingly
          //console.log("Social login successful", data);
          // TODO if not called from /user, go to /user {* AuthConstant::USER_ROUTE *}
          location.reload(); // To update the menu
        } else {
          // Handle login failure
          errorLogger.log(
            `Social login to ${provider} failed: ${JSON.stringify(data)}`,
            "error",
          );
        }
      });
  };
}); //$
