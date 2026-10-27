const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

async function main() {
  const requests = [];
  let result = { ok: true, json: async () => ({ csrfToken: "issued-token" }) };
  const context = vm.createContext({
    URL,
    window: { location: new URL("https://example.test/app/page") },
    fetch: async (url, options) => {
      requests.push({ url, options });
      return result;
    },
  });
  const source = fs
    .readFileSync(path.join(__dirname, "../assets/scripts/seablast.js"), "utf8")
    .replace(/^export\s*\{[^}]*\};?\s*$/gm, "");
  vm.runInContext(source + "\nglobalThis.obtain = getCsrfToken;", context);
  assert.equal(
    await context.obtain("/app", "existing-token"),
    "existing-token",
  );
  assert.equal(requests.length, 0);
  await assert.rejects(
    context.obtain("https://evil.test", "existing-token"),
    /same-origin/,
  );
  assert.equal(requests.length, 0);
  const tokens = await Promise.all([
    context.obtain("/app"),
    context.obtain("/app/"),
  ]);
  assert.deepEqual(tokens, ["issued-token", "issued-token"]);
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, "https://example.test/app/api/csrf");
  assert.equal(requests[0].options.credentials, "same-origin");
  assert.equal(requests[0].options.mode, "same-origin");
  assert.equal(requests[0].options.redirect, "error");
  assert.equal(requests[0].options.cache, "no-store");
  assert.equal(requests[0].options.headers["X-Seablast-CSRF"], "1");
  assert.equal(await context.obtain("/app"), "issued-token");
  assert.equal(requests.length, 1);
  result = { ok: false };
  await assert.rejects(context.obtain("/retry"), /initialize/);
  result = { ok: true, json: async () => ({ csrfToken: null }) };
  await assert.rejects(context.obtain("/retry"), /Missing/);
  result = { ok: true, json: async () => ({ csrfToken: "retried-token" }) };
  assert.equal(await context.obtain("/retry"), "retried-token");
  assert.equal(requests.length, 4);
  console.log(
    "On-demand CSRF token, concurrency, origin, and retry tests passed.",
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
