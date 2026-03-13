function s(e) {
  return e !== null && typeof e == "object" && !Array.isArray(e);
}
function c(e) {
  return typeof e == "function";
}
function m(e) {
  return typeof e == "string";
}
function S(e) {
  return typeof e > "u";
}
function $r(e) {
  return typeof e == "boolean";
}
function mt(e, t) {
  return !s(e) && typeof e != "function" ? !1 : Object.prototype.hasOwnProperty.call(e, t);
}
function Or(e) {
  if ($r(e))
    return e;
  if (typeof e == "number")
    return e !== 0;
  if (m(e)) {
    const t = e.trim().toLowerCase();
    if (["true", "1", "yes", "on"].includes(t))
      return !0;
    if (["false", "0", "no", "off", ""].includes(t))
      return !1;
  }
  return !!e;
}
function l(e, t = "") {
  if (!m(e))
    return t;
  const n = e.trim();
  return n !== "" ? n : t;
}
function f(e, t, n = null) {
  if (!s(e) || !m(t) || t === "")
    return n;
  const r = t.split(".");
  let o = e;
  for (const i of r) {
    if (!s(o) && typeof o != "function" || !mt(o, i))
      return n;
    o = o[i];
  }
  return o;
}
const M = "AuthKit", T = {
  runtime: {
    windowKey: M,
    dispatchEvents: !0,
    eventTarget: "document"
  },
  ui: {
    mode: "system"
  },
  events: {
    ready: "authkit:ready",
    theme_ready: "authkit:theme:ready",
    theme_changed: "authkit:theme:changed",
    form_before_submit: "authkit:form:before-submit",
    form_success: "authkit:form:success",
    form_error: "authkit:form:error",
    page_ready: "authkit:page:ready"
  },
  modules: {
    theme: {
      enabled: !0
    },
    forms: {
      enabled: !0
    }
  },
  pages: {}
};
let A = null;
function dt() {
  return S(window) ? M : s(window[M]) && s(window[M].config) && s(window[M].config.runtime) && m(window[M].config.runtime.windowKey) ? l(
    window[M].config.runtime.windowKey,
    M
  ) : s(window.__AUTHKIT__) && m(window.__AUTHKIT__.windowKey) ? l(window.__AUTHKIT__.windowKey, M) : M;
}
function Br() {
  if (S(window))
    return {};
  const e = dt(), t = window[e];
  return s(t) ? t : {};
}
function Ir() {
  const e = Br();
  return s(e.config) ? e.config : {};
}
function gt() {
  if (A !== null)
    return A;
  const e = Ir();
  return A = {
    ...T,
    ...e,
    runtime: {
      ...T.runtime,
      ...s(e.runtime) ? e.runtime : {}
    },
    ui: {
      ...T.ui,
      ...s(e.ui) ? e.ui : {}
    },
    events: {
      ...T.events,
      ...s(e.events) ? e.events : {}
    },
    modules: {
      ...T.modules,
      ...s(e.modules) ? e.modules : {},
      theme: {
        ...T.modules.theme,
        ...s(e.modules?.theme) ? e.modules.theme : {}
      },
      forms: {
        ...T.modules.forms,
        ...s(e.modules?.forms) ? e.modules.forms : {}
      }
    },
    pages: s(e.pages) ? e.pages : T.pages
  }, A;
}
function h(e, t = null) {
  if (!m(e) || e.trim() === "")
    return t;
  const n = gt(), r = e.split(".").filter(Boolean);
  let o = n;
  for (const i of r) {
    if (!s(o) || !mt(o, i))
      return t;
    o = o[i];
  }
  return o === void 0 ? t : o;
}
function pt() {
  return document.documentElement || null;
}
function Ct(e, t = document) {
  return !m(e) || e.trim() === "" || !t || !c(t.querySelector) ? null : t.querySelector(e);
}
function d(e, t = document) {
  return !m(e) || e.trim() === "" ? [] : !t || !c(t.querySelectorAll) ? [] : Array.from(t.querySelectorAll(e));
}
function oe(e) {
  return e instanceof Element;
}
function Rr(e) {
  return e instanceof HTMLFormElement;
}
function bt(e, t, n = null) {
  if (!oe(e) || !m(t) || t.trim() === "")
    return n;
  const r = t.trim();
  if (r.startsWith("data-")) {
    const i = e.getAttribute(r);
    return i !== null ? i : n;
  }
  const o = e.dataset?.[r];
  return o !== void 0 ? o : n;
}
function ie() {
  const e = Ct(".authkit-page");
  return e instanceof HTMLElement ? e : null;
}
function zr() {
  const e = ie();
  return e ? l(bt(e, "data-authkit-page"), null) : null;
}
function p(e, t, n = null) {
  if (!oe(e) || !m(t) || t.trim() === "")
    return n;
  const r = e.getAttribute(t.trim());
  return r !== null ? r : n;
}
function jr(e = "data-authkit-theme-toggle") {
  const t = `[${e}]`;
  return d(t).filter((n) => n instanceof HTMLElement);
}
function Kr() {
  return d("[data-authkit-theme-toggle-select]").filter(
    (e) => e instanceof HTMLSelectElement
  );
}
function xr() {
  return d("[data-authkit-theme-toggle-cycle]").filter(
    (e) => e instanceof HTMLButtonElement
  );
}
function Vr(e = "data-authkit-ajax") {
  const t = `[${e}]`;
  return d(t).filter((n) => n instanceof HTMLFormElement);
}
function E(e = document) {
  return d("form", e).filter((t) => t instanceof HTMLFormElement);
}
function se(e, t, n, r = !1) {
  return !e || !c(e.addEventListener) ? () => {
  } : !m(t) || t.trim() === "" ? () => {
  } : (e.addEventListener(t, n, r), () => {
    c(e.removeEventListener) && e.removeEventListener(t, n, r);
  });
}
function Dr() {
  return document.readyState === "interactive" || document.readyState === "complete";
}
function Nr(e) {
  if (c(e)) {
    if (Dr()) {
      e();
      return;
    }
    document.addEventListener("DOMContentLoaded", e, { once: !0 });
  }
}
const Ur = {
  ready: "authkit:ready",
  theme_ready: "authkit:theme:ready",
  theme_changed: "authkit:theme:changed",
  form_before_submit: "authkit:form:before-submit",
  form_success: "authkit:form:success",
  form_error: "authkit:form:error",
  page_ready: "authkit:page:ready"
};
function qr() {
  return !!h("runtime.dispatchEvents", !0);
}
function Jr() {
  return l(
    h("runtime.eventTarget", "document"),
    "document"
  );
}
function Gr() {
  return Jr() === "window" && !S(window) ? window : document;
}
function Wr(e, t = null) {
  if (!m(e) || e.trim() === "")
    return t;
  const n = e.trim(), r = Ur[n] ?? t ?? null, o = h(`events.${n}`, r);
  return l(o, r);
}
function Xr(e, t = {}) {
  const n = t && s(t) ? { ...t } : {};
  return {
    eventKey: e,
    timestamp: Date.now(),
    ...n
  };
}
function L(e, t = {}) {
  if (!qr())
    return null;
  const n = Wr(e);
  if (!m(n) || n.trim() === "")
    return null;
  const r = Gr();
  if (!r || !c(r.dispatchEvent))
    return null;
  const o = new CustomEvent(n, {
    detail: Xr(e, t),
    bubbles: !0
  });
  return r.dispatchEvent(o), o;
}
function yt() {
  const e = h("pages", {});
  return s(e) ? e : {};
}
function Et(e) {
  const t = l(e, "");
  if (t === "")
    return null;
  const n = f(yt(), t, null);
  return s(n) ? n : null;
}
function ue() {
  return zr();
}
function ct() {
  return ie();
}
function w(e) {
  const t = l(e, ""), n = ue();
  return t === "" || n === null ? !1 : n === t;
}
function Yr(e) {
  const t = l(e, "");
  if (t === "")
    return null;
  const n = Et(t);
  return n ? l(n.pageKey, t) : t;
}
function Qr(e) {
  const t = Et(e);
  return t ? !!t.enabled : !1;
}
function Zr() {
  const e = ue();
  if (!m(e) || e.trim() === "")
    return null;
  const t = yt(), n = Object.entries(t);
  for (const [r, o] of n) {
    if (!s(o) || !Qr(r))
      continue;
    const i = Yr(r);
    if (!(!m(i) || i.trim() === "") && i === e)
      return {
        key: r,
        pageKey: i,
        config: o
      };
  }
  return null;
}
function eo() {
  const e = Zr();
  return e ? {
    key: e.key,
    pageKey: e.pageKey,
    element: ct(),
    config: e.config
  } : {
    key: null,
    pageKey: ue(),
    element: ct(),
    config: null
  };
}
const a = {
  booted: !1,
  booting: !1,
  context: null,
  modules: {},
  page: null,
  errors: []
};
function to({
  config: e,
  moduleRegistry: t,
  pageRegistry: n
}) {
  const r = eo(), o = pt(), i = ie();
  return {
    bootedAt: null,
    root: o,
    pageElement: i,
    page: r,
    config: e,
    moduleRegistry: t,
    pageRegistry: n,
    /**
     * Access the public runtime API.
     *
     * @returns {Object|null}
     */
    getRuntime() {
      return ht();
    },
    /**
     * Access the current runtime state snapshot.
     *
     * @returns {Object}
     */
    getState() {
      return _();
    },
    /**
     * Dispatch a configured AuthKit runtime event.
     *
     * @param {string} eventKey
     * @param {Object} [detail={}]
     * @returns {CustomEvent|null}
     */
    emit(u, g = {}) {
      return L(u, g);
    }
  };
}
function _() {
  return {
    booted: a.booted,
    booting: a.booting,
    context: a.context,
    modules: { ...a.modules },
    page: a.page,
    errors: [...a.errors]
  };
}
function wt() {
  return a.booted;
}
function no() {
  return a.booting;
}
function Mt(e, t, n) {
  a.errors.push({
    scope: e,
    key: t,
    error: n,
    timestamp: Date.now()
  });
}
function ro() {
  if (S(window))
    return null;
  const e = dt();
  return !m(e) || e.trim() === "" ? null : (s(window[e]) || (window[e] = {}), window[e]);
}
function oo(e, t, n) {
  const r = n?.[e] ?? null;
  if (!s(r))
    return null;
  const o = r.boot;
  if (!c(o))
    return null;
  try {
    const i = o(t), u = {
      key: e,
      booted: !0,
      result: i ?? null,
      error: null
    };
    return a.modules[e] = u, u;
  } catch (i) {
    Mt("module", e, i);
    const u = {
      key: e,
      booted: !1,
      result: null,
      error: i
    };
    return a.modules[e] = u, u;
  }
}
function io(e, t) {
  const n = f(e.config, "modules", {}), r = s(n) ? Object.entries(n) : [];
  for (const [o, i] of r)
    f(i, "enabled", !1) && s(t?.[o]) && oo(o, e, t);
  return { ...a.modules };
}
function so(e, t) {
  const n = e.page;
  if (!s(n) || !n.key)
    return a.page = null, null;
  const r = t?.[n.key] ?? null;
  if (!s(r) || !c(r.boot))
    return a.page = {
      key: n.key,
      pageKey: n.pageKey ?? null,
      booted: !1,
      result: null,
      error: null
    }, a.page;
  try {
    const o = r.boot(e);
    return a.page = {
      key: n.key,
      pageKey: n.pageKey ?? null,
      booted: !0,
      result: o ?? null,
      error: null
    }, e.emit("page_ready", {
      pageKey: n.pageKey ?? null,
      pageModuleKey: n.key
    }), a.page;
  } catch (o) {
    return Mt("page", n.key, o), a.page = {
      key: n.key,
      pageKey: n.pageKey ?? null,
      booted: !1,
      result: null,
      error: o
    }, a.page;
  }
}
function ht() {
  return {
    /**
     * Determine whether the runtime has completed boot.
     *
     * @returns {boolean}
     */
    isBooted() {
      return wt();
    },
    /**
     * Determine whether the runtime is currently booting.
     *
     * @returns {boolean}
     */
    isBooting() {
      return no();
    },
    /**
     * Return a current runtime state snapshot.
     *
     * @returns {Object}
     */
    state() {
      return _();
    },
    /**
     * Dispatch a configured AuthKit event manually.
     *
     * @param {string} eventKey
     * @param {Object} [detail={}]
     * @returns {CustomEvent|null}
     */
    emit(e, t = {}) {
      return L(e, t);
    }
  };
}
function uo() {
  if (S(window))
    return null;
  const e = ro();
  if (!e)
    return null;
  const t = ht();
  return e.runtime = t, t;
}
function lo({
  config: e,
  moduleRegistry: t,
  pageRegistry: n
}) {
  if (a.booted || a.booting)
    return _();
  a.booting = !0;
  const r = to({
    config: e,
    moduleRegistry: t,
    pageRegistry: n
  });
  return a.context = r, uo(), io(r, t), so(r, n), a.booted = !0, a.booting = !1, a.context = {
    ...r,
    bootedAt: Date.now()
  }, L("ready", {
    pageKey: f(a.context, "page.pageKey", null),
    pageModuleKey: f(a.context, "page.key", null),
    modules: Object.keys(a.modules)
  }), _();
}
const co = ["light", "dark", "system"];
function k(e) {
  const t = l(e, "");
  return co.includes(t);
}
function b(e, t = "system") {
  const n = l(e, t);
  return k(n) ? n : k(t) ? t : "system";
}
function Lt() {
  const e = h("ui.mode", "system");
  return b(e, "system");
}
function Tt() {
  return !S(window) && c(window.matchMedia);
}
function St() {
  if (!Tt())
    return "light";
  try {
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  } catch {
    return "light";
  }
}
function ao(e = null) {
  const t = Lt();
  return k(e) ? b(e, t) : t;
}
function le(e = null) {
  const t = Lt(), n = ao(e), r = St(), o = n === "system" ? r : b(n, "light");
  return {
    configuredMode: t,
    preferredMode: n,
    resolvedMode: o,
    systemMode: r
  };
}
function Ht() {
  return pt();
}
function fo(e) {
  const t = Ht();
  if (!t)
    return null;
  const n = b(e, "system");
  return t.setAttribute(
    "data-authkit-mode-preference",
    n
  ), n;
}
function mo(e) {
  const t = Ht();
  if (!t)
    return null;
  const n = l(e, "light") === "system" ? "light" : b(e, "light");
  return t.setAttribute("data-authkit-mode-resolved", n), t.setAttribute("data-authkit-mode", n), n;
}
function ce(e = null) {
  if (!s(e))
    return null;
  const t = fo(e.preferredMode), n = mo(e.resolvedMode);
  return {
    ...e,
    preferredMode: t ?? b(e.preferredMode, "system"),
    resolvedMode: n ?? "light"
  };
}
function _t(e = "local") {
  try {
    return S(window) ? null : e === "session" ? window.sessionStorage ?? null : window.localStorage ?? null;
  } catch {
    return null;
  }
}
function go(e, t = null, n = "local") {
  const r = l(e, "");
  if (r === "")
    return t;
  const o = _t(n);
  if (!o)
    return t;
  try {
    const i = o.getItem(r);
    return i !== null ? i : t;
  } catch {
    return t;
  }
}
function po(e, t, n = "local") {
  const r = l(e, "");
  if (r === "" || t !== null && s(t))
    return !1;
  const o = _t(n);
  if (!o)
    return !1;
  try {
    return o.setItem(r, String(t)), !0;
  } catch {
    return !1;
  }
}
const at = "authkit.ui.mode";
function kt() {
  return Or(
    h("ui.persistence.enabled", !0)
  );
}
function Ft() {
  return l(
    h("ui.persistence.storageKey", at),
    at
  );
}
function Co() {
  if (!kt())
    return null;
  const e = Ft();
  return l(
    go(e, null, "local"),
    null
  );
}
function bo() {
  const e = Co();
  return k(e) ? b(e, "system") : null;
}
function yo(e) {
  if (!kt() || !k(e))
    return !1;
  const t = Ft(), n = b(e, "system");
  return po(t, n, "local");
}
function Pt() {
  return l(
    h("ui.toggle.attribute", "data-authkit-theme-toggle"),
    "data-authkit-theme-toggle"
  );
}
function Eo() {
  return !!h("ui.toggle.allowSystem", !0);
}
function wo() {
  return Eo() ? ["light", "dark", "system"] : ["light", "dark"];
}
function ae() {
  return jr(Pt());
}
function fe() {
  return Kr();
}
function me() {
  return xr();
}
function Mo() {
  return {
    options: ae(),
    selects: fe(),
    cycleButtons: me()
  };
}
function vt(e) {
  const t = bt(e, Pt(), null);
  if (t === null)
    return null;
  const n = b(t, "");
  return n !== "" ? n : null;
}
function At(e) {
  const t = wo(), n = b(e, t[0]), r = t.indexOf(n);
  if (r === -1)
    return t[0];
  const o = (r + 1) % t.length;
  return t[o] ?? "light";
}
function ho(e, t = ae()) {
  const n = b(e, "system");
  t.forEach((r) => {
    const i = vt(r) === n;
    r.setAttribute("aria-pressed", i ? "true" : "false"), r.setAttribute("data-authkit-theme-toggle-active", i ? "true" : "false");
  });
}
function Lo(e, t = fe()) {
  const n = b(e, "system");
  t.forEach((r) => {
    r.value = n;
  });
}
function To(e, t = me()) {
  const n = b(e, "system"), r = At(n);
  t.forEach((o) => {
    o.setAttribute("data-authkit-theme-toggle-current", n), o.setAttribute("data-authkit-theme-toggle-next", r), o.setAttribute(
      "aria-label",
      `Toggle appearance mode (current: ${n}, next: ${r})`
    ), o.setAttribute(
      "title",
      `Current: ${n}. Next: ${r}.`
    );
  });
}
function de(e) {
  const t = Mo();
  ho(e, t.options), Lo(e, t.selects), To(e, t.cycleButtons);
}
function So(e, t = ae()) {
  return c(e) ? t.map((n) => se(n, "click", (r) => {
    r.preventDefault();
    const o = vt(n);
    o !== null && e(o, {
      source: "option",
      element: n,
      event: r
    });
  })) : [];
}
function Ho(e, t = fe()) {
  return c(e) ? t.map((n) => se(n, "change", (r) => {
    const o = b(n.value, "");
    o !== "" && e(o, {
      source: "select",
      element: n,
      event: r
    });
  })) : [];
}
function _o(e, t, n = me()) {
  return !c(e) || !c(t) ? [] : n.map((r) => se(r, "click", (o) => {
    o.preventDefault();
    const i = t(), u = At(i);
    e(u, {
      source: "cycle",
      element: r,
      event: o,
      currentMode: b(i, "system"),
      nextMode: u
    });
  }));
}
function ko(e, t = {}) {
  if (!c(e))
    return () => {
    };
  const n = c(t.getCurrentMode) ? t.getCurrentMode : () => "system", r = [
    ...So(e),
    ...Ho(e),
    ..._o(e, n)
  ];
  return () => {
    r.forEach((o) => {
      c(o) && o();
    });
  };
}
const Fo = "(prefers-color-scheme: dark)";
function Po() {
  if (!Tt())
    return null;
  try {
    return window.matchMedia(Fo);
  } catch {
    return null;
  }
}
function vo(e) {
  return !!(e && c(e.addEventListener) && c(e.removeEventListener));
}
function Ao(e) {
  return !!(e && c(e.addListener) && c(e.removeListener));
}
function $o(e) {
  if (!c(e))
    return () => {
    };
  const t = Po();
  return t ? vo(t) ? (t.addEventListener("change", e), () => {
    t.removeEventListener("change", e);
  }) : Ao(t) ? (t.addListener(e), () => {
    t.removeListener(e);
  }) : () => {
  } : () => {
  };
}
function Oo(e, t) {
  return !c(e) || !c(t) ? () => {
  } : $o((n) => {
    const r = b(e(), "system");
    if (r !== "system")
      return;
    const o = St();
    t({
      preferredMode: r,
      resolvedMode: o,
      systemMode: o,
      event: n
    });
  });
}
const C = {
  preferredMode: null,
  resolvedMode: null,
  systemMode: null
};
function F() {
  return C.preferredMode;
}
function $t() {
  return C.resolvedMode;
}
function B(e) {
  !e || !s(e) || (C.preferredMode = e.preferredMode ?? null, C.resolvedMode = e.resolvedMode ?? null, C.systemMode = e.systemMode ?? null);
}
function ge(e, t = {}) {
  const n = le(e), r = ce(n);
  return B(r), yo(C.preferredMode), de(C.preferredMode), L("theme_changed", {
    preferredMode: C.preferredMode,
    resolvedMode: C.resolvedMode,
    systemMode: C.systemMode,
    meta: t
  }), r;
}
function Ot() {
  return ko(
    (e, t) => {
      ge(e, t);
    },
    {
      getCurrentMode: () => F()
    }
  );
}
function Bt() {
  return Oo(
    () => F(),
    ({ systemMode: e }) => {
      const t = le(F()), n = ce(t);
      B(n), de(C.preferredMode), L("theme_changed", {
        preferredMode: C.preferredMode,
        resolvedMode: C.resolvedMode,
        systemMode: e,
        meta: { source: "system" }
      });
    }
  );
}
function Bo(e) {
  const t = bo(), n = le(t), r = ce(n);
  return B(r), de(C.preferredMode), Ot(), Bt(), L("theme_ready", {
    preferredMode: C.preferredMode,
    resolvedMode: C.resolvedMode,
    systemMode: C.systemMode
  }), {
    getPreferredMode: F,
    getResolvedMode: $t,
    setPreferredMode: ge
  };
}
const Io = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Bo,
  getPreferredMode: F,
  getResolvedMode: $t,
  initSystemListener: Bt,
  initThemeToggles: Ot,
  setPreferredMode: ge,
  updateThemeState: B
}, Symbol.toStringTag, { value: "Module" }));
function It(e = null) {
  return {
    form: Rr(e) ? e : null,
    submitting: !1,
    submitted: !1,
    lastResult: null,
    fieldErrors: {},
    message: null,
    meta: {}
  };
}
function y(e) {
  return s(e) && "submitting" in e && "submitted" in e && "lastResult" in e && "fieldErrors" in e && "message" in e && "meta" in e;
}
function Ro(e) {
  return y(e) ? e.submitting === !0 : !1;
}
function I(e, t = !0) {
  if (!y(e))
    return null;
  const n = t === !0;
  return e.submitting = n, n || (e.submitted = !0), e;
}
function pe(e, t = null) {
  return y(e) ? (e.lastResult = s(t) ? { ...t } : null, e) : null;
}
function zo(e) {
  return y(e) && s(e.lastResult) ? { ...e.lastResult } : null;
}
function Rt(e, t = {}) {
  return y(e) ? (e.fieldErrors = ye(t), e) : null;
}
function jo(e) {
  return y(e) ? zt(e.fieldErrors) : {};
}
function Ko(e) {
  return y(e) ? Object.keys(e.fieldErrors).length > 0 : !1;
}
function Ce(e) {
  return y(e) ? (e.fieldErrors = {}, e) : null;
}
function be(e, t = null) {
  return y(e) ? (e.message = l(t, null), e) : null;
}
function xo(e) {
  return y(e) ? l(e.message, null) : null;
}
function R(e) {
  return y(e) ? (e.message = null, e) : null;
}
function z(e, t = {}) {
  return y(e) ? (e.meta = {
    ...e.meta,
    ...s(t) ? t : {}
  }, e) : null;
}
function Vo(e) {
  return y(e) ? s(e.meta) ? { ...e.meta } : {} : {};
}
function Do(e) {
  return y(e) ? (e.lastResult = null, e.fieldErrors = {}, e.message = null, e) : null;
}
function No(e) {
  if (!y(e))
    return null;
  const t = e.form ?? null;
  return e.form = t, e.submitting = !1, e.submitted = !1, e.lastResult = null, e.fieldErrors = {}, e.message = null, e.meta = {}, e;
}
function ye(e) {
  return s(e) ? Object.entries(e).reduce((t, [n, r]) => {
    const o = l(n, "");
    if (o === "")
      return t;
    const i = Array.isArray(r) ? r.map((u) => l(u, "")).filter((u) => u !== "") : [l(r, "")].filter((u) => u !== "");
    return i.length === 0 || (t[o] = i), t;
  }, {}) : {};
}
function zt(e) {
  const t = ye(e);
  return Object.entries(t).reduce((n, [r, o]) => (n[r] = [...o], n), {});
}
function Uo() {
  const e = Ct('meta[name="csrf-token"]');
  if (!e)
    return null;
  const t = e.getAttribute("content");
  return l(t, null);
}
function jt(e, t) {
  if (!s(e) || !m(t) || t.trim() === "")
    return !1;
  const n = t.trim().toLowerCase();
  return Object.keys(e).some((r) => r.toLowerCase() === n);
}
function ne(e = {}) {
  return s(e) ? Object.entries(e).reduce((t, [n, r]) => (!m(n) || n.trim() === "" || r == null || (t[n.trim()] = String(r)), t), {}) : {};
}
function qo(e = {}) {
  const t = ne({
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest"
  }), n = Uo();
  return n !== null && !jt(t, "X-CSRF-TOKEN") && (t["X-CSRF-TOKEN"] = n), {
    ...t,
    ...ne(e)
  };
}
function Jo(e) {
  return JSON.stringify(e ?? {});
}
function Go(e) {
  if (e instanceof FormData)
    return e;
  if (e instanceof HTMLFormElement)
    return new FormData(e);
  const t = new FormData();
  return s(e) && Object.entries(e).forEach(([n, r]) => {
    if (!(!m(n) || n.trim() === "")) {
      if (Array.isArray(r)) {
        r.forEach((o) => {
          s(o) || t.append(n, o ?? "");
        });
        return;
      }
      s(r) || t.append(n, r ?? "");
    }
  }), t;
}
function Wo(e) {
  return (e?.headers?.get("content-type") ?? "").toLowerCase().includes("application/json");
}
async function Xo(e) {
  if (!e || e.status === 204)
    return null;
  if (Wo(e))
    try {
      return await e.json();
    } catch {
      return null;
    }
  try {
    return await e.text();
  } catch {
    return null;
  }
}
async function Yo(e) {
  const t = await Xo(e);
  return {
    ok: e.ok,
    status: e.status,
    statusText: e.statusText,
    redirected: e.redirected,
    url: e.url,
    headers: e.headers,
    data: t,
    response: e
  };
}
function Qo(e = {}) {
  const t = l(f(e, "method", "GET"), "GET").toUpperCase(), n = !!f(e, "asJson", !1), r = f(e, "credentials", "same-origin"), o = f(e, "signal", void 0), i = f(e, "mode", void 0), u = f(e, "redirect", void 0), g = ne(f(e, "headers", {})), v = f(e, "body", null), ee = qo(g);
  let te;
  return t !== "GET" && t !== "HEAD" && (n ? (jt(ee, "Content-Type") || (ee["Content-Type"] = "application/json"), te = Jo(v)) : v != null && (te = Go(v))), {
    method: t,
    headers: ee,
    body: te,
    credentials: r,
    signal: o,
    mode: i,
    redirect: u
  };
}
async function Zo(e, t = {}) {
  const n = l(e, "");
  if (n === "")
    throw new Error("AuthKit HTTP request requires a valid URL.");
  const r = Qo(t), o = await fetch(n, r);
  return Yo(o);
}
function Kt(e) {
  if (!P(e))
    return "GET";
  const t = l(e.method, "") || l(p(e, "method", ""), "");
  return t !== "" ? t.toUpperCase() : "GET";
}
function xt(e) {
  if (!P(e))
    return re();
  const t = l(e.action, "") || l(p(e, "action", ""), "");
  return t !== "" ? t : re();
}
function P(e) {
  return oe(e) && e instanceof HTMLFormElement;
}
function Ee(e) {
  return P(e) ? new FormData(e) : new FormData();
}
function ei(e) {
  return we(Ee(e));
}
function we(e) {
  if (!(e instanceof FormData))
    return {};
  const t = {};
  for (const [n, r] of e.entries()) {
    const o = l(n, "");
    if (o !== "") {
      if (!Object.prototype.hasOwnProperty.call(t, o)) {
        t[o] = r;
        continue;
      }
      Array.isArray(t[o]) || (t[o] = [t[o]]), t[o].push(r);
    }
  }
  return t;
}
function Vt(e) {
  const t = P(e) ? e : null, n = Ee(t);
  return {
    form: t,
    action: xt(t),
    method: Kt(t),
    formData: n,
    data: we(n)
  };
}
function ti(e, t = {}) {
  const n = s(e) ? e : {}, r = s(t) ? t : {};
  return {
    ...n,
    ...r
  };
}
function ni(e, t, n = null) {
  if (!s(e))
    return n;
  const r = l(t, "");
  return r === "" ? n : Object.prototype.hasOwnProperty.call(e, r) ? e[r] : n;
}
function ri(e, t) {
  if (!s(e))
    return !1;
  const n = l(t, "");
  return n === "" ? !1 : Object.prototype.hasOwnProperty.call(e, n);
}
function re() {
  return S(window) || !m(window.location?.href) ? "" : l(window.location.href, "");
}
function oi(e = {}) {
  const t = f(e, "redirect.url", null);
  return m(t) && t.trim() !== "" ? t : null;
}
function ii(e = {}, t = "Operation completed.") {
  const n = e?.message;
  return m(n) && n.trim() !== "" ? n : t;
}
function Dt(e = {}) {
  const t = s(e?.data) ? e.data : {};
  return {
    ok: !0,
    status: Number(e?.status ?? t?.status ?? 200),
    message: ii(t),
    redirectUrl: oi(t),
    data: t
  };
}
function Nt(e, t) {
  return I(e, !1), pe(e, t), Ce(e), R(e), be(e, t.message), z(e, {
    status: t.status,
    outcome: "success",
    redirectUrl: t.redirectUrl
  }), e;
}
function Ut(e, t, n, r = {}) {
  const o = Dt(r);
  Nt(n, o);
  const i = {
    form: t,
    status: o.status,
    message: o.message,
    redirectUrl: o.redirectUrl,
    result: o.data
  };
  return e && c(e.emit) ? e.emit("form_success", i) : L("form_success", i), o;
}
function qt(e = {}) {
  const t = {}, n = f(e, "payload.fields", null);
  s(n) && Object.entries(n).forEach(([i, u]) => {
    !m(i) || i.trim() === "" || (t[i] = Array.isArray(u) ? u.map((g) => String(g)).filter((g) => g.trim() !== "") : [String(u)].filter((g) => g.trim() !== ""));
  });
  const r = e?.errors ?? null;
  return s(r) && !Array.isArray(r) && Object.entries(r).forEach(([i, u]) => {
    !m(i) || i.trim() === "" || t[i] || (t[i] = Array.isArray(u) ? u.map((g) => String(g)).filter((g) => g.trim() !== "") : [String(u)].filter((g) => g.trim() !== ""));
  }), (Array.isArray(r) ? r : []).forEach((i) => {
    const u = s(i) ? i.field : null, g = s(i) ? i.message : null;
    !m(u) || u.trim() === "" || !m(g) || g.trim() === "" || (Array.isArray(t[u]) || (t[u] = []), t[u].push(g));
  }), t;
}
function Jt(e = {}, t = {}, n = "Something went wrong.") {
  const r = e?.message;
  if (m(r) && r.trim() !== "")
    return r;
  for (const o of Object.values(t))
    if (Array.isArray(o) && o.length > 0) {
      const i = o[0];
      if (m(i) && i.trim() !== "")
        return i;
    }
  return n;
}
function Gt(e = {}) {
  const t = s(e?.data) ? e.data : {}, n = qt(t);
  return {
    ok: !1,
    status: Number(e?.status ?? t?.status ?? 422),
    message: Jt(t, n),
    fieldErrors: n,
    data: t
  };
}
function Wt(e, t) {
  return I(e, !1), pe(e, t), R(e), be(e, t.message), Rt(e, t.fieldErrors), z(e, {
    status: t.status,
    outcome: "error"
  }), e;
}
function $(e, t, n, r = {}) {
  const o = Gt(r);
  Wt(n, o);
  const i = {
    form: t,
    status: o.status,
    message: o.message,
    errors: o.fieldErrors,
    result: o.data
  };
  return e && c(e.emit) ? e.emit("form_error", i) : L("form_error", i), o;
}
function Xt(e = {}) {
  return f(e, "asJson", !1) === !0;
}
function Yt(e = {}) {
  return {
    asJson: Xt(e)
  };
}
function Qt(e, t = {}) {
  const n = l(f(t, "url", ""), "");
  return n !== "" ? n : l(f(e, "action", ""), "");
}
function Zt(e, t = {}) {
  const n = l(f(t, "method", ""), "");
  return n !== "" ? n.toUpperCase() : l(f(e, "method", "POST"), "POST").toUpperCase();
}
function en(e = {}) {
  const t = f(e, "headers", {});
  return s(t) ? { ...t } : {};
}
function tn(e = {}) {
  return {
    credentials: f(e, "credentials", "same-origin"),
    signal: f(e, "signal", void 0),
    mode: f(e, "mode", void 0),
    redirect: f(e, "redirect", void 0)
  };
}
function nn(e, t) {
  return t.asJson === !0 ? f(e, "data", {}) : f(e, "formData", null);
}
function rn(e, t = {}) {
  const n = Vt(e), r = Yt(t);
  return {
    form: f(n, "form", null),
    serializedForm: n,
    url: Qt(n, t),
    method: Zt(n, t),
    body: nn(n, r),
    asJson: r.asJson === !0,
    headers: en(t),
    ...tn(t)
  };
}
function on(e, t) {
  return {
    form: e,
    url: t.url,
    method: t.method,
    asJson: t.asJson === !0,
    data: f(t, "serializedForm.data", {})
  };
}
function sn(e, t, n) {
  const r = on(t, n);
  return e && c(e.emit) ? e.emit("form_before_submit", r) : L("form_before_submit", r);
}
function un(e, t) {
  return Ce(e), R(e), z(e, {
    requestUrl: t.url,
    requestMethod: t.method,
    asJson: t.asJson === !0,
    outcome: null
  }), I(e, !0), e;
}
function ln(e) {
  return {
    status: 0,
    data: {
      ok: !1,
      status: 0,
      message: m(e?.message) && e.message.trim() !== "" ? e.message : "Unable to submit the form. Please try again.",
      errors: []
    }
  };
}
async function cn(e, t, n, r = {}) {
  const o = rn(t, r);
  if (o.url === "")
    return $(e, t, n, {
      status: 0,
      data: {
        ok: !1,
        status: 0,
        message: "Form submission requires a valid action URL."
      }
    });
  if (c(r.beforeSubmit)) {
    const i = await r.beforeSubmit(t, o, n, e);
    s(i) && Object.assign(o, i);
  }
  sn(e, t, o), un(n, o);
  try {
    const i = await Zo(o.url, {
      method: o.method,
      body: o.body,
      asJson: o.asJson,
      headers: o.headers,
      credentials: o.credentials,
      signal: o.signal,
      mode: o.mode,
      redirect: o.redirect
    }), u = i?.ok ? Ut(e, t, n, i) : $(e, t, n, i);
    return c(r.afterSubmit) && await r.afterSubmit(u, t, n, e), u;
  } catch (i) {
    const u = $(
      e,
      t,
      n,
      ln(i)
    );
    return c(r.afterSubmit) && await r.afterSubmit(u, t, n, e), u;
  }
}
const O = /* @__PURE__ */ new WeakMap();
function H(e) {
  return e instanceof HTMLFormElement;
}
function si(e) {
  return H(e) ? O.has(e) : !1;
}
function Me(e) {
  return H(e) ? O.get(e) ?? null : null;
}
function he(e) {
  if (!H(e))
    return !1;
  const t = Me(e);
  return !t || !c(t.cleanup) ? !1 : (t.cleanup(), !0);
}
function Le(e, t) {
  if (!H(e) || !c(t))
    return () => {
    };
  const n = Me(e);
  if (n && c(n.cleanup))
    return n.cleanup;
  const r = It(e), o = (u) => {
    u.preventDefault(), t(u, e, r);
  };
  e.addEventListener("submit", o);
  const i = () => {
    e.removeEventListener("submit", o), O.delete(e);
  };
  return O.set(e, {
    handler: o,
    cleanup: i,
    state: r
  }), i;
}
function an(e, t) {
  if (!Array.isArray(e) || !c(t))
    return () => {
    };
  const n = e.filter((r) => H(r)).map((r) => Le(r, t));
  return () => {
    n.forEach((r) => {
      c(r) && r();
    });
  };
}
function ui(e) {
  return Array.isArray(e) ? e.reduce((t, n) => he(n) ? t + 1 : t, 0) : 0;
}
function li(e, t) {
  return !H(e) || !c(t) ? () => {
  } : (he(e), Le(e, t));
}
function ft(e) {
  const t = String(
    e?.config?.forms?.ajaxAttribute ?? e?.config?.forms?.ajax?.attribute ?? "data-authkit-ajax"
  ), n = Vr(t), r = an(n, (o, i, u) => {
    cn(e, i, u, {
      event: o
    });
  });
  return {
    forms: n,
    count: n.length,
    cleanup: r
  };
}
const ci = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  applyErrorState: Wt,
  applySuccessState: Nt,
  beginSubmitState: un,
  bindForm: Le,
  bindForms: an,
  boot: ft,
  bootForms: ft,
  buildSerializedForm: Vt,
  buildSubmitRequest: rn,
  clearFeedbackState: Do,
  clearFieldErrors: Ce,
  clearMessage: R,
  cloneFieldErrors: zt,
  createBeforeSubmitDetail: on,
  createFormState: It,
  createTransportErrorResult: ln,
  emitBeforeSubmit: sn,
  formDataToObject: we,
  getCurrentUrl: re,
  getFieldErrors: jo,
  getFormAction: xt,
  getFormBinding: Me,
  getFormMethod: Kt,
  getLastResult: zo,
  getMessage: xo,
  getMeta: Vo,
  getSerializedValue: ni,
  getSubmitSerialization: Yt,
  handleError: $,
  handleSuccess: Ut,
  hasFieldErrors: Ko,
  hasSerializedValue: ri,
  isFormBound: si,
  isFormElement: H,
  isFormState: y,
  isSerializedFormElement: P,
  isSubmitting: Ro,
  mergeSerializedData: ti,
  normalizeErrorFieldErrors: qt,
  normalizeErrorResult: Gt,
  normalizeStateFieldErrors: ye,
  normalizeSuccessResult: Dt,
  rebindForm: li,
  resetFormState: No,
  resolveErrorMessage: Jt,
  resolveSubmitBody: nn,
  resolveSubmitHeaders: en,
  resolveSubmitMethod: Zt,
  resolveSubmitTransport: tn,
  resolveSubmitUrl: Qt,
  serializeForm: ei,
  serializeFormToFormData: Ee,
  setFieldErrors: Rt,
  setLastResult: pe,
  setMessage: be,
  setMeta: z,
  setSubmitting: I,
  shouldSubmitAsJson: Xt,
  submitForm: cn,
  unbindForm: he,
  unbindForms: ui
}, Symbol.toStringTag, { value: "Module" }));
const ai = Object.freeze({
  theme: Io,
  forms: ci
});
function fi() {
  return { ...ai };
}
function Te(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function Se(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function He(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function fn(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function _e(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !Te(e);
}
function mn(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function dn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function gn(e = []) {
  for (const t of e)
    if (_e(t) && !(He(t) || Se(t)))
      return t;
  return null;
}
function pn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = dn(e), r = mn(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => _e(o)),
    hiddenControls: r.filter((o) => Te(o)),
    passwordControls: r.filter((o) => He(o)),
    checkboxControls: r.filter((o) => Se(o)),
    submitControls: r.filter((o) => fn(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    identityControl: gn(r)
  };
}
function mi(e) {
  return !s(e) || !w("login") ? null : {
    key: "login",
    ...pn(e)
  };
}
const di = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: mi,
  getFormControls: mn,
  getIdentityControl: gn,
  getLoginForm: dn,
  getLoginPageElements: pn,
  isCheckboxControl: Se,
  isHiddenControl: Te,
  isPasswordControl: He,
  isSubmitControl: fn,
  isVisibleFormControl: _e
}, Symbol.toStringTag, { value: "Module" }));
function ke(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function Fe(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Pe(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function Cn(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function ve(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !ke(e);
}
function bn(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function yn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function Ae(e = []) {
  return e.filter((t) => !(!ve(t) || Pe(t) || Fe(t)));
}
function j(e = []) {
  return e.filter((t) => Pe(t));
}
function En(e = []) {
  return Ae(e)[0] ?? null;
}
function wn(e = []) {
  return j(e)[0] ?? null;
}
function Mn(e = []) {
  return j(e)[1] ?? null;
}
function hn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = yn(e), r = bn(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => ve(o)),
    hiddenControls: r.filter((o) => ke(o)),
    checkboxControls: r.filter((o) => Fe(o)),
    passwordControls: j(r),
    submitControls: r.filter((o) => Cn(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    identityLikeControls: Ae(r),
    primaryIdentityControl: En(r),
    primaryPasswordControl: wn(r),
    passwordConfirmationControl: Mn(r)
  };
}
function gi(e) {
  return !s(e) || !w("register") ? null : {
    key: "register",
    ...hn(e)
  };
}
const pi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: gi,
  getFormControls: bn,
  getIdentityLikeControls: Ae,
  getPasswordConfirmationControl: Mn,
  getPasswordControls: j,
  getPrimaryIdentityControl: En,
  getPrimaryPasswordControl: wn,
  getRegisterForm: yn,
  getRegisterPageElements: hn,
  isCheckboxControl: Fe,
  isHiddenControl: ke,
  isPasswordControl: Pe,
  isSubmitControl: Cn,
  isVisibleFormControl: ve
}, Symbol.toStringTag, { value: "Module" }));
function K(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function $e(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Oe(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function Ln(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function x(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !K(e);
}
function Tn(e) {
  if (!x(e))
    return !1;
  const t = String(p(e, "autocomplete", "") || "").toLowerCase(), n = String(p(e, "inputmode", "") || "").toLowerCase(), r = e instanceof Element ? e.className : "";
  return t === "one-time-code" || n === "numeric" || String(r).includes("authkit-otp");
}
function Sn(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function Hn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function Be(e = []) {
  return e.filter((t) => !(!x(t) || Oe(t) || $e(t)));
}
function _n(e = []) {
  return e.filter((t) => Tn(t));
}
function kn(e = []) {
  return Be(e)[0] ?? null;
}
function Fn(e = []) {
  return e.filter((t) => K(t));
}
function Pn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Hn(e), r = Sn(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => x(o)),
    hiddenControls: r.filter((o) => K(o)),
    checkboxControls: r.filter((o) => $e(o)),
    passwordControls: r.filter((o) => Oe(o)),
    submitControls: r.filter((o) => Ln(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    verificationControls: Be(r),
    otpLikeControls: _n(r),
    primaryVerificationControl: kn(r),
    contextControls: Fn(r)
  };
}
function Ci(e) {
  return !s(e) || !w("two_factor_challenge") ? null : {
    key: "two_factor_challenge",
    ...Pn(e)
  };
}
const bi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Ci,
  getContextControls: Fn,
  getFormControls: Sn,
  getOtpLikeControls: _n,
  getPrimaryVerificationControl: kn,
  getTwoFactorChallengeForm: Hn,
  getTwoFactorChallengePageElements: Pn,
  getVerificationControls: Be,
  isCheckboxControl: $e,
  isHiddenControl: K,
  isOtpLikeControl: Tn,
  isPasswordControl: Oe,
  isSubmitControl: Ln,
  isVisibleFormControl: x
}, Symbol.toStringTag, { value: "Module" }));
function V(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function D(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Ie(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function vn(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function Re(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !V(e);
}
function An(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function $n(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function ze(e = []) {
  return e.filter((t) => !(!Re(t) || Ie(t) || D(t)));
}
function On(e = []) {
  return ze(e)[0] ?? null;
}
function Bn(e = []) {
  return e.filter((t) => V(t));
}
function In(e = []) {
  return e.filter((t) => D(t));
}
function Rn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = $n(e), r = An(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => Re(o)),
    hiddenControls: r.filter((o) => V(o)),
    checkboxControls: r.filter((o) => D(o)),
    passwordControls: r.filter((o) => Ie(o)),
    submitControls: r.filter((o) => vn(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    recoveryControls: ze(r),
    primaryRecoveryControl: On(r),
    contextControls: Bn(r),
    rememberLikeControls: In(r)
  };
}
function yi(e) {
  return !s(e) || !w("two_factor_recovery") ? null : {
    key: "two_factor_recovery",
    ...Rn(e)
  };
}
const Ei = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: yi,
  getContextControls: Bn,
  getFormControls: An,
  getPrimaryRecoveryControl: On,
  getRecoveryControls: ze,
  getRememberLikeControls: In,
  getTwoFactorRecoveryForm: $n,
  getTwoFactorRecoveryPageElements: Rn,
  isCheckboxControl: D,
  isHiddenControl: V,
  isPasswordControl: Ie,
  isSubmitControl: vn,
  isVisibleFormControl: Re
}, Symbol.toStringTag, { value: "Module" }));
function N(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function je(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Ke(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function zn(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function xe(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !N(e);
}
function jn(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function Kn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function xn(e = []) {
  return e.filter((t) => N(t));
}
function Ve(e = []) {
  return e.filter((t) => !(!xe(t) || Ke(t) || je(t)));
}
function Vn(e = []) {
  return Ve(e)[0] ?? null;
}
function Dn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Kn(e), r = jn(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => xe(o)),
    hiddenControls: r.filter((o) => N(o)),
    checkboxControls: r.filter((o) => je(o)),
    passwordControls: r.filter((o) => Ke(o)),
    submitControls: r.filter((o) => zn(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    noticeControls: Ve(r),
    primaryNoticeControl: Vn(r),
    contextControls: xn(r)
  };
}
function wi(e) {
  return !s(e) || !w("email_verification_notice") ? null : {
    key: "email_verification_notice",
    ...Dn(e)
  };
}
const Mi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: wi,
  getContextControls: xn,
  getEmailVerificationNoticeForm: Kn,
  getEmailVerificationNoticePageElements: Dn,
  getFormControls: jn,
  getNoticeControls: Ve,
  getPrimaryNoticeControl: Vn,
  isCheckboxControl: je,
  isHiddenControl: N,
  isPasswordControl: Ke,
  isSubmitControl: zn,
  isVisibleFormControl: xe
}, Symbol.toStringTag, { value: "Module" }));
function U(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function De(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Ne(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function Nn(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function q(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !U(e);
}
function Un(e) {
  if (!q(e))
    return !1;
  const t = String(p(e, "autocomplete", "") || "").toLowerCase(), n = String(p(e, "inputmode", "") || "").toLowerCase(), r = e instanceof Element ? e.className : "";
  return t === "one-time-code" || n === "numeric" || String(r).includes("authkit-otp");
}
function qn(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function Jn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function Gn(e = []) {
  return e.filter((t) => U(t));
}
function Ue(e = []) {
  return e.filter((t) => !(!q(t) || Ne(t) || De(t)));
}
function Wn(e = []) {
  return e.filter((t) => Un(t));
}
function Xn(e = []) {
  return Ue(e)[0] ?? null;
}
function Yn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Jn(e), r = qn(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => q(o)),
    hiddenControls: r.filter((o) => U(o)),
    checkboxControls: r.filter((o) => De(o)),
    passwordControls: r.filter((o) => Ne(o)),
    submitControls: r.filter((o) => Nn(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    verificationControls: Ue(r),
    otpLikeControls: Wn(r),
    primaryVerificationControl: Xn(r),
    contextControls: Gn(r)
  };
}
function hi(e) {
  return !s(e) || !w("email_verification_token") ? null : {
    key: "email_verification_token",
    ...Yn(e)
  };
}
const Li = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: hi,
  getContextControls: Gn,
  getEmailVerificationTokenForm: Jn,
  getEmailVerificationTokenPageElements: Yn,
  getFormControls: qn,
  getOtpLikeControls: Wn,
  getPrimaryVerificationControl: Xn,
  getVerificationControls: Ue,
  isCheckboxControl: De,
  isHiddenControl: U,
  isOtpLikeControl: Un,
  isPasswordControl: Ne,
  isSubmitControl: Nn,
  isVisibleFormControl: q
}, Symbol.toStringTag, { value: "Module" }));
function qe(e) {
  return e instanceof HTMLElement ? d("a[href]", e).filter((t) => t instanceof HTMLAnchorElement) : [];
}
function Qn(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null;
  return E(t || document).filter((r) => r instanceof HTMLFormElement);
}
function Zn(e) {
  return qe(e)[0] ?? null;
}
function er(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Qn(e), r = qe(t instanceof HTMLElement ? t : null);
  return {
    page: t instanceof HTMLElement ? t : null,
    forms: n,
    formCount: n.length,
    links: r,
    primaryActionLink: Zn(t instanceof HTMLElement ? t : null)
  };
}
function Ti(e) {
  return !s(e) || !w("email_verification_success") ? null : {
    key: "email_verification_success",
    ...er(e)
  };
}
const Si = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Ti,
  getEmailVerificationSuccessPageElements: er,
  getPrimaryActionLink: Zn,
  getSuccessPageForms: Qn,
  getSuccessPageLinks: qe
}, Symbol.toStringTag, { value: "Module" }));
function J(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function Je(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Ge(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function tr(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function We(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !J(e);
}
function nr(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function rr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function or(e = []) {
  return e.filter((t) => J(t));
}
function Xe(e = []) {
  return e.filter((t) => !(!We(t) || Ge(t) || Je(t)));
}
function ir(e = []) {
  return Xe(e)[0] ?? null;
}
function sr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = rr(e), r = nr(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => We(o)),
    hiddenControls: r.filter((o) => J(o)),
    checkboxControls: r.filter((o) => Je(o)),
    passwordControls: r.filter((o) => Ge(o)),
    submitControls: r.filter((o) => tr(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    requestControls: Xe(r),
    primaryRequestControl: ir(r),
    contextControls: or(r)
  };
}
function Hi(e) {
  return !s(e) || !w("password_forgot") ? null : {
    key: "password_forgot",
    ...sr(e)
  };
}
const _i = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Hi,
  getContextControls: or,
  getFormControls: nr,
  getPasswordForgotForm: rr,
  getPasswordForgotPageElements: sr,
  getPrimaryRequestControl: ir,
  getRequestControls: Xe,
  isCheckboxControl: Je,
  isHiddenControl: J,
  isPasswordControl: Ge,
  isSubmitControl: tr,
  isVisibleFormControl: We
}, Symbol.toStringTag, { value: "Module" }));
function G(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function Ye(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function Qe(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function ur(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function Ze(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !G(e);
}
function lr(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function cr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function ar(e = []) {
  return e.filter((t) => G(t));
}
function et(e = []) {
  return e.filter((t) => !(!Ze(t) || Qe(t) || Ye(t)));
}
function fr(e = []) {
  return et(e)[0] ?? null;
}
function mr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = cr(e), r = lr(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => Ze(o)),
    hiddenControls: r.filter((o) => G(o)),
    checkboxControls: r.filter((o) => Ye(o)),
    passwordControls: r.filter((o) => Qe(o)),
    submitControls: r.filter((o) => ur(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    resendControls: et(r),
    primaryResendControl: fr(r),
    contextControls: ar(r)
  };
}
function ki(e) {
  return !s(e) || !w("password_forgot_sent") ? null : {
    key: "password_forgot_sent",
    ...mr(e)
  };
}
const Fi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: ki,
  getContextControls: ar,
  getFormControls: lr,
  getPasswordForgotSentForm: cr,
  getPasswordForgotSentPageElements: mr,
  getPrimaryResendControl: fr,
  getResendControls: et,
  isCheckboxControl: Ye,
  isHiddenControl: G,
  isPasswordControl: Qe,
  isSubmitControl: ur,
  isVisibleFormControl: Ze
}, Symbol.toStringTag, { value: "Module" }));
function W(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function tt(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function nt(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function dr(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function rt(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !W(e);
}
function gr(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function pr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function Cr(e = []) {
  return e.filter((t) => W(t));
}
function X(e = []) {
  return e.filter((t) => nt(t));
}
function br(e = []) {
  return X(e)[0] ?? null;
}
function yr(e = []) {
  return X(e)[1] ?? null;
}
function Er(e = []) {
  return e.filter((t) => !(!rt(t) || nt(t) || tt(t)));
}
function wr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = pr(e), r = gr(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => rt(o)),
    hiddenControls: r.filter((o) => W(o)),
    checkboxControls: r.filter((o) => tt(o)),
    passwordControls: X(r),
    submitControls: r.filter((o) => dr(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    visibleResetControls: Er(r),
    primaryPasswordControl: br(r),
    passwordConfirmationControl: yr(r),
    contextControls: Cr(r)
  };
}
function Pi(e) {
  return !s(e) || !w("password_reset") ? null : {
    key: "password_reset",
    ...wr(e)
  };
}
const vi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Pi,
  getContextControls: Cr,
  getFormControls: gr,
  getPasswordConfirmationControl: yr,
  getPasswordControls: X,
  getPasswordResetForm: pr,
  getPasswordResetPageElements: wr,
  getPrimaryPasswordControl: br,
  getVisibleResetControls: Er,
  isCheckboxControl: tt,
  isHiddenControl: W,
  isPasswordControl: nt,
  isSubmitControl: dr,
  isVisibleFormControl: rt
}, Symbol.toStringTag, { value: "Module" }));
function Y(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "hidden";
}
function ot(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "checkbox";
}
function it(e) {
  return e instanceof HTMLInputElement && String(e.type).toLowerCase() === "password";
}
function Mr(e) {
  if (e instanceof HTMLButtonElement)
    return (p(e, "type", "submit") || "submit").toLowerCase() === "submit";
  if (e instanceof HTMLInputElement) {
    const t = String(e.type || "").toLowerCase();
    return t === "submit" || t === "image";
  }
  return !1;
}
function Q(e) {
  return !(e instanceof HTMLInputElement) && !(e instanceof HTMLSelectElement) && !(e instanceof HTMLTextAreaElement) ? !1 : !Y(e);
}
function st(e) {
  if (!Q(e))
    return !1;
  const t = String(p(e, "autocomplete", "") || "").toLowerCase(), n = String(p(e, "inputmode", "") || "").toLowerCase(), r = e instanceof Element ? e.className : "";
  return t === "one-time-code" || n === "numeric" || String(r).includes("authkit-otp");
}
function hr(e) {
  return e instanceof HTMLFormElement ? d("input, select, textarea, button", e) : [];
}
function Lr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = E(t || document);
  return n[0] instanceof HTMLFormElement ? n[0] : null;
}
function Tr(e = []) {
  return e.filter((t) => Y(t));
}
function Z(e = []) {
  return e.filter((t) => it(t));
}
function ut(e = []) {
  return e.filter((t) => st(t));
}
function Sr(e = []) {
  return ut(e)[0] ?? null;
}
function Hr(e = []) {
  return Z(e)[0] ?? null;
}
function _r(e = []) {
  return Z(e)[1] ?? null;
}
function kr(e = []) {
  return e.filter((t) => !(!Q(t) || it(t) || ot(t) || st(t)));
}
function Fr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Lr(e), r = hr(n);
  return {
    page: t instanceof HTMLElement ? t : null,
    form: n,
    controls: r,
    visibleControls: r.filter((o) => Q(o)),
    hiddenControls: r.filter((o) => Y(o)),
    checkboxControls: r.filter((o) => ot(o)),
    passwordControls: Z(r),
    submitControls: r.filter((o) => Mr(o)),
    links: t ? d("a[href]", t).filter((o) => o instanceof HTMLAnchorElement) : [],
    otpLikeControls: ut(r),
    primaryOtpLikeControl: Sr(r),
    visibleResetControls: kr(r),
    primaryPasswordControl: Hr(r),
    passwordConfirmationControl: _r(r),
    contextControls: Tr(r)
  };
}
function Ai(e) {
  return !s(e) || !w("password_reset_token") ? null : {
    key: "password_reset_token",
    ...Fr(e)
  };
}
const $i = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Ai,
  getContextControls: Tr,
  getFormControls: hr,
  getOtpLikeControls: ut,
  getPasswordConfirmationControl: _r,
  getPasswordControls: Z,
  getPasswordResetTokenForm: Lr,
  getPasswordResetTokenPageElements: Fr,
  getPrimaryOtpLikeControl: Sr,
  getPrimaryPasswordControl: Hr,
  getVisibleResetControls: kr,
  isCheckboxControl: ot,
  isHiddenControl: Y,
  isOtpLikeControl: st,
  isPasswordControl: it,
  isSubmitControl: Mr,
  isVisibleFormControl: Q
}, Symbol.toStringTag, { value: "Module" }));
function lt(e) {
  return e instanceof HTMLElement ? d("a[href]", e).filter((t) => t instanceof HTMLAnchorElement) : [];
}
function Pr(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null;
  return E(t || document).filter((r) => r instanceof HTMLFormElement);
}
function vr(e) {
  return lt(e)[0] ?? null;
}
function Ar(e) {
  const t = e?.pageElement ?? e?.page?.element ?? null, n = Pr(e), r = lt(t instanceof HTMLElement ? t : null);
  return {
    page: t instanceof HTMLElement ? t : null,
    forms: n,
    formCount: n.length,
    links: r,
    primaryActionLink: vr(t instanceof HTMLElement ? t : null)
  };
}
function Oi(e) {
  return !s(e) || !w("password_reset_success") ? null : {
    key: "password_reset_success",
    ...Ar(e)
  };
}
const Bi = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  boot: Oi,
  getPasswordResetSuccessPageElements: Ar,
  getPrimaryActionLink: vr,
  getSuccessPageForms: Pr,
  getSuccessPageLinks: lt
}, Symbol.toStringTag, { value: "Module" }));
function Ii() {
  return {
    login: di,
    register: pi,
    two_factor_challenge: bi,
    two_factor_recovery: Ei,
    email_verification_notice: Mi,
    email_verification_token: Li,
    email_verification_success: Si,
    password_forgot: _i,
    password_forgot_sent: Fi,
    password_reset: vi,
    password_reset_token: $i,
    password_reset_success: Bi
  };
}
const Ri = Object.freeze(Ii());
function zi() {
  return { ...Ri };
}
function ji() {
  return lo({
    config: gt(),
    moduleRegistry: fi(),
    pageRegistry: zi()
  });
}
function Ki() {
  return _();
}
function xi() {
  return wt();
}
Nr(() => {
  ji();
});
export {
  ji as bootAuthKit,
  Ki as getAuthKitState,
  xi as isAuthKitBooted
};
