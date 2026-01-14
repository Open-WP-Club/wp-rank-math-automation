(function () {
  "use strict";

  // Wait for DOM to be ready
  document.addEventListener("DOMContentLoaded", init);

  // Content type configurations
  const contentTypes = {
    product: {
      singular: "product",
      plural: "products",
      countAction: "wrms_get_product_count",
      syncAction: "wrms_sync_next_product",
      removeAction: "wrms_remove_product_meta",
      titleKey: "title",
      itemKey: "product",
    },
    category: {
      singular: "category",
      plural: "categories",
      countAction: "wrms_get_category_count",
      syncAction: "wrms_sync_next_category",
      removeAction: "wrms_remove_category_meta",
      titleKey: "name",
      itemKey: "category",
    },
    page: {
      singular: "page",
      plural: "pages",
      countAction: "wrms_get_page_count",
      syncAction: "wrms_sync_next_page",
      removeAction: "wrms_remove_page_meta",
      titleKey: "title",
      itemKey: "page",
    },
    media: {
      singular: "media item",
      plural: "media items",
      countAction: "wrms_get_media_count",
      syncAction: "wrms_sync_next_media",
      removeAction: "wrms_remove_media_meta",
      titleKey: "title",
      itemKey: "media",
    },
    post: {
      singular: "post",
      plural: "posts",
      countAction: "wrms_get_post_count",
      syncAction: "wrms_sync_next_post",
      removeAction: "wrms_remove_post_meta",
      titleKey: "title",
      itemKey: "post",
    },
  };

  // Batch size for sync operations
  const BATCH_SIZE = 10;

  // Helper functions
  function $(selector) {
    return document.querySelector(selector);
  }

  function $$(selector) {
    return document.querySelectorAll(selector);
  }

  function show(el) {
    if (el) el.style.display = "block";
  }

  function hide(el) {
    if (el) el.style.display = "none";
  }

  function setText(el, text) {
    if (el) el.textContent = text;
  }

  function setHtml(el, html) {
    if (el) el.innerHTML = html;
  }

  function appendHtml(el, html) {
    if (el) el.insertAdjacentHTML("beforeend", html);
  }

  function setWidth(el, percent) {
    if (el) el.style.width = percent + "%";
  }

  function scrollToBottom(el) {
    if (el) el.scrollTop = el.scrollHeight;
  }

  // AJAX helper using Fetch API
  async function ajax(action, data = {}) {
    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", wrms_data.nonce);

    for (const [key, value] of Object.entries(data)) {
      if (Array.isArray(value)) {
        value.forEach((v) => formData.append(key + "[]", v));
      } else {
        formData.append(key, value);
      }
    }

    const response = await fetch(wrms_data.ajax_url, {
      method: "POST",
      body: formData,
    });

    return response.json();
  }

  // Initialize
  function init() {
    initTabs();
    initSyncButtons();
    initRemoveButtons();
    initAutoSync();
    initStats();
    initUrlDownload();
    loadLastSyncTime();
  }

  // Tab functionality
  function initTabs() {
    $$(".wrms-tab-link").forEach((tab) => {
      tab.addEventListener("click", () => {
        const tabId = tab.dataset.tab;

        $$(".wrms-tab-link").forEach((t) => t.classList.remove("active"));
        $$(".wrms-tab-pane").forEach((p) => p.classList.remove("active"));

        tab.classList.add("active");
        const pane = $("#" + tabId);
        if (pane) pane.classList.add("active");
      });
    });
  }

  // Sync buttons
  function initSyncButtons() {
    const buttons = {
      "sync-products": "product",
      "sync-categories": "category",
      "sync-pages": "page",
      "sync-media": "media",
      "sync-posts": "post",
    };

    for (const [id, type] of Object.entries(buttons)) {
      const btn = $("#" + id);
      if (btn) {
        btn.addEventListener("click", () => syncContentType(type));
      }
    }
  }

  // Remove buttons
  function initRemoveButtons() {
    const buttons = {
      "remove-product-meta": "product",
      "remove-category-meta": "category",
      "remove-page-meta": "page",
      "remove-media-meta": "media",
      "remove-post-meta": "post",
    };

    for (const [id, type] of Object.entries(buttons)) {
      const btn = $("#" + id);
      if (btn) {
        btn.addEventListener("click", () => removeContentMeta(type));
      }
    }
  }

  // Auto-sync toggle
  function initAutoSync() {
    const toggle = $("#wrms_auto_sync");
    if (toggle) {
      toggle.addEventListener("change", async () => {
        try {
          await ajax("wrms_update_auto_sync", {
            auto_sync: toggle.checked ? 1 : 0,
          });
        } catch (error) {
          alert("Error updating auto-sync setting: " + error.message);
        }
      });
    }
  }

  // Statistics
  function initStats() {
    const btn = $("#update-stats");
    if (btn) {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        updateStats();
      });
    }
  }

  async function updateStats() {
    const btn = $("#update-stats");
    if (btn) {
      btn.disabled = true;
      setText(btn, "Updating...");
    }

    try {
      const response = await ajax("wrms_update_stats");

      if (response.success) {
        const s = response.data;
        setText($("#total-products"), s.total_products);
        setText($("#synced-products"), s.synced_products);
        setText($("#total-pages"), s.total_pages);
        setText($("#synced-pages"), s.synced_pages);
        setText($("#total-media"), s.total_media);
        setText($("#synced-media"), s.synced_media);
        setText($("#total-categories"), s.total_categories);
        setText($("#synced-categories"), s.synced_categories);
        setText($("#total-posts"), s.total_posts);
        setText($("#synced-posts"), s.synced_posts);
        setText($("#total-items"), s.total_items);
        setText($("#total-synced"), s.total_synced);
        setText($("#sync-percentage"), s.sync_percentage + "%");
        setText(
          $("#last-updated"),
          new Date(s.timestamp * 1000).toLocaleString()
        );
      }
    } catch (error) {
      console.error("Failed to update stats:", error);
    } finally {
      if (btn) {
        btn.disabled = false;
        setText(btn, "Update Statistics");
      }
    }
  }

  // Generic sync function with batch processing
  async function syncContentType(type) {
    const config = contentTypes[type];
    let total = 0;
    let processed = 0;

    show($("#progress-bar"));
    show($("#sync-loader"));
    setHtml($("#sync-log"), "");
    setWidth($("#progress-bar-fill"), 0);

    try {
      // Get count first
      const countResponse = await ajax(config.countAction);

      if (!countResponse.success) {
        appendHtml(
          $("#sync-status"),
          `<p>Error: ${countResponse.data?.message || "Unknown error"}</p>`
        );
        hideLoader();
        return;
      }

      total = countResponse.data.count;
      setText($("#sync-count"), `Processing 0 of ${total} ${config.plural}`);

      // Process in batches
      while (processed < total) {
        const response = await ajax(config.syncAction, {
          batch_size: BATCH_SIZE,
        });

        if (response.success && response.data.processed > 0) {
          processed += response.data.processed;
          const items = response.data.items || [response.data[config.itemKey]];

          // Log batch progress
          items.forEach((item) => {
            const title = item[config.titleKey] || item.name || item.title;
            appendHtml(
              $("#sync-log"),
              `<p>Synced: ${title} (ID: ${item.id})</p>`
            );
          });

          setText(
            $("#sync-count"),
            `Processing ${processed} of ${total} ${config.plural}`
          );
          scrollToBottom($("#sync-log"));
          setWidth($("#progress-bar-fill"), (processed / total) * 100);
        } else if (!response.success) {
          finishSync(`Error: ${response.data?.message || "Unknown error"}`);
          return;
        } else {
          // No more items to process
          break;
        }
      }

      finishSync(
        processed > 0
          ? `${config.plural} synced successfully!`
          : `All ${config.plural} are already synced.`
      );
    } catch (error) {
      finishSync(`Error during syncing: ${error.message}`);
    }
  }

  // Generic remove meta function
  async function removeContentMeta(type) {
    const config = contentTypes[type];

    show($("#progress-bar"));
    show($("#sync-loader"));
    setHtml($("#sync-log"), "");
    setWidth($("#progress-bar-fill"), 0);

    try {
      const response = await ajax(config.removeAction);

      if (response.success) {
        const { removed, total } = response.data;
        setText(
          $("#sync-count"),
          `Removed meta from ${removed} of ${total} ${config.plural}`
        );
        appendHtml(
          $("#sync-log"),
          `<p>${config.plural.charAt(0).toUpperCase() + config.plural.slice(1)} meta removed successfully!</p>`
        );
        setWidth($("#progress-bar-fill"), total > 0 ? (removed / total) * 100 : 100);
      } else {
        appendHtml(
          $("#sync-status"),
          `<p>Error: ${response.data?.message || "Unknown error"}</p>`
        );
      }
    } catch (error) {
      appendHtml($("#sync-status"), `<p>Error: ${error.message}</p>`);
    } finally {
      hideLoader();
      updateStats();
    }
  }

  function hideLoader() {
    hide($("#sync-loader"));
  }

  function finishSync(message) {
    hideLoader();
    appendHtml($("#sync-status"), `<p>${message}</p>`);
    updateStats();
  }

  // URL Download
  function initUrlDownload() {
    const btn = $("#download-urls");
    if (btn) {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        downloadUrls();
      });
    }
  }

  async function downloadUrls() {
    const checkboxes = $$('input[name="url_types[]"]:checked');
    const urlTypes = Array.from(checkboxes).map((cb) => cb.value);

    if (urlTypes.length === 0) {
      setText(
        $("#download-status"),
        "Please select at least one URL type to download."
      );
      return;
    }

    show($("#progress-bar"));
    show($("#download-loader"));
    setHtml($("#download-log"), "");
    setWidth($("#download-progress-bar-fill"), 0);

    let offset = 0;
    const chunkSize = 2000;

    try {
      while (true) {
        const response = await ajax("wrms_get_urls", {
          offset,
          chunk_size: chunkSize,
          url_types: urlTypes,
        });

        if (response.success && response.data.urls.length > 0) {
          // Create and download file
          const blob = new Blob([response.data.urls.join("\n")], {
            type: "text/plain",
          });
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = `urls_${offset}-${offset + response.data.urls.length}.txt`;
          link.click();
          URL.revokeObjectURL(link.href);

          const end = offset + response.data.urls.length;
          setText($("#download-count"), `Downloaded URLs ${offset} to ${end}`);
          appendHtml(
            $("#download-log"),
            `<p>Downloaded URLs ${offset} to ${end}</p>`
          );
          scrollToBottom($("#download-log"));
          setWidth(
            $("#download-progress-bar-fill"),
            (end / response.data.total) * 100
          );

          offset += chunkSize;
        } else {
          setText($("#download-count"), "All URLs have been downloaded.");
          appendHtml(
            $("#download-log"),
            "<p>All URLs have been downloaded.</p>"
          );
          hide($("#download-loader"));
          break;
        }
      }
    } catch (error) {
      hide($("#download-loader"));
      appendHtml($("#download-status"), `<p>Error: ${error.message}</p>`);
    }
  }

  // Load last sync time on page load
  async function loadLastSyncTime() {
    try {
      const response = await ajax("wrms_get_last_sync_time");

      if (response.success && response.data.last_sync_time > 0) {
        const timestamp = response.data.last_sync_time;
        setText(
          $("#last-sync-time"),
          `Last sync: ${new Date(timestamp * 1000).toLocaleString()}`
        );
      }
    } catch (error) {
      console.error("Failed to load last sync time:", error);
    }
  }

  // =============================================================================
  // SEO AUTOMATION TAB
  // =============================================================================

  // Bulk update ALT texts
  const bulkAltBtn = $("#bulk-update-alts");
  if (bulkAltBtn) {
    bulkAltBtn.addEventListener("click", async () => {
      const statusEl = $("#alt-update-status");
      bulkAltBtn.disabled = true;
      setText(bulkAltBtn, "Updating...");
      setText(statusEl, "");

      try {
        const response = await ajax("wrms_bulk_update_alts");

        if (response.success) {
          setText(statusEl, response.data.message);
          statusEl.classList.add("success");
          statusEl.classList.remove("error");
        } else {
          setText(statusEl, "Error: " + (response.data?.message || "Unknown error"));
          statusEl.classList.add("error");
          statusEl.classList.remove("success");
        }
      } catch (error) {
        setText(statusEl, "Error: " + error.message);
        statusEl.classList.add("error");
      } finally {
        bulkAltBtn.disabled = false;
        setText(bulkAltBtn, "Update Missing ALT Texts");
      }
    });
  }

  // Save automation settings
  const saveAutomationBtn = $("#save-automation-settings");
  if (saveAutomationBtn) {
    saveAutomationBtn.addEventListener("click", async () => {
      saveAutomationBtn.disabled = true;
      setText(saveAutomationBtn, "Saving...");

      const noindexOutOfStock = $("#noindex-out-of-stock")?.checked ? "1" : "0";
      const noindexDateArchives = $("#noindex-date-archives")?.checked ? "1" : "0";

      try {
        await ajax("wrms_save_automation_settings", {
          noindex_out_of_stock: noindexOutOfStock,
          noindex_date_archives: noindexDateArchives,
        });

        setText(saveAutomationBtn, "Saved!");
        setTimeout(() => {
          setText(saveAutomationBtn, "Save Settings");
        }, 2000);
      } catch (error) {
        alert("Error saving settings: " + error.message);
      } finally {
        saveAutomationBtn.disabled = false;
      }
    });
  }

  // =============================================================================
  // SITEMAP SETTINGS TAB
  // =============================================================================

  // Add sitemap input
  const addSitemapBtn = $("#add-sitemap");
  if (addSitemapBtn) {
    addSitemapBtn.addEventListener("click", () => {
      const container = $("#additional-sitemaps");
      if (container) {
        appendHtml(
          container,
          `<div class="sitemap-input">
            <input type="text" name="wrms_additional_sitemaps[]" placeholder="https://example.com/sitemap.xml" />
            <button type="button" class="remove-sitemap button">Remove</button>
          </div>`
        );
      }
    });
  }

  // Add URL input
  const addUrlBtn = $("#add-url");
  if (addUrlBtn) {
    addUrlBtn.addEventListener("click", () => {
      const container = $("#additional-urls");
      if (container) {
        appendHtml(
          container,
          `<div class="url-input">
            <input type="text" name="wrms_additional_urls[]" placeholder="https://example.com/page" />
            <button type="button" class="remove-url button">Remove</button>
          </div>`
        );
      }
    });
  }

  // Remove sitemap/url buttons (event delegation)
  document.addEventListener("click", (e) => {
    if (e.target.classList.contains("remove-sitemap")) {
      e.target.closest(".sitemap-input")?.remove();
    }
    if (e.target.classList.contains("remove-url")) {
      e.target.closest(".url-input")?.remove();
    }
  });

  // Refresh sitemap
  const refreshSitemapBtn = $("#refresh-sitemap");
  if (refreshSitemapBtn) {
    refreshSitemapBtn.addEventListener("click", async () => {
      refreshSitemapBtn.disabled = true;
      setText(refreshSitemapBtn, "Refreshing...");

      try {
        const response = await ajax("wrms_refresh_sitemap");

        if (response.success) {
          alert(response.data.message || "Sitemap refreshed successfully!");
        } else {
          alert("Error: " + (response.data?.message || "Unknown error"));
        }
      } catch (error) {
        alert("Error refreshing sitemap: " + error.message);
      } finally {
        refreshSitemapBtn.disabled = false;
        setText(refreshSitemapBtn, "Refresh Sitemap");
      }
    });
  }
})();
