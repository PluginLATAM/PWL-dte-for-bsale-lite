jQuery(function ($) {
	"use strict";

	$(document).on("click", ".pwl-dte-for-bsale-logs-retry", function () {
		var $btn = $(this);
		var orderId = $btn.data("order-id");
		var nonce = $btn.data("nonce");

		if (!confirm(pwlDteLogs.labels.confirmRetry)) {
			return;
		}

		$btn.prop("disabled", true).text(pwlDteLogs.labels.processing);

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "pwl_dte_regenerate_dte",
				nonce: nonce,
				order_id: orderId,
			},
			success: function (r) {
				if (r.success) {
					alert(r.data.message || pwlDteLogs.labels.success);
					location.reload();
					return;
				}

				alert(
					pwlDteLogs.labels.errorPrefix +
						(r.data && r.data.message
							? r.data.message
							: pwlDteLogs.labels.unknownError),
				);
				$btn.prop("disabled", false).text(pwlDteLogs.labels.retry);
			},
			error: function () {
				alert(pwlDteLogs.labels.connectionError);
				$btn.prop("disabled", false).text(pwlDteLogs.labels.retry);
			},
		});
	});
});
