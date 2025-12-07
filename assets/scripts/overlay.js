"use strict";
/* global $ */
//import { BannerManager } from "../../vendor/seablast/seablast/assets/scripts/seablast.js";
import { ButtonPanel } from "./ButtonPanel.js"; // TODO inject instead of import!

export class Overlay {
  /**
   *
   * @param {import('Environment.js').Environment} env
   * @param {BannerManager} bannerManager
   */
  constructor(env, bannerManager) {
    this.env = env;
    this.bannerManager = bannerManager;

    /** @type {string} apiUrl */
    this.apiUrl = "";
    /** @type {string} apiParamName */
    this.apiParamName = "";
    /** @type {string} currentText */
    this.currentText = ""; // Store the original text. It's only assigned once in create().
    /** @type {?JQuery} editedElement */
    this.editedElement = null;
    /** @type {?JQuery} overlayDiv */
    this.overlayDiv = null;
  }

  /**
   * Syntactic sugar
   * @param {JQuery} element
   */
  create(element) {
    this.editedElement = element;

    this.apiUrl = `${this.env.API_BASE}/${element.data("api")}`; // Note: api URL expected to work in the app root directory
    this.apiParamName = element.data("api-parameter");

    this.currentText = this.editedElement.text(); // Store the original text. It's only assigned here.
    //console.log("currentText", this.currentText); // debug
    const overlayDiv = (this.overlayDiv = $("<div/>", { id: "overlay" }).on(
      "click",
      (e) => {
        if (e.target === e.currentTarget) {
          // only if we clicked outside textarea and other elements
          this.save();
        }
      },
    ));

    // Create textarea
    const textarea = $('<textarea id="overlayTextarea">').val(this.currentText);

    // Create counter div BELOW the textarea // TODO make it optional, e.g. if there's a data-counter="znaků slov"
    const counterDiv = $('<div class="text-counter">')
      .append('<span id="charCount">0</span> znaků | ')
      .append('<span id="wordCount">0</span> slov');

    // Create the button panel
    const buttonPanel = new ButtonPanel();

    // Add the cancel button
    buttonPanel.addCancelButton(overlayDiv);

    buttonPanel.addButton(
      "Uložit text",
      "responsive button--save",
      () => this.save(),
      "Ctrl+Enter",
    );

    if (this.env.flags.includes("ui_allowAI")) {
      buttonPanel.addButton("AI návrh textu", "responsive", () => {
        const processedTextarea = $("textarea#overlayTextarea");
        if (processedTextarea && processedTextarea.val()) {
          this.sendTextToAI(processedTextarea.val())
            .then((aiResponse) => {
              // quick solution. TODO: visually appealing
              processedTextarea.val(
                (confirm(
                  "Nahradit tímto textem (nebo jen přidat)? " + aiResponse,
                )
                  ? ""
                  : processedTextarea.val() + " - ") + aiResponse,
              );
            })
            .catch((error) => {
              console.error("An error occurred:", error);
              this.bannerManager.addBanner(
                "Chyba při zpracování textu.",
                "warning",
              );
            });
        } else {
          console.error("No area to process");
        }
      });
    }

    // Append elements **in the correct order**: textarea -> counter -> buttons
    overlayDiv.append(textarea).append(counterDiv).append(buttonPanel.element);
    $("body").append(overlayDiv);
    textarea.trigger("focus");

    // **Function to update counter**
    const updateCounter = () => {
      const text = textarea.val();
      const charCount = text.length;
      const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
      $("#charCount").text(charCount);
      $("#wordCount").text(wordCount);
    };

    // **Bind event to textarea input**
    textarea.on("input", updateCounter);
    updateCounter(); // Update counter on load
  }

  save() {
    const newText = String($("textarea#overlayTextarea").val());
    //console.log("newText", newText); // debug

    // don't save if the val was not actually changed to save bandwith
    if (newText === this.currentText) {
      console.log("No changes detected, skipping save.");
      this.overlayDiv?.remove();
      return;
    }

    const postData = {
      [this.apiParamName]: newText,
      csrfToken: this.env.csrfToken,
    };

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify(postData),
      success: (response) => {
        console.log("Data sent successfully. Received: ", response);
        // put the new value to the current tile while returning proper new line
        this.editedElement?.html(newText.replace(/\n/g, "<br>\n"));
        // close the overlay
        this.overlayDiv?.remove();
        // if there's a text overflow, refresh the page (or remove overflowing tiles and recreate them)
        // TODO if the text gets shorter,  no refresh, so remaining studs
        // Clone the original div and modify it for measuring text fit
        const $clone = this.editedElement
          .clone()
          .css({
            visibility: "hidden",
            position: "absolute",
            "max-height": "none",
            width: this.editedElement.width(), // Ensure the width is the same
          })
          .appendTo("body");
        $clone.html(this.editedElement.html());
        // Quickly find just the limit of overflowing text not to start with the complete text
        if ($clone.outerHeight() > this.editedElement.height()) {
          console.log("Reloading as oH>eE.h"); // TODO really reload here? It reloads poseidon with .editable also. Why?
          location.reload();
        }
        $clone.remove();
      },
      // Arrow functions do not have their own this; they inherit it from the enclosing scope.
      error: (xhr, status, error) => {
        console.error("Error sending data:", status, error);
        this.bannerManager.addBanner(
          "Could not save. Please try again. Error sending data: " + error,
          "warning",
        );
        //alert("Error: Could not save. Please try again.");
      },
    });
  }

  /**
   * todo - this pt function shouldn't be part of universal
   *
   * @param {string} text
   * @returns
   */
  async sendTextToAI(text) {
    console.log("You entered: " + text);
    const dataToSend = {
      csrfToken: this.env.csrfToken,
      userInput: text,
    };
    // Return the fetch promise directly
    const response = await fetch(this.env.API_BASE_DIR + "text", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(dataToSend),
    });

    if (!response.ok) {
      // If the response is not 2xx, throw an error
      throw new Error("Network response was not ok");
    }
    const result_1 = await response.json();
    console.log("User input: " + text);
    return result_1.eloquentResponse;
    // Catch the error outside to differentiate ok vs error response
  }
}
