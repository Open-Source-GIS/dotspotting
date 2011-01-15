<?php

	#
	# $Id$
	#

	# HEY LOOK! THIS DOESN'T WORK.

	loadpear("modestmaps/ModestMaps");
	loadpear("fpdf");

	#################################################################

	function pdf_export_dots(&$dots, &$more){

		$w = 11;
		$h = 8.5;

		$margin = .5;
		$dpi = 72;

		$pdf = new FPDF("P", "in", array($w, $h));
		$pdf->setMargins($margin, $margin);

		# First, add the map

		$pdf->addPage();

		$map_img = _pdf_export_dots_map($dots, ($w * $dpi), ($h * $dpi));
		$pdf->Image($map_img, 0, 0, 0, 0, 'PNG');

		# Now add the dots

		$pdf->addPage();

		$x = $margin;
		$y = $margin;

		$y += _pdf_add_row($pdf, $more['columns'], null, $w, $h, $margin, $x, $y);

		foreach ($dots as $dot){

			$_y = _pdf_add_row($pdf, $more['columns'], $dot, $w, $h, $margin, $x, $y);

			if ($_y != -1){
				$y += $_y;
				continue;
			}

			$pdf->addPage();

			$x = $margin;
			$y = $margin;

			$y += _pdf_add_row($pdf, $more['columns'], null, $w, $h, $margin, $x, $y);
			$y += _pdf_add_row($pdf, $more['columns'], $dot, $w, $h, $margin, $x, $y);
		}

		# Go!

		$pdf->Output();
		unlink($map_img);
	}

	#################################################################

	function _pdf_add_row($pdf, $cols, $dot, $w, $h, $margin, $x, $y){

		if ($dot){
			$pdf->SetFont('Helvetica', '', 8);
		}

		else {
			$pdf->SetFont('Helvetica', 'B', 10);
		}

		$col_w = ceil($w - ($margin * 2)) / count($cols);
		$col_h = .2;

		$row_h = $col_h;

		foreach ($cols as $col){

			$str = ($dot) ? $dot[$col] : $col;
			$str_w = ceil($pdf->GetStringWidth($str));

			if ($str_w > $col_w){
				$lines = ceil($str_w / $col_w);
				$row_h = max($row_h, ($lines * $col_h));
			}
		}

		if (($y + $row_h) > ($h - $margin * 2)){
			return -1;
		}

		foreach ($cols as $col){

			$pdf->SetXY($x, $y);

			$pdf->Rect($x, $y, $col_w, $row_h);
			$str = ($dot) ? $dot[$col] : $col;
			$pdf->MultiCell($col_w, $col_h, trim($str), 0, 'L');

			$x += $col_w;
		}

		return $row_h;
	}

	#################################################################

	# See this: It is basically a clone of what's happening in lib_png.
	# Soon it will be time to reconcile the two. But not yet.
	# (20110113/straup)

	function _pdf_export_dots_map(&$dots, $w, $h){

		$dot_size = 20;

		$swlat = null;
		$swlon = null;
		$nelat = null;
		$nelon = null;

		foreach ($dots as $dot){
			$swlat = (! isset($swlat)) ? $dot['latitude'] : min($swlat, $dot['latitude']);
			$swlon = (! isset($swlon)) ? $dot['longitude'] : min($swlon, $dot['longitude']);
			$nelat = (! isset($nelat)) ? $dot['latitude'] : max($nelat, $dot['latitude']);
			$nelon = (! isset($nelon)) ? $dot['longitude'] : max($nelon, $dot['longitude']);
		}

		$template = $GLOBALS['cfg']['maptiles_template_url'];

		$hosts = $GLOBALS['cfg']['maptiles_template_hosts'];
		shuffle($hosts);
		$template = str_replace("{S}", $hosts[0], $template);

		$provider = new MMaps_Templated_Spherical_Mercator_Provider($template);

		$sw = new MMaps_Location($swlat, $swlon);
		$ne = new MMaps_Location($nelat, $nelon);

		$dims = new MMaps_Point($w, $h);

		$map = MMaps_mapByExtent($provider, $sw, $ne, $dims);
		$im = $map->draw();

		$points = array();

		$fill = imagecolorallocatealpha($im, 0, 17, 45, 96);
		$stroke = imagecolorallocate($im, 153, 204, 0);

		foreach ($dots as $dot){

			$loc = new MMaps_Location($dot['latitude'], $dot['longitude']);
			$pt = $map->locationPoint($loc);

			imagefilledellipse($im, $pt->x, $pt->y, $dot_size, $dot_size, $fill);

			imagesetthickness($im, 3);
			imagearc($im, $pt->x, $pt->y, $dot_size, $dot_size, 0, 359.9, $stroke);
		}

		$tmp = tempnam(sys_get_temp_dir(), "pdf") . ".png";

		imagepng($im, $tmp);
		imagedestroy($im);

		return $tmp;
	}

	#################################################################
?>