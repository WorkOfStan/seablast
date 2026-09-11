const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const requests = [];
let now = 100000;
let banners = 0;
const context = vm.createContext({
  console: { error() {}, log() {} },
  window: { location: { href: "https://example.test/page" } },
  Date: class extends Date {
    static now() {
      return now;
    }
  },
  $: {
    ajax(request) {
      requests.push(request);
    },
  },
});
const source = fs
  .readFileSync(path.join(__dirname, "../assets/scripts/seablast.js"), "utf8")
  .replace(/^export\s*\{[^}]*\};?\s*$/gm, "");
vm.runInContext(
  source +
    "\nglobalThis.ErrorLogger = ErrorLogger; globalThis.BannerManager = BannerManager;",
  context,
);
context.BannerManager.prototype.addBanner = () => {
  banners++;
};
const logger = new context.ErrorLogger("token", "/app");
logger.log("normal");
assert.equal(requests.length, 1);
assert.equal(JSON.parse(requests[0].data).csrfToken, "token");
assert.equal(requests[0].url, "/app/api/error");
requests[0].error(
  { status: 429, getResponseHeader: () => "10" },
  "error",
  "Too Many Requests",
);
logger.log("paused");
assert.equal(requests.length, 1);
now += 10000;
logger.log("resumed");
assert.equal(requests.length, 2);
requests[1].error(
  { status: 429, getResponseHeader: () => new Date(now + 20000).toUTCString() },
  "error",
  "429",
);
logger.log("date paused");
assert.equal(requests.length, 2);
now += 20000;
logger.log("date resumed");
assert.equal(requests.length, 3);
requests[2].error(
  { status: 429, getResponseHeader: () => null },
  "error",
  "429",
);
now += 59999;
logger.log("fallback paused");
assert.equal(requests.length, 3);
now++;
logger.log("fallback resumed");
assert.equal(requests.length, 4);
requests[3].error({ status: 403 }, "error", "Disabled");
now += 1000000;
logger.log("disabled");
assert.equal(requests.length, 4);
assert.equal(banners, 0);
const other = new context.ErrorLogger("token", "/app");
other.log("other instance");
assert.equal(requests.length, 5);
requests[4].error({ status: 500 }, "error", "Server error");
assert.equal(banners, 1);
console.log("ErrorLogger cooldown, disable, and fallback tests passed.");
