body {
	%string: '\'""\'';
	%test: '\'';
	$ie: "progid:DXImageTransform.Microsoft.gradient(startColorStr=#000,EndColorStr=#fff,GradientType=1)";
	%filter: $ie;
	filter: raw($ie);
	-ms-filter: $ie;
}