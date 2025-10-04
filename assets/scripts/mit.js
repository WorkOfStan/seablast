"use strict";
/*!
 * MIT License for mit.js - Seablast common components
 *
 * Copyright (c) 2024 Stanislav Rejthar
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
/* global $, API_BASE, csrfToken, flags */

import { Overlay } from "./Overlay.js";
import { BannerManager } from "./seablast.js";

$(() => {
  /** @type import('Environment.js').Environment */
  const env = {
    API_BASE,
    API_BASE_DIR: `${API_BASE}/api/`,
    flags,
    csrfToken,
  };

  const bannerManager = new BannerManager();
  const overlay = new Overlay(env, bannerManager);
  initEditable();

  function initEditable() {
    // editable (todo move to seablast also SB/Admin)
    // Delegate event if .editable elements are dynamic.
    // The overlay.create function is only called when an element with the .editable class and both
    // data-api-parameter and data-api attributes is clicked.
    $("body").on("click", ".editable[data-api-parameter][data-api]", (e) =>
      overlay.create($(e.target)),
    );
  }
});
