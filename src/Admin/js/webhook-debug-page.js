(function ($) {
	"use strict";

	if (typeof pwlDteWebhookDebug === "undefined") {
		return;
	}

	var i18n = pwlDteWebhookDebug.i18n || {};

	function relativeTime(dateString) {
		var now = new Date();
		var then = new Date(dateString.replace(" ", "T"));
		var diff = Math.floor((now - then) / 1000);
		if (diff < 60) {
			return "hace " + diff + "s";
		}
		if (diff < 3600) {
			return "hace " + Math.floor(diff / 60) + "m";
		}
		if (diff < 86400) {
			return "hace " + Math.floor(diff / 3600) + "h";
		}
		return "hace " + Math.floor(diff / 86400) + "d";
	}

	$(".bsale-relative-time").each(function () {
		var $element = $(this);
		var time = $element.data("time");
		if (time) {
			$element.text(relativeTime(time));
		}
	});

	function syntaxHighlight(json) {
		var escaped = json.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
		return escaped.replace(
			/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
			function (match) {
				if (/^"/.test(match)) {
					return /:$/.test(match)
						? '<span class="jk">' + match + "</span>"
						: '<span class="js">' + match + "</span>";
				}
				if (/true|false/.test(match)) {
					return '<span class="jb">' + match + "</span>";
				}
				if (/null/.test(match)) {
					return '<span class="jl">' + match + "</span>";
				}
				return '<span class="jn">' + match + "</span>";
			}
		);
	}

	$(document).on("click", ".bsale-toggle-payload", function () {
		var $button = $(this);
		var $row = $("#" + $button.data("target"));
		var $pre = $row.find(".bsale-json-pre");

		if ($pre.is(":empty")) {
			try {
				var raw = JSON.stringify(JSON.parse($pre.attr("data-raw")), null, 2);
				$pre.html(syntaxHighlight(raw));
			} catch (error) {
				$pre.text($pre.attr("data-raw"));
			}
		}

		$row.toggle();
		$button.text($row.is(":visible") ? "▲" : "▼");
	});

	$(document).on("click", ".bsale-copy-payload", function () {
		var $button = $(this);
		var raw = $button.attr("data-payload");
		try {
			var pretty = JSON.stringify(JSON.parse(raw), null, 2);
			navigator.clipboard.writeText(pretty).then(function () {
				var original = $button.text();
				$button.text(i18n.copied || "Copiado").prop("disabled", true);
				setTimeout(function () {
					$button.text(original).prop("disabled", false);
				}, 2000);
			});
		} catch (error) {
			// Ignore malformed payload copy attempts.
		}
	});

	$(document).on("click", ".bsale-clear-events-btn", function () {
		var $button = $(this);
		var confirmText = $button.data("confirm");
		if (!window.confirm(confirmText)) {
			return;
		}

		$button.prop("disabled", true).text(i18n.deleteInProgress || "Eliminando...");
		$.post(
			pwlDteWebhookDebug.ajaxUrl,
			{
				action: "pwl_dte_clear_webhook_events",
				nonce: $button.data("nonce"),
			},
			function (response) {
				if (!response.success) {
					return;
				}

				$("#bsale-wh-events-wrap").html(
					'<div id="bsale-wh-empty" style="text-align:center;padding:48px 24px;background:#fff;border:1px solid #c3c4c7;border-radius:3px;">' +
						'<span style="font-size:40px;display:block;margin-bottom:12px;">📭</span>' +
						'<p style="font-size:15px;color:#646970;margin:0;"><strong>' +
						(i18n.historyCleared || "Historial eliminado.") +
						"</strong></p>" +
						"</div>"
				);
			}
		).fail(function () {
			$button.prop("disabled", false).text("✕ " + (i18n.clearHistory || "Limpiar historial"));
			alert(i18n.clearError || "Error");
		});
	});

	$("#bsale-toggle-webhooks-btn").on("click", function () {
		var $button = $(this);
		var $bar = $("#bsale-webhook-status-bar");
		var nonce = $button.data("nonce");

		$button.prop("disabled", true).text(i18n.saving || "Guardando...");
		$.post(
			pwlDteWebhookDebug.ajaxUrl,
			{
				action: "pwl_dte_toggle_webhooks",
				nonce: nonce,
			},
			function (response) {
				if (!response.success) {
					$button.prop("disabled", false);
					return;
				}

				var enabled = !!response.data.enabled;
				$button.data("enabled", enabled ? "1" : "0");
				if (enabled) {
					$bar.css({ border: "1px solid #c3e6cb", background: "#d4edda" });
					$button.removeClass("button-primary").text(i18n.disableWebhooks || "Desactivar webhooks");
				} else {
					$bar.css({ border: "1px solid #ffc107", background: "#fff3cd" });
					$button.addClass("button-primary").text(i18n.enableWebhooks || "Activar webhooks");
				}
				$button.prop("disabled", false);
			}
		).fail(function () {
			$button.prop("disabled", false);
		});
	});

	$("#copy-webhook-url").on("click", function () {
		var $button = $(this);
		var url = pwlDteWebhookDebug.webhookUrl;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function () {
				$button.text(i18n.copied || "Copiado").prop("disabled", true);
				setTimeout(function () {
					$button.text(i18n.copyUrl || "Copiar URL").prop("disabled", false);
				}, 2000);
			});
			return;
		}

		$("#webhook-url-display").select();
		document.execCommand("copy");
		$button.text(i18n.copied || "Copiado");
		setTimeout(function () {
			$button.text(i18n.copyUrl || "Copiar URL");
		}, 2000);
	});

	$("#test-webhook-form").on("submit", function (event) {
		event.preventDefault();

		var topic = $("#webhook-topic").val();
		var action = $("#webhook-action").val();
		var resourceId = $("#webhook-resource-id").val();
		var officeId = $("#webhook-office-id").val();
		var $result = $("#webhook-result");
		var $button = $("#send-test-btn");

		if (!resourceId) {
			alert(i18n.enterResourceId || "Ingresa un Resource ID para continuar.");
			return;
		}

		$button.prop("disabled", true).text(i18n.sending || "Enviando...");
		$result.html('<p style="color:#555;font-size:13px;">⏳ ' + (i18n.sendingPayload || "Enviando payload...") + "</p>");

		var payload = {
			cpnId: 2,
			resource: "/v2/stocks.json?variant=" + resourceId + "&office=" + officeId,
			resourceId: resourceId,
			topic: topic,
			action: action,
			officeId: parseInt(officeId, 10),
			send: Math.floor(Date.now() / 1000),
		};

		$.ajax({
			url: pwlDteWebhookDebug.webhookUrl,
			method: "POST",
			contentType: "application/json",
			data: JSON.stringify(payload),
			success: function (response) {
				var status = response.status || "processed";
				var message = response.message || "";
				var ignored = status === "ignored";
				var bgColor = ignored ? "#fff3cd" : "#d4edda";
				var borderColor = ignored ? "#ffc107" : "#c3e6cb";
				var icon = ignored ? "⚠" : "✓";
				$result.html(
					'<div style="background:' +
						bgColor +
						";border:1px solid " +
						borderColor +
						';padding:12px 16px;border-radius:4px;">' +
						"<strong>" +
						icon +
						" " +
						(ignored ? (i18n.ignored || "Ignorado") : (i18n.processedSuccess || "Procesado exitosamente")) +
						"</strong>" +
						(message ? '<br><span style="font-size:12px;color:#666;">' + message + "</span>" : "") +
						"</div>"
				);
				setTimeout(function () {
					window.location.reload();
				}, 1500);
			},
			error: function (xhr) {
				var errorMessage = "";
				try {
					errorMessage = JSON.parse(xhr.responseText).error;
				} catch (error) {
					errorMessage = xhr.statusText;
				}
				$result.html(
					'<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:12px 16px;border-radius:4px;">' +
						"<strong>✗ " +
						(i18n.processError || "Error al procesar webhook") +
						"</strong><br>" +
						'<span style="font-size:12px;color:#721c24;">' +
						errorMessage +
						"</span>" +
						"</div>"
				);
			},
			complete: function () {
				$button.prop("disabled", false).text(i18n.sendTestWebhook || "Enviar Webhook de Prueba");
			},
		});
	});
})(jQuery);
