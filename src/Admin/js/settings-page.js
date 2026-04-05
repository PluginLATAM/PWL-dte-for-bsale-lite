jQuery(function ($) {
	"use strict";

	$("#pwl-dte-for-bsale-load-offices").on("click", function () {
		var $btn = $(this);
		var $status = $("#pwl-dte-for-bsale-offices-status");

		$btn.prop("disabled", true);
		$status.text(pwlDteSettings.labels.loading);

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "pwl_dte_get_offices",
				nonce: pwlDteSettings.nonce,
			},
			success: function (r) {
				if (!r.success) {
					$status.text(
						pwlDteSettings.labels.errorPrefix +
							(r.data && r.data.message
								? r.data.message
								: pwlDteSettings.labels.unknownError),
					);
					$btn.prop("disabled", false);
					return;
				}

				$(".pwl-dte-for-bsale-office-select").each(function () {
					var $s = $(this);
					var cur = $s.data("current") || $s.val();
					var first = $s.find("option:first").clone();

					$s.empty().append(first);
					$.each(r.data.offices, function (_i, o) {
						var $o = $("<option>")
							.val(o.id)
							.text(o.name + " (#" + o.id + ")");
						if (String(o.id) === String(cur)) {
							$o.prop("selected", true);
						}
						$s.append($o);
					});
				});

				$status.text(pwlDteSettings.labels.loaded);
				$btn.prop("disabled", false);
			},
			error: function () {
				$status.text(pwlDteSettings.labels.connectionError);
				$btn.prop("disabled", false);
			},
		});
	});
});
