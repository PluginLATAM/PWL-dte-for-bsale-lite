<?php
namespace PwlDte\Core;

defined('ABSPATH') || exit;

/** Chilean RUT validator and formatter. */
class RutHelper
{
	private static function normalize(string $rut): string
	{
		return preg_replace('/[^0-9kK]/', '', strtoupper($rut));
	}

	public static function validate_rut(string $rut): bool
	{
		$rut = self::normalize($rut);
		if (strlen($rut) < 2) {
			return false;
		}

		$body       = substr($rut, 0, -1);
		$dv         = substr($rut, -1);
		$sum        = 0;
		$multiplier = 2;

		for ($i = strlen($body) - 1; $i >= 0; $i--) {
			$sum       += (int) $body[$i] * $multiplier;
			$multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
		}

		$computed    = 11 - ($sum % 11);
		$expected_dv = match ($computed) {
			11      => '0',
			10      => 'K',
			default => (string) $computed,
		};

		return $dv === $expected_dv;
	}

	/** Format to display form "12.345.678-9". */
	public static function format_rut(string $rut): string
	{
		$rut = self::normalize($rut);
		if (strlen($rut) < 2) {
			return $rut;
		}
		return number_format((int) substr($rut, 0, -1), 0, ',', '.') . '-' . substr($rut, -1);
	}

	/** Clean to API form "12345678-9". */
	public static function clean_rut(string $rut): string
	{
		$rut = self::normalize($rut);
		if (strlen($rut) < 2) {
			return $rut;
		}
		return substr($rut, 0, -1) . '-' . substr($rut, -1);
	}
}
