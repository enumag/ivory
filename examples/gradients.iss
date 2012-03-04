@gradient-x($left, $right: $left) {
	background-image: -o-linear-gradient(left,$left,$right);
	background-image: -ms-linear-gradient(left,$left,$right);
	background-image: -moz-linear-gradient(left,$left,$right);
	background-image: -webkit-gradient(linear,left top,right top,color-stop(0,$left),color-stop(1,$right));
	$ie: 'progid:DXImageTransform.Microsoft.gradient(startColorStr=' . iergba($left) . ',EndColorStr=' . iergba($right) . ',GradientType=1)';
	%filter: $ie;
	-ms-filter: $ie;
}

@gradient-y($top, $bottom: $top) {
	background-image: -o-linear-gradient(top,$top 100%,$bottom);
	background-image: -ms-linear-gradient(top,$top 100%,$bottom);
	background-image: -moz-linear-gradient(top,$top 100%,$bottom);
	background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0,$top),color-stop(1,$bottom));
	$ie: 'progid:DXImageTransform.Microsoft.gradient(startColorStr=' . iergba($top) . ',EndColorStr=' . iergba($bottom) . ')';
	%filter: $ie;
	-ms-filter: $ie;
}

.gradient-x {
	@gradient-x: #ff0-25 #000-50;
}

.gradient-y {
	@gradient-y: #ff0-25 #000-50;
}