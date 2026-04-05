jQuery(function ($) {
	"use strict";

	$(document).on("click", ".pwl-dte-for-bsale-dte-action", function () {
		var $btn = $(this);
		var action = $btn.data("action");
		var label = $btn.data("label-done");

		if (!confirm(pwlDteMetabox.labels.confirm)) {
			return;
		}

		$btn.prop("disabled", true).text(pwlDteMetabox.labels.processing);

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: action,
				nonce: $btn.data("nonce"),
				order_id: $btn.data("order-id"),
			},
			success: function (r) {
				if (r.success) {
					alert(r.data.message || pwlDteMetabox.labels.success);
					if (action === "pwl_dte_regenerate_dte") {
						location.reload();
						return;
					}
					$btn.prop("disabled", false).text(label);
					return;
				}

				alert(
					pwlDteMetabox.labels.errorPrefix +
						(r.data && r.data.message
							? r.data.message
							: pwlDteMetabox.labels.unknownError),
				);
				$btn.prop("disabled", false).text(label);
			},
			error: function () {
				alert(pwlDteMetabox.labels.connectionError);
				$btn.prop("disabled", false).text(label);
			},
		});
	});
});
