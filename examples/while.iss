$i: 1;
while ($i <= 8) .block > div.class<$i>, .block > div:nth-child(<$i>) {
	background: url('/images/obrazek' . $i . '.jpg') no-repeat;
	$i: $i + 1;
}