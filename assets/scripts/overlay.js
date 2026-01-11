"use strict";
/* global $ */
// TODO inject instead of import!
import { ButtonPanel } from "./button-panel.js";

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
    /** @type {"text"|"color"} */
    this.editorType = "text";
    /** @type {string} */
    this.editorSelector = "textarea#overlayTextarea";
    /** @type {?JQuery} overlayDiv */
    this.overlayDiv = null;
  }

  /**
   * Syntactic sugar
   * @param {JQuery} element
   */
  create(element) {
    this.editedElement = element;

    // Note: api URL expected to work in the app root directory
    this.apiUrl = `${this.env.API_BASE}/${element.data("api")}`;
    this.apiParamName = element.data("api-parameter");

    // Store the original text. It's only assigned here.
    this.currentText = this.editedElement.text();
    //console.log("currentText", this.currentText); // debug
    // Detect editor type
    this.editorType = element.hasClass("color-edit") ? "color" : "text";
    this.editorSelector =
      this.editorType === "color" ? "input#overlayColorInput" : "textarea#overlayTextarea";

    // Prepare initial value
    let initialValue = this.currentText;
    if (this.editorType === "color") {
      initialValue = this.normalizeHexColor(this.currentText) ?? "#000000";
    }

    const overlayDiv = (this.overlayDiv = $("<div>", { id: "overlay" }).on("click", (e) => {
      if (e.target === e.currentTarget) {
        // only if we clicked outside editor and other elements
        this.save();
      }
    }));

// todo const editorEl
    // Create editor (textarea or color input)
    let editorEl;
    if (this.editorType === "color") {
      editorEl = $('<input type="color" id="overlayColorInput" />').val(initialValue);
    } else {
      editorEl = $('<textarea id="overlayTextarea"></textarea>').val(initialValue);
    }

    // Create the button panel
    const buttonPanel = new ButtonPanel();
    buttonPanel.addCancelButton(overlayDiv);

    if (this.editorType === "text") {
      // Create counter div BELOW the textarea
      // TODO make it optional, e.g. if there's a data-counter="znaků slov"
      const counterDiv = $('<div class="text-counter">')
        .append('<span id="charCount">0</span> znaků | ')
        .append('<span id="wordCount">0</span> slov');

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
                  (confirm("Nahradit tímto textem (nebo jen přidat)? " + aiResponse)
                    ? ""
                    : processedTextarea.val() + " - ") + aiResponse,
                );
              })
              .catch((error) => {
                console.error("An error occurred:", error);
                this.bannerManager.addBanner("Chyba při zpracování textu.", "warning");
              });
          } else {
            console.error("No area to process");
          }
        });
      }

      // Append elements **in the correct order**: textarea -> counter -> buttons
      overlayDiv.append(editorEl).append(counterDiv).append(buttonPanel.element);
      $("body").append(overlayDiv);

      editorEl.trigger("focus");

      // **Function to update counter**
      const updateCounter = () => {
        const text = editorEl.val();
        const charCount = text.length;
        const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
        $("#charCount").text(charCount);
        $("#wordCount").text(wordCount);
      };

      // **Bind event to textarea input**
      editorEl.on("input", updateCounter);
      updateCounter(); // Update counter on load
    } else {
      console.log('col edit');
      // COLOR editor
      buttonPanel.addButton("Uložit", "responsive button--save", () => this.save());
overlayDiv.append(editorEl).append(buttonPanel.element);
$("body").append(overlayDiv);
console.log('docusY');
// Zabraň tomu, aby kliky na input “propadaly” do overlay
editorEl.on("click mousedown pointerdown", (e) => e.stopPropagation());
console.log('docusX');
// Focus
editorEl.trigger("focus");
console.log('docus');
// Pokus o okamžité otevření pickeru (funguje v části browserů)
const input = editorEl[0];
try {
  console.log('try');
  if (typeof input.showPicker === "function") {
    console.log('showPicker');
    input.showPicker();      // Chromium-based (novější)
  } else {
    console.log('click');
    input.click();           // fallback (někde funguje)
  }
} catch (e) {
  console.log('manually');
  // některé prohlížeče to bloknou – pak se musí kliknout ručně
}

// Doporučené UX: uložit hned po změně barvy
editorEl.on("change", () => this.save());
    }
  }

  save() {
    const rawNewValue = String($(this.editorSelector).val());

    // Don't save if the val was not actually changed to save bandwidth
    // Normalize compare for color
    let compareNew = rawNewValue;
    let compareOld = this.currentText;

// todo is color normalization needed??? stupid value is #000000 anyway, right?
    if (this.editorType === "color") {
      compareNew = (this.normalizeHexColor(rawNewValue) ?? rawNewValue).toLowerCase();
      compareOld = (this.normalizeHexColor(this.currentText) ?? this.currentText).toLowerCase();
    }

    if (compareNew === compareOld) {
      console.log("No changes detected, skipping save.");
      this.overlayDiv?.remove();
      return;
    }

    const postData = {
      [this.apiParamName]: rawNewValue,
      csrfToken: this.env.csrfToken,
    };

    $.ajax({
      url: this.apiUrl,
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify(postData),
      success: (response) => {
        console.log("Data sent successfully. Received: ", response);

        if (this.editorType === "color") {
          const normalized = this.normalizeHexColor(rawNewValue) ?? rawNewValue;
          this.editedElement?.text(normalized);

          // Volitelné: ať je hned barva vidět (zruš, pokud nechceš)
          this.editedElement?.css("background-color", normalized);
        } else {
          // put the new value to the current tile while returning proper new line
          this.editedElement?.html(rawNewValue.replace(/\n/g, "<br>\n"));
        }

        // close the overlay
        this.overlayDiv?.remove();

        // if there's a text overflow, refresh the page (or remove overflowing tiles and recreate them)
        // TODO if the text gets shorter, no refresh, so remaining studs

        // Overflow check dává smysl jen pro text
        if (this.editorType === "text") {
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
            console.log("Reloading as oH>eE.h");
            // TODO really reload here? It reloads poseidon with .editable also. Why?
            location.reload();
          }
          $clone.remove();
        }
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
   * @param {string} v
   * @returns {string|null} "#rrggbb" nebo null
   */
  normalizeHexColor(v) {
    if (!v) return null;
    const s = String(v).trim().toLowerCase();

    // "#abc" -> "#aabbcc"
    const m3 = s.match(/^#([0-9a-f]{3})$/i);
    if (m3) {
      const a = m3[1].split("");
      return "#" + a.map((ch) => ch + ch).join("");
    }

    // "#aabbcc"
    const m6 = s.match(/^#([0-9a-f]{6})$/i);
    if (m6) return "#" + m6[1];

    return null;
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

    const result = await response.json();
    console.log("User input: " + text);

    // Catch the error outside to differentiate ok vs error response
    return result.eloquentResponse;
  }
}
