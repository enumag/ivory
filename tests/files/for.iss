for ($i: 1 .. 8) .block > div.class<$i>, .block > div:nth-child(<$i>) {
	background: url($i . '.jpg') no-repeat;
	$i: $i + 1;
}