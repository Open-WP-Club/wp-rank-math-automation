jQuery(document).ready(function ($) {
  // Tab functionality
  $(".wrms-tab-link").click(function () {
    var tabId = $(this).data("tab");
    $(".wrms-tab-link").removeClass("active");
    $(".wrms-tab-pane").removeClass("active");
    $(this).addClass("active");
    $("#" + tabId).addClass("active");
  });

  // Content type configurations
  var contentTypes = {
    product: {
      singular: "product",
      plural: "products",
      countAction: "wrms_get_product_count",
      syncAction: "wrms_sync_next_product",
      removeAction: "wrms_remove_product_meta",
      titleKey: "title",
      itemKey: "product"
    },
    category: {
      singular: "category",
      plural: "categories",
      countAction: "wrms_get_category_count",
      syncAction: "wrms_sync_next_category",
      removeAction: "wrms_remove_category_meta",
      titleKey: "name",
      itemKey: "category"
    },
    page: {
      singular: "page",
      plural: "pages",
      countAction: "wrms_get_page_count",
      syncAction: "wrms_sync_next_page",
      removeAction: "wrms_remove_page_meta",
      titleKey: "title",
      itemKey: "page"
    },
    media: {
      singular: "media item",
      plural: "media items",
      countAction: "wrms_get_media_count",
      syncAction: "wrms_sync_next_media",
      removeAction: "wrms_remove_media_meta",
      titleKey: "title",
      itemKey: "media"
    },
    post: {
      singular: "post",
      plural: "posts",
      countAction: "wrms_get_post_count",
      syncAction: "wrms_sync_next_post",
      removeAction: "wrms_remove_post_meta",
      titleKey: "title",
      itemKey: "post"
    }
  };

  // Batch size for sync operations (10x faster than single item processing)
  var BATCH_SIZE = 10;

  // Generic sync function with batch processing
  function syncContentType(type) {
    var config = contentTypes[type];
    var total = 0;
    var processed = 0;

    $("#progress-bar").show();
    $("#sync-loader").show();
    $("#sync-log").html("");
    $("#progress-bar-fill").css("width", "0%");

    // Get count first
    $.ajax({
      url: wrms_data.ajax_url,
      method: "POST",
      data: {
        action: config.countAction,
        nonce: wrms_data.nonce
      },
      success: function (response) {
        if (response.success) {
          total = response.data.count;
          $("#sync-count").text("Processing 0 of " + total + " " + config.plural);
          processNextBatch();
        } else {
          $("#sync-status").append("<p>Error: " + response.data.message + "</p>");
          hideLoader();
        }
      },
      error: function (xhr, status, error) {
        $("#sync-status").append("<p>Error: " + error + "</p>");
        hideLoader();
      }
    });

    function processNextBatch() {
      $.ajax({
        url: wrms_data.ajax_url,
        method: "POST",
        data: {
          action: config.syncAction,
          nonce: wrms_data.nonce,
          batch_size: BATCH_SIZE
        },
        success: function (response) {
          if (response.success && response.data.processed > 0) {
            processed += response.data.processed;
            var items = response.data.items || [response.data[config.itemKey]];

            // Log batch progress
            items.forEach(function(item) {
              var title = item[config.titleKey] || item.name || item.title;
              $("#sync-log").append(
                "<p>Synced: " + title + " (ID: " + item.id + ")</p>"
              );
            });

            $("#sync-count").text("Processing " + processed + " of " + total + " " + config.plural);
            $("#sync-log").scrollTop($("#sync-log")[0].scrollHeight);
            $("#progress-bar-fill").css("width", (processed / total) * 100 + "%");

            if (processed < total) {
              processNextBatch();
            } else {
              finishSync(config.plural + " synced successfully!");
            }
          } else if (!response.success) {
            finishSync("Error: " + response.data.message);
          } else {
            finishSync("All " + config.plural + " are already synced.");
          }
        },
        error: function (xhr, status, error) {
          finishSync("Error during syncing: " + error);
        }
      });
    }
  }

  // Generic remove meta function
  function removeContentMeta(type) {
    var config = contentTypes[type];

    $("#progress-bar").show();
    $("#sync-loader").show();
    $("#sync-log").html("");
    $("#progress-bar-fill").css("width", "0%");

    $.ajax({
      url: wrms_data.ajax_url,
      method: "POST",
      data: {
        action: config.removeAction,
        nonce: wrms_data.nonce
      },
      success: function (response) {
        if (response.success) {
          var removed = response.data.removed;
          var total = response.data.total;
          $("#sync-count").text("Removed meta from " + removed + " of " + total + " " + config.plural);
          $("#sync-log").append("<p>" + config.plural.charAt(0).toUpperCase() + config.plural.slice(1) + " meta removed successfully!</p>");
          $("#progress-bar-fill").css("width", total > 0 ? (removed / total) * 100 + "%" : "100%");
        } else {
          $("#sync-status").append("<p>Error: " + response.data.message + "</p>");
        }
        hideLoader();
        updateStats();
      },
      error: function (xhr, status, error) {
        $("#sync-status").append("<p>Error: " + error + "</p>");
        hideLoader();
        updateStats();
      }
    });
  }

  function hideLoader() {
    $("#sync-loader").hide();
  }

  function finishSync(message) {
    hideLoader();
    $("#sync-status").append("<p>" + message + "</p>");
    updateStats();
  }

  // Bind sync buttons
  $("#sync-products").click(function () { syncContentType("product"); });
  $("#sync-categories").click(function () { syncContentType("category"); });
  $("#sync-pages").click(function () { syncContentType("page"); });
  $("#sync-media").click(function () { syncContentType("media"); });
  $("#sync-posts").click(function () { syncContentType("post"); });

  // Bind remove buttons
  $("#remove-product-meta").click(function () { removeContentMeta("product"); });
  $("#remove-category-meta").click(function () { removeContentMeta("category"); });
  $("#remove-page-meta").click(function () { removeContentMeta("page"); });
  $("#remove-media-meta").click(function () { removeContentMeta("media"); });
  $("#remove-post-meta").click(function () { removeContentMeta("post"); });

  // Auto-sync toggle
  $("#wrms_auto_sync").on("change", function () {
    var isChecked = $(this).is(":checked");
    $.ajax({
      url: wrms_data.ajax_url,
      method: "POST",
      data: {
        action: "wrms_update_auto_sync",
        auto_sync: isChecked ? 1 : 0,
        nonce: wrms_data.nonce
      },
      error: function (xhr, status, error) {
        alert("Error updating auto-sync setting: " + error);
      }
    });
  });

  // Update Statistics
  $("#update-stats").on("click", function (e) {
    e.preventDefault();
    updateStats();
  });

  function updateStats() {
    var button = $("#update-stats");
    button.prop("disabled", true).text("Updating...");

    $.ajax({
      url: wrms_data.ajax_url,
      type: "POST",
      data: {
        action: "wrms_update_stats",
        nonce: wrms_data.nonce
      },
      success: function (response) {
        if (response.success) {
          var s = response.data;
          $("#total-products").text(s.total_products);
          $("#synced-products").text(s.synced_products);
          $("#total-pages").text(s.total_pages);
          $("#synced-pages").text(s.synced_pages);
          $("#total-media").text(s.total_media);
          $("#synced-media").text(s.synced_media);
          $("#total-categories").text(s.total_categories);
          $("#synced-categories").text(s.synced_categories);
          $("#total-posts").text(s.total_posts);
          $("#synced-posts").text(s.synced_posts);
          $("#total-items").text(s.total_items);
          $("#total-synced").text(s.total_synced);
          $("#sync-percentage").text(s.sync_percentage + "%");
          $("#last-updated").text(new Date(s.timestamp * 1000).toLocaleString());
        }
      },
      complete: function () {
        button.prop("disabled", false).text("Update Statistics");
      }
    });
  }

  // Download URLs
  $("#download-urls").click(function (e) {
    e.preventDefault();
    var urlTypes = $('input[name="url_types[]"]:checked').map(function () {
      return this.value;
    }).get();

    if (urlTypes.length === 0) {
      $("#download-status").text("Please select at least one URL type to download.");
      return;
    }

    $("#progress-bar").show();
    $("#download-loader").show();
    $("#download-log").html("");
    $("#download-progress-bar-fill").css("width", "0%");

    var offset = 0;
    var chunkSize = 2000;

    function downloadChunk() {
      $.ajax({
        url: wrms_data.ajax_url,
        type: "POST",
        data: {
          action: "wrms_get_urls",
          nonce: wrms_data.nonce,
          offset: offset,
          chunk_size: chunkSize,
          url_types: urlTypes
        },
        success: function (response) {
          if (response.success && response.data.urls.length > 0) {
            var blob = new Blob([response.data.urls.join("\n")], { type: "text/plain" });
            var link = document.createElement("a");
            link.href = window.URL.createObjectURL(blob);
            link.download = "urls_" + offset + "-" + (offset + response.data.urls.length) + ".txt";
            link.click();

            var end = offset + response.data.urls.length;
            $("#download-count").text("Downloaded URLs " + offset + " to " + end);
            $("#download-log").append("<p>Downloaded URLs " + offset + " to " + end + "</p>");
            $("#download-log").scrollTop($("#download-log")[0].scrollHeight);
            $("#download-progress-bar-fill").css("width", (end / response.data.total) * 100 + "%");

            offset += chunkSize;
            downloadChunk();
          } else {
            $("#download-count").text("All URLs have been downloaded.");
            $("#download-log").append("<p>All URLs have been downloaded.</p>");
            $("#download-loader").hide();
          }
        },
        error: function (xhr, status, error) {
          $("#download-loader").hide();
          $("#download-status").append("<p>Error: " + error + "</p>");
        }
      });
    }

    downloadChunk();
  });

  // Get last sync time on page load
  $.ajax({
    url: wrms_data.ajax_url,
    method: "POST",
    data: {
      action: "wrms_get_last_sync_time",
      nonce: wrms_data.nonce
    },
    success: function (response) {
      if (response.success && response.data.last_sync_time) {
        var timestamp = response.data.last_sync_time;
        if (timestamp > 0) {
          $("#last-sync-time").text("Last sync: " + new Date(timestamp * 1000).toLocaleString());
        }
      }
    }
  });
});
