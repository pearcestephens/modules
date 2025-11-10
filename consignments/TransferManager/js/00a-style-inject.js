'use strict';
/**
 * Transfer Manager Style Injector
 * - Injects strictly-scoped CSS at runtime as an internal <style> tag
 * - Prevents global CSS leakage into CIS template
 * - Consolidates both styles.css and cis-theme-override.css under the
 *   .transfer-manager-wrap root to ensure isolation
 */
(function(){
  try {
    // Only run on pages that contain the Transfer Manager root container
    var root = document.querySelector('.transfer-manager-wrap');
    if (!root) return;

    // Fetch bundled CSS files and inject into a single <style> tag
    var sources = [
      '/modules/consignments/TransferManager/styles.css',
      '/modules/consignments/TransferManager/cis-theme-override.css'
      var css = contents.join('\n\n/* --- next file --- */\n\n');

      // Robust scoping: prefix selectors with .transfer-manager-wrap
      // - Keeps @keyframes/@font-face global (safe)
      // - Recursively scopes inside @media/@supports
      // - Maps html, body, :root to the container itself
      function scopeBlock(blockCss) {
        // Split into selector and declarations
        var parts = blockCss.split('{');
        if (parts.length < 2) return blockCss;
        var selector = parts.shift().trim();
        var body = parts.join('{');

        // Skip at-rules that define global constructs
        if (/^@(?:keyframes|font-face)/i.test(selector)) {
          return selector + '{' + body;
        }

        // Handle nested at-rules that contain rules to be scoped
        if (/^@(?:media|supports|container)/i.test(selector)) {
          // Find matching closing brace for this at-rule
          // Very naive balance-based approach
          var depth = 1; var i = 0; var inner = '';
          for (; i < body.length; i++) {
            var ch = body[i];
            inner += ch;
            if (ch === '{') depth++;
            else if (ch === '}') depth--;
            if (depth === 0) break;
          }
          // inner includes last '}' of the nested content; remove trailing '}'
          var innerContent = inner.slice(0, -1);
          var scopedInner = scopeStyle(innerContent);
          return selector + '{' + scopedInner + '}';
        }

        // Scope normal selector list
        var scopedSelector = selector
          .split(',')
          .map(function(sel){
            sel = sel.trim();
            // Map html/body/:root to container
            sel = sel.replace(/\b(html|body|:root)\b/gi, ':where(.transfer-manager-wrap)');
            // If selector already contains .transfer-manager-wrap, keep
            if (/\.transfer-manager-wrap\b/.test(sel)) return sel;
            // Prefix with container; ensure combinators preserved
            if (sel.startsWith(':where(.transfer-manager-wrap)')) return sel;
            return ':where(.transfer-manager-wrap) ' + sel;
          })
          .join(', ');

        return scopedSelector + '{' + body;
      }

      function scopeStyle(text) {
        var out = '';
        var i = 0; var start = 0; var depth = 0;
        while (i < text.length) {
          var ch = text[i];
          if (ch === '{') { depth++; i++; continue; }
          if (ch === '}') { depth--; i++; continue; }
          // When at top-level and we encounter a '}' boundary of a rule, capture
          i++;
        }

        // Fallback simple splitter (keeps comments harmlessly):
        return text.split('}').map(function(chunk){
          chunk = chunk.trim();
          if (!chunk) return '';
          'use strict';
          /**
           * Transfer Manager Style Injector
           * - Injects strictly-scoped CSS at runtime as an internal <style> tag
           * - Prevents global CSS leakage into CIS template
           */
          (function(){
            try {
              var root = document.querySelector('.transfer-manager-wrap');
              if (!root) return;

              var sources = [
                '/modules/consignments/TransferManager/styles.css',
                '/modules/consignments/TransferManager/cis-theme-override.css'
              ];

              Promise.all(sources.map(function(url){
                return fetch(url, { credentials: 'same-origin' }).then(function(r){
                  if (!r.ok) throw new Error('CSS load failed: ' + url + ' (' + r.status + ')');
                  return r.text();
                });
              })).then(function(contents){
                var css = contents.join('\n\n/* --- next file --- */\n\n');

                function scopeBlock(blockCss) {
                  var parts = blockCss.split('{');
                  if (parts.length < 2) return blockCss;
                  var selector = parts.shift().trim();
                  var body = parts.join('{');

                  if (/^@(?:keyframes|font-face)/i.test(selector)) {
                    return selector + '{' + body;
                  }

                  if (/^@(?:media|supports|container)/i.test(selector)) {
                    return selector + '{' + scopeStyle(body.slice(0, -1)) + '}';
                  }

                  var scopedSelector = selector
                    .split(',')
                    .map(function(sel){
                      sel = sel.trim();
                      sel = sel.replace(/\b(html|body|:root)\b/gi, ':where(.transfer-manager-wrap)');
                      if (/\.transfer-manager-wrap\b/.test(sel)) return sel;
                      if (sel.startsWith(':where(.transfer-manager-wrap)')) return sel;
                      return ':where(.transfer-manager-wrap) ' + sel;
                    })
                    .join(', ');

                  return scopedSelector + '{' + body;
                }

                function scopeStyle(text) {
                  return text.split('}').map(function(chunk){
                    chunk = chunk.trim();
                    if (!chunk) return '';
                    return scopeBlock(chunk + '}');
                  }).join('\n');
                }

                var reset = ':where(.transfer-manager-wrap){all: initial;}\n:where(.transfer-manager-wrap){font: inherit; color: inherit;}\n';
                  // Avoid aggressive resets that may hide UI; only scope selectors
                  var scopedCss = scopeStyle(css);

                var style = document.createElement('style');
                style.type = 'text/css';
                style.setAttribute('data-origin', 'tm-style-injector');
                style.appendChild(document.createTextNode(scopedCss));
                document.head.appendChild(style);
              }).catch(function(err){
                console.error('Transfer Manager style injection failed:', err);
              });
            } catch (e) {
              console.error('Transfer Manager style injector error:', e);
            }
          })();
