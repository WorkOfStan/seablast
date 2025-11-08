"use strict";
// seablast-bridge.js is ES modul and so are the scripts from which is imported below

import { BannerManager } from "./seablast.js";
import { ButtonPanel } from "./ButtonPanel.js";
import { ErrorLogger } from "./seablast.js";
import { Overlay } from "./Overlay.js";

// Let's make these accessible for non-module scripts
window.BannerManager = BannerManager;
window.ButtonPanel = ButtonPanel;
window.ErrorLogger = ErrorLogger;
window.Overlay = Overlay;

//  const errorLogger = new ErrorLogger(env.csrfToken, env.API_BASE);
//  const bannerManager = new BannerManager();
//  const fileInput = document.getElementById("fileInput"); // dropzone
//  const dropZone = $("#drop_zone"); // dropzone
//  const overlay = new Overlay(env, bannerManager);
  